<?php



namespace App\Tests\Service;

use App\Entity\AuditLog;
use App\Entity\Commentaire;
use App\Entity\Notification;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\CommentAccessDeniedException;
use App\Exception\CommentValidationException;
use App\Service\CommentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * US 2.4 : CommentService porte la validation CA-1, la notification CA-4
 * et les autorisations de suppression + l'audit log CA-2.
 */
final class CommentServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private CommentService $commentService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->commentService = new CommentService($this->em);
    }

    // ── CA-1 : création / validation ────────────────────────────

    public function testCreateCommentRejectsEmptyContent(): void
    {
        $this->em->expects($this->never())->method('persist');

        $this->expectException(CommentValidationException::class);

        $this->commentService->createComment(
            $this->makeUser(),
            new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg'),
            '   ',
        );
    }

    public function testCreateCommentRejectsContentOverOneThousandChars(): void
    {
        $this->em->expects($this->never())->method('persist');

        $this->expectException(CommentValidationException::class);

        $this->commentService->createComment(
            $this->makeUser(),
            new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg'),
            str_repeat('a', 1001),
        );
    }

    public function testCreateCommentAcceptsExactlyOneThousandChars(): void
    {
        $postAuthor = $this->makeUser();
        $publication = new Publication($postAuthor, 'https://media.gem-link.org/x.jpg');
        $commenter = $this->makeUser();

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $comment = $this->commentService->createComment($commenter, $publication, str_repeat('a', 1000));

        $this->assertSame(1000, mb_strlen($comment->getContent()));
        $this->assertSame($commenter, $comment->getUser());
        $this->assertSame($publication, $comment->getPublication());
    }

    public function testCreateCommentTrimsContent(): void
    {
        $postAuthor = $this->makeUser();
        $publication = new Publication($postAuthor, 'https://media.gem-link.org/x.jpg');

        $comment = $this->commentService->createComment(
            $this->makeUser(),
            $publication,
            '  Superbe améthyste !  ',
        );

        $this->assertSame('Superbe améthyste !', $comment->getContent());
    }

    // ── CA-4 : notification de l'auteur du post ──────────────────

    public function testCreateCommentPersistsNotificationForPostAuthor(): void
    {
        $postAuthor = $this->makeUser();
        $publication = new Publication($postAuthor, 'https://media.gem-link.org/x.jpg');
        $commenter = $this->makeUser();

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->commentService->createComment($commenter, $publication, 'Belle pièce.');

        $notifications = array_values(array_filter($persisted, static fn ($e) => $e instanceof Notification));
        $this->assertCount(1, $notifications);
        $this->assertSame(Notification::TYPE_NEW_COMMENT, $notifications[0]->getType());
        $this->assertSame($postAuthor, $notifications[0]->getUser());
        $this->assertSame($publication->getId()->toRfc4122(), $notifications[0]->getTargetId()?->toRfc4122());
    }

    public function testCreateCommentDoesNotNotifySelfComment(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        // L'auteur commente son propre post.
        $this->commentService->createComment($author, $publication, 'Auto-commentaire.');

        $notifications = array_filter($persisted, static fn ($e) => $e instanceof Notification);
        $this->assertCount(0, $notifications);
    }

    // ── CA-2 : suppression / autorisations / audit log ───────────

    public function testDeleteCommentByAuthorSoftDeletesAndLogsAudit(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'À supprimer');

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $this->em->expects($this->once())->method('flush');

        $this->commentService->deleteComment($comment, $author);

        $this->assertTrue($comment->isDeleted());

        $auditLogs = array_values(array_filter($persisted, static fn ($e) => $e instanceof AuditLog));
        $this->assertCount(1, $auditLogs);
        $this->assertSame(AuditLog::ACTION_COMMENT_DELETED, $auditLogs[0]->getAction());
        $this->assertSame(AuditLog::TARGET_TYPE_COMMENTAIRE, $auditLogs[0]->getTargetType());
        $this->assertSame($comment->getId()->toRfc4122(), $auditLogs[0]->getTargetId()->toRfc4122());
    }

    public function testDeleteCommentByModeratorSucceeds(): void
    {
        $author = $this->makeUser();
        $moderator = $this->makeUser('MODERATOR');
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'À supprimer');

        $this->commentService->deleteComment($comment, $moderator);

        $this->assertTrue($comment->isDeleted());
    }

    public function testDeleteCommentByStrangerIsRejected(): void
    {
        $author = $this->makeUser();
        $stranger = $this->makeUser();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Contenu');

        $this->em->expects($this->never())->method('flush');

        $this->expectException(CommentAccessDeniedException::class);

        $this->commentService->deleteComment($comment, $stranger);

        $this->assertFalse($comment->isDeleted());
    }

    private function makeUser(string $role = 'USER'): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole($role);

        return $user;
    }
}
