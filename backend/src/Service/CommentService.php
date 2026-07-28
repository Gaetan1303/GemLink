<?php



namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\Commentaire;
use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\CommentAccessDeniedException;
use App\Exception\CommentValidationException;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * US 2.4 : logique métier des commentaires (CA-1 à CA-4).
 * Même répartition que PostService : ce service orchestre la persistance
 * du commentaire, la notification de l'auteur du post et la trace d'audit
 * à la suppression, sans déléguer à un contrôleur HTTP.
 */
class CommentService
{
    private const PRIVILEGED_ROLES = ['MODERATOR', 'ADMIN'];

    private const CONTENT_MAX_LENGTH = 1000;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * CA-1 : commentaire limité à 1000 caractères, associé à l'utilisateur
     * authentifié et au post cible.
     * CA-4 : notification in-app de l'auteur du post (sauf s'il commente
     * lui-même son propre post — s'auto-notifier n'a pas de sens).
     */
    public function createComment(User $author, Publication $publication, string $content): Commentaire
    {
        $trimmed = trim($content);

        if ($trimmed === '') {
            throw new CommentValidationException('Le commentaire ne peut pas être vide.');
        }

        if (mb_strlen($trimmed) > self::CONTENT_MAX_LENGTH) {
            throw new CommentValidationException(sprintf(
                'Un commentaire ne peut pas dépasser %d caractères.',
                self::CONTENT_MAX_LENGTH
            ));
        }

        $comment = new Commentaire($author, $publication, $trimmed);
        $this->em->persist($comment);

        $postAuthor = $publication->getUser();

        if (!$postAuthor->getId()->equals($author->getId())) {
            $notification = new Notification($postAuthor, Notification::TYPE_NEW_COMMENT);
            $notification->setTarget($publication->getId(), Notification::TARGET_TYPE_PUBLICATION);
            $this->em->persist($notification);
        }

        $this->em->flush();

        return $comment;
    }

    /**
     * CA-2 : suppression réservée à l'auteur du commentaire, un modérateur
     * ou un administrateur. Soft delete + entrée d'audit immuable.
     */
    public function deleteComment(Commentaire $comment, User $actor): void
    {
        $this->assertCanDelete($comment, $actor);

        $comment->setDeletedAt(new DateTimeImmutable());

        $auditLog = new AuditLog(
            $actor,
            AuditLog::ACTION_COMMENT_DELETED,
            AuditLog::TARGET_TYPE_COMMENTAIRE,
            $comment->getId(),
        );
        $this->em->persist($auditLog);

        $this->em->flush();
    }

    private function assertCanDelete(Commentaire $comment, User $actor): void
    {
        $isAuthor = $comment->getUser()->getId()->equals($actor->getId());
        $isPrivileged = in_array($actor->getRole(), self::PRIVILEGED_ROLES, true);

        if (!$isAuthor && !$isPrivileged) {
            throw new CommentAccessDeniedException(
                'Seul l\'auteur, un modérateur ou un administrateur peut supprimer ce commentaire.'
            );
        }
    }
}
