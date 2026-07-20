<?php



namespace App\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Vitrine;
use App\Entity\VitrineMedia;
use App\Entity\VitrinePublication;
use App\Exception\VitrineAccessDeniedException;
use App\Exception\VitrineEmptyException;
use App\Exception\VitrineValidationException;
use App\Repository\VitrineMediaRepository;
use App\Repository\VitrinePublicationRepository;
use App\Repository\VitrineRepository;
use App\Service\Media\MediaUploadService;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VitrineService
{
    private const TITLE_MAX_LENGTH = 100;
    private const DESCRIPTION_MAX_LENGTH = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VitrineRepository $vitrines,
        private readonly VitrinePublicationRepository $vitrinePublications,
        private readonly VitrineMediaRepository $vitrineMedias,
        private readonly MediaUploadService $mediaUploadService,
    ) {
    }

    /**
     * CA-1 : titre obligatoire (max 100), description optionnelle (max 500),
     * slug généré automatiquement avec gestion des collisions.
     *
     * @throws VitrineValidationException si le titre/la description sont
     *         invalides, OU si une collision de slug survient malgré la
     *         vérification préalable (race condition entre deux créations
     *         concurrentes du même titre — la contrainte unique en base
     *         (uq_vitrine_slug) reste le dernier rempart, celle-ci la traduit
     *         en erreur métier 422 plutôt qu'un 500 brut).
     */
    public function createVitrine(User $user, ?string $title, ?string $description): Vitrine
    {
        $cleanTitle = $this->sanitizeTitle($title);
        $cleanDescription = $this->sanitizeDescription($description);

        $slug = $this->vitrines->generateUniqueSlug($cleanTitle);
        $vitrine = new Vitrine($user, $cleanTitle, $slug);

        if ($cleanDescription !== null) {
            $vitrine->setDescription($cleanDescription);
        }

        try {
            $this->vitrines->save($vitrine);
        } catch (UniqueConstraintViolationException) {
            throw new VitrineValidationException(
                'Une Vitrine avec un titre très similaire vient d\'être créée au même moment. Merci de réessayer.'
            );
        }

        return $vitrine;
    }

    public function updateVitrine(Vitrine $vitrine, User $actor, ?string $title, ?string $description): Vitrine
    {
        $this->assertOwner($vitrine, $actor);

        if ($title !== null) {
            $cleanTitle = $this->sanitizeTitle($title);

            if ($cleanTitle !== $vitrine->getTitle()) {
                $vitrine->setTitle($cleanTitle);
                $vitrine->setSlug($this->vitrines->generateUniqueSlug($cleanTitle, $vitrine->getId()));
            }
        }

        if ($description !== null) {
            $vitrine->setDescription($this->sanitizeDescription($description));
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            throw new VitrineValidationException(
                'Une Vitrine avec un titre très similaire existe déjà. Merci de réessayer avec un autre titre.'
            );
        }

        return $vitrine;
    }

    public function deleteVitrine(Vitrine $vitrine, User $actor): void
    {
        $this->assertOwner($vitrine, $actor);

        $this->em->remove($vitrine);
        $this->em->flush();
    }

    /**
     * CA-2 : ajout individuel d'un post, position = fin de la collection.
     *
     * @throws VitrineValidationException si le post est déjà présent —
     *         détecté soit par la vérification applicative ci-dessous, soit
     *         (race condition entre deux ajouts concurrents du même post)
     *         par la contrainte de clé primaire composite en base sur
     *         vitrine_publication, traduite ici en la même erreur métier.
     */
    public function addItem(Vitrine $vitrine, User $actor, Publication $publication): VitrinePublication
    {
        $this->assertOwner($vitrine, $actor);

        if ($publication->isDeleted()) {
            throw new VitrineValidationException('Ce post n\'est plus disponible.');
        }

        foreach ($vitrine->getItems() as $existing) {
            if ($existing->getPublication()->getId()->equals($publication->getId())) {
                throw new VitrineValidationException('Ce post est déjà présent dans cette Vitrine.');
            }
        }

        $item = new VitrinePublication($vitrine, $publication, $vitrine->getNextPosition());
        $vitrine->addItem($item);

        try {
            $this->vitrinePublications->save($item);
        } catch (UniqueConstraintViolationException) {
            throw new VitrineValidationException('Ce post est déjà présent dans cette Vitrine.');
        }

        return $item;
    }

    public function removeItem(Vitrine $vitrine, User $actor, VitrinePublication $item): void
    {
        $this->assertOwner($vitrine, $actor);

        $vitrine->removeItem($item);
        $this->vitrinePublications->remove($item);
    }

    public function addMedia(Vitrine $vitrine, User $actor, ?UploadedFile $file): VitrineMedia
    {
        $this->assertOwner($vitrine, $actor);

        $directory = sprintf('vitrines/%s', (new DateTimeImmutable())->format('Y/m'));
        $uploaded = $this->mediaUploadService->upload($file, $directory);

        $media = new VitrineMedia($vitrine, $uploaded->mediaUrl, $uploaded->mediaType, $vitrine->getNextPosition());
        $vitrine->addMedia($media);

        $this->vitrineMedias->save($media);

        return $media;
    }

    public function removeMedia(Vitrine $vitrine, User $actor, VitrineMedia $media): void
    {
        $this->assertOwner($vitrine, $actor);

        $vitrine->removeMedia($media);
        $this->vitrineMedias->remove($media);
    }

    /**
     * CA-3 : réordonnancement unifié posts + médias. Toute la validation
     * (cohérence de la liste, appartenance des éléments à cette Vitrine)
     * vit ici plutôt que dans Vitrine::reorderItems() — cohérent avec le
     * reste du projet où PostService porte la validation, pas Publication.
     *
     * @param array<int, array{type: string, id: string}> $orderedItems
     */
    public function reorderItems(Vitrine $vitrine, User $actor, array $orderedItems): void
    {
        $this->assertOwner($vitrine, $actor);

        $postsByPublicationId = [];
        foreach ($vitrine->getItems() as $item) {
            $postsByPublicationId[$item->getPublication()->getId()->toRfc4122()] = $item;
        }

        $mediaById = [];
        foreach ($vitrine->getMediaItems() as $media) {
            $mediaById[$media->getId()->toRfc4122()] = $media;
        }

        if (count($orderedItems) !== count($postsByPublicationId) + count($mediaById)) {
            throw new VitrineValidationException('La liste fournie ne correspond pas au contenu de la Vitrine.');
        }

        foreach ($orderedItems as $index => $entry) {
            $type = $entry['type'] ?? null;
            $id = $entry['id'] ?? null;

            if ($type === 'post' && isset($postsByPublicationId[$id])) {
                $postsByPublicationId[$id]->setPosition($index);
                unset($postsByPublicationId[$id]);
                continue;
            }

            if ($type === 'media' && isset($mediaById[$id])) {
                $mediaById[$id]->setPosition($index);
                unset($mediaById[$id]);
                continue;
            }

            throw new VitrineValidationException('Un élément de la liste ne fait pas partie de cette Vitrine.');
        }

        $vitrine->touch();
        $this->em->flush();
    }

    /**
     * CA-4 : refuse la publication si la Vitrine ne contient ni post ni média.
     */
    public function publish(Vitrine $vitrine, User $actor): void
    {
        $this->assertOwner($vitrine, $actor);

        if ($vitrine->isEmpty()) {
            throw new VitrineEmptyException();
        }

        $vitrine->publish();
        $this->em->flush();
    }

    public function unpublish(Vitrine $vitrine, User $actor): void
    {
        $this->assertOwner($vitrine, $actor);

        $vitrine->unpublish();
        $this->em->flush();
    }

    public function recordView(Vitrine $vitrine): void
    {
        $vitrine->incrementViewCount();
        $this->em->flush();
    }

    private function assertOwner(Vitrine $vitrine, User $actor): void
    {
        if (!$vitrine->getUser()->getId()->equals($actor->getId())) {
            throw new VitrineAccessDeniedException('Vous n\'êtes pas propriétaire de cette Vitrine.');
        }
    }

    private function sanitizeTitle(?string $title): string
    {
        $trimmed = trim((string) $title);

        if ($trimmed === '') {
            throw new VitrineValidationException('Le titre est obligatoire.');
        }

        if (mb_strlen($trimmed) > self::TITLE_MAX_LENGTH) {
            throw new VitrineValidationException(sprintf(
                'Le titre ne peut pas dépasser %d caractères.',
                self::TITLE_MAX_LENGTH
            ));
        }

        return $trimmed;
    }

    private function sanitizeDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $trimmed = trim($description);

        if ($trimmed === '') {
            return null;
        }

        if (mb_strlen($trimmed) > self::DESCRIPTION_MAX_LENGTH) {
            throw new VitrineValidationException(sprintf(
                'La description ne peut pas dépasser %d caractères.',
                self::DESCRIPTION_MAX_LENGTH
            ));
        }

        return $trimmed;
    }
}