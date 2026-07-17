<?php



namespace App\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Vitrine;
use App\Entity\VitrinePublication;
use App\Exception\VitrineAccessDeniedException;
use App\Exception\VitrineEmptyException;
use App\Exception\VitrineValidationException;
use App\Repository\VitrinePublicationRepository;
use App\Repository\VitrineRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * US 4.1 : logique métier de la Vitrine (CA-1 à CA-4).
 * Même partage des responsabilités que PostService : les entités portent
 * les transitions d'état simples (publish/unpublish, touch), le service
 * porte la validation et les règles d'autorisation.
 */
class VitrineService
{
    private const TITLE_MAX_LENGTH = 100;
    private const DESCRIPTION_MAX_LENGTH = 500;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VitrineRepository $vitrines,
        private readonly VitrinePublicationRepository $vitrinePublications,
    ) {
    }

    /**
     * CA-1 : titre obligatoire (max 100), description optionnelle (max 500),
     * slug généré automatiquement avec gestion des collisions.
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

        $this->vitrines->save($vitrine);

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

        $this->em->flush();

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

        $this->vitrinePublications->save($item);

        return $item;
    }

    public function removeItem(Vitrine $vitrine, User $actor, VitrinePublication $item): void
    {
        $this->assertOwner($vitrine, $actor);

        $vitrine->removeItem($item);
        $this->vitrinePublications->remove($item);
    }

    /**
     * CA-3 : réordonnancement complet (glisser-déposer côté front envoie la
     * liste ordonnée des publicationId).
     *
     * @param string[] $orderedPublicationIds
     */
    public function reorderItems(Vitrine $vitrine, User $actor, array $orderedPublicationIds): void
    {
        $this->assertOwner($vitrine, $actor);

        $vitrine->reorderItems($orderedPublicationIds);
        $this->em->flush();
    }

    /**
     * CA-4 : refuse la publication si la Vitrine ne contient aucun item.
     */
    public function publish(Vitrine $vitrine, User $actor): void
    {
        $this->assertOwner($vitrine, $actor);

        if ($vitrine->getItems()->isEmpty()) {
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