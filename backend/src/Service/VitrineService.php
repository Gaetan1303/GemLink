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