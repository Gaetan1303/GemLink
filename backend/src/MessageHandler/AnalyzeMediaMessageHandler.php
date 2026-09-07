<?php



namespace App\MessageHandler;

use App\Dto\AiAnalysisResult;
use App\Entity\Embedding;
use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\Tag;
use App\Entity\VersionModeleIa;
use App\Exception\AiAnalysisException;
use App\Message\AnalyzeMediaMessage;
use App\Repository\EmbeddingRepository;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Repository\TagRepository;
use App\Repository\VersionModeleIaRepository;
use App\Service\BadgeAwardService;
use App\Service\AiOrchestrationService;
use App\Service\Media\AiMediaReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * US 3.1 CA-2/CA-4 : consomme AnalyzeMediaMessage en tâche de fond (IA Worker),
 * appelle le service FastAPI (pipeline YOLO -> ViT -> CLIP) puis persiste :
 *  - PublicationPierre (label + confidence, table de jointure du MLD)
 *  - Embedding (vecteur CLIP 512-d, pgvector)
 *
 * Retry exponentiel (CA-3) : toute exception ici relance le message via
 * Symfony Messenger (config/packages/messenger.yaml, retry_strategy). La
 * bascule définitive en ANALYSIS_FAILED, une fois les tentatives épuisées,
 * reste gérée par App\EventListener\AnalyzeMediaFailureListener — jamais ici.
 *
 * Lecture du média (fetchMedia) : en mode 'local' (dev), le worker et le
 * serveur HTTP tournent dans le même conteneur Docker et partagent le même
 * disque -> on lit le fichier directement en filesystem, sans repasser par
 * HTTP/localhost (qui casse en conteneur à cause du port mapping Docker).
 * En mode 'r2' (prod/Railway), l'URL est publique -> un GET HTTP classique
 * reste légitime.
 */
#[AsMessageHandler]
final class AnalyzeMediaMessageHandler
{
    private const STORAGE_MODE_LOCAL = 'local';
    private const STORAGE_MODE_R2 = 'r2';

    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly PierreRepository $pierres,
        private readonly VersionModeleIaRepository $modelVersions,
        private readonly PublicationPierreRepository $publicationPierres,
        private readonly EmbeddingRepository $embeddings,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiServiceUrl,
        private readonly string $internalApiKey,
        private readonly string $mediaStorageMode,    // 'local' | 'r2'
        private readonly string $uploadDir,           // '%kernel.project_dir%/public/uploads'
        private readonly string $localPublicBaseUrl,  // 'http://localhost:8000/uploads'
        private readonly ?BadgeAwardService $badgeAwards = null,
        private readonly ?TagRepository $tags = null,
        private readonly ?AiOrchestrationService $aiOrchestration = null,
        private readonly ?AiMediaReader $mediaReader = null,
    ) {
    }

    public function __invoke(AnalyzeMediaMessage $message): void
    {
        $publication = $this->publications->find(Uuid::fromString($message->getPublicationId()));

        if ($publication === null || $publication->isDeleted()) {
            // CA-4 : le post a pu être supprimé pendant que le message était en file.
            return;
        }

        $result = $this->requestAnalysis($publication);

        if ($result->isUnknown()) {
            // Completed analysis with no identification: no fictitious Pierre,
            // identified tag or badge. Keep existing links on explicit reanalysis.
            $this->persistEmbedding($publication, $result);
            $publication->setStatus(Publication::STATUS_ANALYZED);
            $this->em->flush();
            return;
        }

        [$pierre, $isNewMineral] = $result->getReviewedPierre() !== null
            ? [$result->getReviewedPierre(), false]
            : $this->findOrCreatePierre($result);
        // Flush immédiat : upsertMatch() ci-dessous écrit en SQL brut, hors de
        // l'Unit of Work Doctrine — si $pierre vient d'être créée, sa ligne
        // doit déjà exister en base avant l'INSERT sur publication_pierre,
        // sous peine de violation de contrainte FK.
        $this->em->flush();

        $this->publicationPierres->upsertMatch($publication, $pierre, $result->getConfidence());
        $this->addDefaultIdentifiedTag($publication);
        $this->badgeAwards?->onStoneIdentified($publication->getUser(), $pierre, $isNewMineral);
        $this->persistEmbedding($publication, $result);

        $publication->setStatus(Publication::STATUS_ANALYZED);
        $this->em->flush();
    }

    /** Adds the canonical tag once the AI successfully identifies the specimen. */
    private function addDefaultIdentifiedTag(Publication $publication): void
    {
        if ($this->tags === null) return;

        $tag = $this->tags->findOneByName('Identifiée');
        if ($tag === null) {
            $tag = new Tag('Identifiée');
            $this->em->persist($tag);
        }
        $publication->addTag($tag);
    }

    /**
     * Récupère le média (disque local ou CDN selon l'environnement) puis
     * appelle POST /analyze en multipart/form-data — l'endpoint FastAPI
     * attend un UploadFile, pas une URL JSON.
     */
    private function requestAnalysis(Publication $publication): AiAnalysisResult
    {
        [$content, $mimeType] = $this->fetchMedia($publication->getMediaUrl(), $publication->getMediaType() === Publication::MEDIA_TYPE_VIDEO);

        $formData = new FormDataPart([
            'file' => new DataPart($content, 'media', $mimeType),
        ]);

        $response = $this->httpClient->request('POST', rtrim($this->aiServiceUrl, '/') . '/analyze', [
            'headers' => array_merge(
                $formData->getPreparedHeaders()->toArray(),
                ['X-Internal-Key' => $this->internalApiKey],
            ),
            'body' => $formData->bodyToIterable(),
            // 180s : la génération Ollama sur CPU prend 40-90s en régime
            // chaud, et jusqu'à 200s+ au premier appel (chargement à froid
            // du modèle). 30s était bien trop court et déclenchait un
            // "Idle timeout reached" avant même qu'Ollama ait terminé.
            'timeout' => 180,
        ]);

        if ($response->getStatusCode() >= 400) {
            // Relancée : déclenche le retry exponentiel de Messenger.
            throw AiAnalysisException::invalidHttpStatus($response->getStatusCode());
        }

        $result = AiAnalysisResult::fromArray($response->toArray());
        return $this->aiOrchestration?->reviewAnalysis($result, $content, $mimeType,
            $publication->getId()->toRfc4122(), 'user:' . $publication->getUser()->getId()->toRfc4122()) ?? $result;
    }

    /**
     * @return array{0: string, 1: string} [contenu binaire du fichier, mime-type]
     *
     * @throws AiAnalysisException si le média est introuvable/inaccessible.
     *         Cette exception déclenche le retry exponentiel de Messenger.
     */
    private function fetchMedia(string $mediaUrl, bool $allowVideo): array
    {
        $reader = $this->mediaReader ?? throw new AiAnalysisException('Lecteur média non configuré.');
        return $reader->read($mediaUrl, $allowVideo);
    }

    /**
     * Réutilise une fiche Pierre existante (comparaison insensible à la
     * casse, le classifieur ViT renvoie des labels en minuscules) ou en crée
     * une nouvelle, enrichie par l'agent connaissance FastAPI.
     */
    /** @return array{0:Pierre,1:bool} */
    private function findOrCreatePierre(AiAnalysisResult $result): array
    {
        $existing = $this->pierres->findOneByNameIgnoreCase($result->getLabel());
        if ($existing !== null) {
            return [$existing, false];
        }

        $pierre = new Pierre($result->getLabel());
        $pierre->setCategory($result->getCategory());
        $pierre->setHardness($result->getHardnessValue());
        $pierre->setCrystalSystem($result->getCrystalSystem());
        $pierre->setComposition($result->getComposition());
        $pierre->setDescription($result->getDescription());

        $this->em->persist($pierre);

        return [$pierre, true];
    }

    /**
     * CA-4 : un seul embedding par publication (contrainte UNIQUE en base) —
     * on met à jour l'existant en cas de ré-analyse plutôt que d'en créer un
     * second.
     */
    private function persistEmbedding(Publication $publication, AiAnalysisResult $result): void
    {
        $modelVersion = $this->modelVersions->findOneBy([
            'name' => $result->getClipModelVersion(),
            'modelType' => VersionModeleIa::TYPE_CLIP,
        ]) ?? $this->createClipModelVersion($result->getClipModelVersion());

        $existing = $this->embeddings->findOneBy(['publication' => $publication]);

        if ($existing !== null) {
            $existing->updateVectorData($result->getEmbedding(), $modelVersion);

            return;
        }

        $this->em->persist(new Embedding($publication, $modelVersion, $result->getEmbedding()));
    }

    /**
     * Bootstrap : crée la version "active" du modèle CLIP au premier passage
     * si la table ai_model_version n'a pas encore été initialisée par une
     * fixture ou l'écran d'admin IA (US 5.x).
     */
    private function createClipModelVersion(string $name): VersionModeleIa
    {
        $modelVersion = new VersionModeleIa(
            $name,
            VersionModeleIa::TYPE_CLIP,
            VersionModeleIa::STATUS_ACTIVE,
        );

        $this->em->persist($modelVersion);

        return $modelVersion;
    }
}
