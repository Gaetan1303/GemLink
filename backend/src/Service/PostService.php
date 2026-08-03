<?php



namespace App\Service;

use App\Entity\Publication;
use App\Entity\Tag;
use App\Entity\User;
use App\Exception\PostAccessDeniedException;
use App\Exception\PostValidationException;
use App\Message\AwardPointsMessage;
use App\Repository\TagRepository;
use App\Service\Media\MediaUploadService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US 2.1 : logique métier de publication d'un post (CA-1 à CA-4).
 * Correspond à la boîte "PostService : Upload CDN · Soft delete" du
 * diagramme d'architecture (couche métier).
 *
 * SRP appliqué à l'intérieur de la classe plutôt qu'au niveau HTTP : ce
 * service délègue la validation/le transfert du fichier à MediaUploadService
 * et le déclenchement de l'analyse IA à AiOrchestrationService — il n'implémente
 * lui-même ni l'un ni l'autre, il orchestre seulement.
 */
class PostService
{
    private const PRIVILEGED_ROLES = ['MODERATOR', 'ADMIN'];

    private const TITLE_MAX_LENGTH = 200;
    private const DESCRIPTION_MAX_LENGTH = 2000;
    private const TAG_MAX_LENGTH = 50;
    private const TAGS_MAX_COUNT = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TagRepository $tags,
        private readonly MediaUploadService $mediaUploadService,
        private readonly AiOrchestrationService $aiOrchestration,
        private readonly FeedCacheService $feedCache,
        private readonly MessageBusInterface $messageBus,
        private readonly ?BadgeAwardService $badgeAwards = null,
    ) {
    }

    /**
     * CA-1 : le fichier média est obligatoire, le reste est optionnel.
     * CA-2 : délégué à MediaUploadService (magic bytes + taille + durée).
     * CA-3 : upload CDN puis création immédiate en base (PENDING_ANALYSIS),
     *        analyse IA déclenchée en tâche de fond sans bloquer la réponse.
     *
     * @param string[] $tagNames
     */
    public function createPost(
        User $author,
        ?UploadedFile $mediaFile,
        ?string $title,
        ?string $description,
        array $tagNames = [],
    ): Publication {
        $directory = sprintf('publications/%s', (new DateTimeImmutable())->format('Y/m'));
        $media = $this->mediaUploadService->upload($mediaFile, $directory);

        $publication = new Publication($author, $media->mediaUrl, $media->mediaType);

        if (($cleanTitle = $this->sanitizeText($title, self::TITLE_MAX_LENGTH, 'Le titre')) !== null) {
            $publication->setTitle($cleanTitle);
        }

        if (($cleanDescription = $this->sanitizeText($description, self::DESCRIPTION_MAX_LENGTH, 'La description')) !== null) {
            $publication->setDescription($cleanDescription);
        }

        foreach ($this->normalizeTagNames($tagNames) as $tagName) {
            $publication->addTag($this->findOrCreateTag($tagName));
        }

        $this->em->persist($publication);
        $this->em->flush();
        if ($this->badgeAwards !== null) {
            $this->badgeAwards->onPostCreated($author);
            $this->em->flush();
        }
        $this->feedCache->prepend($publication);
        $this->messageBus->dispatch(new AwardPointsMessage(
            $author->getId()->toRfc4122(),
            PointsService::ACTION_POST_CREATED,
            $publication->getId()->toRfc4122(),
        ));

        // CA-3 : ne bloque jamais la réponse — le traitement se fait dans le worker Messenger.
        $this->aiOrchestration->requestAnalysis($publication);

        return $publication;
    }

    /**
     * CA-4 : suppression réservée à l'auteur, un modérateur ou un administrateur.
     * Soft delete privilégié en production pour la traçabilité (doc/bdd.md).
     */
    public function softDelete(Publication $post, User $actor): void
    {
        $this->assertCanDelete($post, $actor);

        $post->setDeletedAt(new DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * CA-4 : suppression physique (purge RGPD). Déclenche les ON DELETE CASCADE
     * définis en base (embeddings, likes, commentaires, publication_tag...).
     */
    public function hardDelete(Publication $post, User $actor): void
    {
        $this->assertCanDelete($post, $actor);

        $this->em->remove($post);
        $this->em->flush();
    }

    /**
     * US 2.2 — comptage de vues best-effort (pas de déduplication par
     * utilisateur/IP au stade MVP), appelé à chaque consultation du détail.
     */
    public function recordView(Publication $post): void
    {
        $post->incrementViewCount();
        $this->em->flush();
    }

    private function assertCanDelete(Publication $post, User $actor): void
    {
        $isAuthor = $post->getUser()->getId()->equals($actor->getId());
        $isPrivileged = in_array($actor->getRole(), self::PRIVILEGED_ROLES, true);

        if (!$isAuthor && !$isPrivileged) {
            throw new PostAccessDeniedException(
                'Seul l\'auteur, un modérateur ou un administrateur peut supprimer ce post.'
            );
        }
    }

    private function findOrCreateTag(string $name): Tag
    {
        $existing = $this->tags->findOneByName($name);

        if ($existing !== null) {
            return $existing;
        }

        $tag = new Tag($name);
        $this->em->persist($tag);

        return $tag;
    }

    /**
     * @param string[] $tagNames
     * @return string[] noms de tags dédupliqués (insensible à la casse), max 10
     */
    private function normalizeTagNames(array $tagNames): array
    {
        $normalized = [];

        foreach ($tagNames as $tagName) {
            if (!is_string($tagName)) {
                continue;
            }

            // Le préfixe # est une convention de saisie/UI, pas une partie
            // de l'identifiant stocké : évite de rendre ##quartz au profil.
            $trimmed = ltrim(trim($tagName), '#');

            if ($trimmed === '') {
                continue;
            }

            if (mb_strlen($trimmed) > self::TAG_MAX_LENGTH) {
                throw new PostValidationException(sprintf(
                    'Un tag ne peut pas dépasser %d caractères.',
                    self::TAG_MAX_LENGTH
                ));
            }

            $normalized[mb_strtolower($trimmed)] = $trimmed;
        }

        if (count($normalized) > self::TAGS_MAX_COUNT) {
            throw new PostValidationException(sprintf(
                'Un post ne peut pas avoir plus de %d tags.',
                self::TAGS_MAX_COUNT
            ));
        }

        return array_values($normalized);
    }

    private function sanitizeText(?string $value, int $maxLength, string $fieldLabel): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > $maxLength) {
            throw new PostValidationException(sprintf(
                '%s ne peut pas dépasser %d caractères.',
                $fieldLabel,
                $maxLength
            ));
        }

        return $trimmed;
    }
}
