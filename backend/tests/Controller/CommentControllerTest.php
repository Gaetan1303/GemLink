<?php



namespace App\Tests\Controller;

use App\Entity\Commentaire;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\CommentAccessDeniedException;
use App\Exception\CommentValidationException;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use App\Service\CommentService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.4 — Commentaires MVP.
 * CommentService/CommentaireRepository/PublicationRepository sont mockés
 * dans le container (même pattern que PublicationControllerTest).
 */
final class CommentControllerTest extends WebTestCase
{
    // ── CA-3 : liste (publique) ─────────────────────────────────

    public function testIndexIsPublicAndReturnsItems(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Superbe pièce.');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->expects($this->once())
            ->method('findActiveByPublicationPaginated')
            ->with($publication->getId(), null, 20)
            ->willReturn([$comment]);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);

        // Pas de loginUser() : la lecture doit être accessible à un visiteur anonyme.
        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122() . '/comments');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertSame('Superbe pièce.', $data['items'][0]['content']);
        $this->assertNull($data['nextCursor']);
    }

    public function testIndexSetsNextCursorWhenPageIsFull(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Dernier de la page.');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->method('findActiveByPublicationPaginated')->willReturn([$comment]);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);

        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122() . '/comments?limit=1');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($comment->getId()->toRfc4122(), $data['nextCursor']);
    }

    public function testIndexWithInvalidCursorReturns400(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122() . '/comments?cursor=not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIndexUnknownPostReturns404(): void
    {
        $client = static::createClient();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        $client->request('GET', '/api/publications/' . Uuid::v7()->toRfc4122() . '/comments');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── CA-1 : création (authentifiée) ────────────────────────────

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);
        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/comments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => 'Bonjour'])
        );

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]
        );
    }

    public function testCreateSuccessReturns201WithCommentPayload(): void
    {
        $client = static::createClient();
        $postAuthor = $this->makeUser();
        $publication = new Publication($postAuthor, 'https://media.gem-link.org/x.jpg');
        $commenter = $this->makeUser();
        $comment = new Commentaire($commenter, $publication, 'Superbe pièce !');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $commentServiceMock = $this->createMock(CommentService::class);
        $commentServiceMock->expects($this->once())
            ->method('createComment')
            ->with($commenter, $publication, 'Superbe pièce !')
            ->willReturn($comment);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(CommentService::class, $commentServiceMock);
        $client->loginUser($commenter, 'api');

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/comments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => 'Superbe pièce !'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Superbe pièce !', $data['content']);
        $this->assertSame($commenter->getUsername(), $data['author']['username']);
    }

    public function testCreateWithTooLongContentReturns422(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $commenter = $this->makeUser();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $commentServiceMock = $this->createMock(CommentService::class);
        $commentServiceMock->method('createComment')
            ->willThrowException(new CommentValidationException('Un commentaire ne peut pas dépasser 1000 caractères.'));

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(CommentService::class, $commentServiceMock);
        $client->loginUser($commenter, 'api');

        $client->request(
            'POST',
            '/api/publications/' . $publication->getId()->toRfc4122() . '/comments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => str_repeat('a', 1001)])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateOnUnknownPostReturns404(): void
    {
        $client = static::createClient();
        $commenter = $this->makeUser();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->loginUser($commenter, 'api');

        $client->request(
            'POST',
            '/api/publications/' . Uuid::v7()->toRfc4122() . '/comments',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['content' => 'Bonjour'])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // ── CA-2 : suppression (authentifiée) ─────────────────────────

    public function testDeleteByUnauthorizedUserReturns403(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $stranger = $this->makeUser();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Contenu');

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->method('findOneActiveById')->willReturn($comment);

        $commentServiceMock = $this->createMock(CommentService::class);
        $commentServiceMock->method('deleteComment')
            ->willThrowException(new CommentAccessDeniedException('Non autorisé.'));

        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);
        $client->getContainer()->set(CommentService::class, $commentServiceMock);
        $client->loginUser($stranger, 'api');

        $client->request('DELETE', '/api/comments/' . $comment->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteUnknownCommentReturns404(): void
    {
        $client = static::createClient();
        $user = $this->makeUser();

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);
        $client->loginUser($user, 'api');

        $client->request('DELETE', '/api/comments/' . Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAuthorCanDeleteOwnCommentReturns204(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Contenu');

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->method('findOneActiveById')->willReturn($comment);

        $commentServiceMock = $this->createMock(CommentService::class);
        $commentServiceMock->expects($this->once())->method('deleteComment');

        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);
        $client->getContainer()->set(CommentService::class, $commentServiceMock);
        $client->loginUser($author, 'api');

        $client->request('DELETE', '/api/comments/' . $comment->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    public function testModeratorCanDeleteAnyCommentReturns204(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $moderator = $this->makeUser('MODERATOR');
        $publication = new Publication($this->makeUser(), 'https://media.gem-link.org/x.jpg');
        $comment = new Commentaire($author, $publication, 'Contenu');

        $commentRepoMock = $this->createMock(CommentaireRepository::class);
        $commentRepoMock->method('findOneActiveById')->willReturn($comment);

        $commentServiceMock = $this->createMock(CommentService::class);
        $commentServiceMock->expects($this->once())->method('deleteComment');

        $client->getContainer()->set(CommentaireRepository::class, $commentRepoMock);
        $client->getContainer()->set(CommentService::class, $commentServiceMock);
        $client->loginUser($moderator, 'api');

        $client->request('DELETE', '/api/comments/' . $comment->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
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
