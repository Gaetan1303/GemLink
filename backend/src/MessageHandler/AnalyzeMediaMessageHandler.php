<?php



namespace App\MessageHandler;

use App\Dto\AiAnalysisResult;
use App\Entity\Embedding;
use App\Entity\Pierre;
use App\Entity\Publication;
use App\Entity\VersionModeleIa;
use App\Exception\AiAnalysisException;
use App\Message\AnalyzeMediaMessage;
use App\Repository\EmbeddingRepository;
use App\Repository\PierreRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Repository\VersionModeleIaRepository;
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
 */
#[AsMessageHandler]
final class AnalyzeMediaMessageHandler
{
    private const CLIP_MODEL_NAME = 'clip-vit-b32-openai';

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

        $pierre = $this->findOrCreatePierre($result);
        // Flush immédiat : upsertMatch() ci-dessous écrit en SQL brut, hors de
        // l'Unit of Work Doctrine — si $pierre vient d'être créée, sa ligne
        // doit déjà exister en base avant l'INSERT sur publication_pierre,
        // sous peine de violation de contrainte FK.
        $this->em->flush();

        $this->publicationPierres->upsertMatch($publication, $pierre, $result->getConfidence());
        $this->persistEmbedding($publication, $result);

        $publication->setStatus(Publication::STATUS_ANALYZED);
        $this->em->flush();
    }

    /**
     * Télécharge le média depuis le CDN puis appelle POST /analyze en
     * multipart/form-data — l'endpoint FastAPI attend un UploadFile, pas une
     * URL JSON.
     */
    private function requestAnalysis(Publication $publication): AiAnalysisResult
    {
        $media = $this->httpClient->request('GET', $publication->getMediaUrl(), ['timeout' => 15]);

        if ($media->getStatusCode() >= 400) {
            throw AiAnalysisException::unreachableMedia($publication->getMediaUrl(), $media->getStatusCode());
        }

        $formData = new FormDataPart([
            'file' => new DataPart(
                $media->getContent(),
                'media',
                $media->getHeaders()['content-type'][0] ?? 'image/jpeg',
            ),
        ]);

        $response = $this->httpClient->request('POST', rtrim($this->aiServiceUrl, '/') . '/analyze', [
            'headers' => array_merge(
                $formData->getPreparedHeaders()->toArray(),
                ['X-Internal-Key' => $this->internalApiKey],
            ),
            'body' => $formData->bodyToIterable(),
            'timeout' => 30,
        ]);

        if ($response->getStatusCode() >= 400) {
            // Relancée : déclenche le retry exponentiel de Messenger.
            throw AiAnalysisException::invalidHttpStatus($response->getStatusCode());
        }

        return AiAnalysisResult::fromArray($response->toArray());
    }

    /**
     * Réutilise une fiche Pierre existante (comparaison insensible à la
     * casse, le classifieur ViT renvoie des labels en minuscules) ou en crée
     * une nouvelle, enrichie par l'agent connaissance FastAPI.
     */
    private function findOrCreatePierre(AiAnalysisResult $result): Pierre
    {
        $existing = $this->pierres->findOneByNameIgnoreCase($result->getLabel());
        if ($existing !== null) {
            return $existing;
        }

        $pierre = new Pierre($result->getLabel());
        $pierre->setCategory($result->getCategory());
        $pierre->setHardness($result->getHardnessValue());
        $pierre->setCrystalSystem($result->getCrystalSystem());
        $pierre->setComposition($result->getComposition());
        $pierre->setDescription($result->getDescription());

        $this->em->persist($pierre);

        return $pierre;
    }

    /**
     * CA-4 : un seul embedding par publication (contrainte UNIQUE en base) —
     * on met à jour l'existant en cas de ré-analyse plutôt que d'en créer un
     * second.
     */
    private function persistEmbedding(Publication $publication, AiAnalysisResult $result): void
    {
        $modelVersion = $this->modelVersions->findActiveByType(VersionModeleIa::TYPE_CLIP)
            ?? $this->createDefaultClipModelVersion();

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
    private function createDefaultClipModelVersion(): VersionModeleIa
    {
        $modelVersion = new VersionModeleIa(
            self::CLIP_MODEL_NAME,
            VersionModeleIa::TYPE_CLIP,
            VersionModeleIa::STATUS_ACTIVE,
        );

        $this->em->persist($modelVersion);

        return $modelVersion;
    }
}