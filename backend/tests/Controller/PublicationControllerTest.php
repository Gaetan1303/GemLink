<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Exception\PostAccessDeniedException;
use App\Repository\PublicationRepository;
use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.1 — Publication d'un post MVP.
 * US 2.2 — Consultation des posts (liste + détail), accès public.
 * PostService/PublicationRepository sont mockés dans le container pour
 * isoler le contrôleur (même pattern que AuthControllerTest / RgpdControllerTest).
 */
final class PublicationControllerTest extends WebTestCase
{
    // ── US 2.2 : liste (publique) ───────────────────────────────

    public function testIndexIsPublicAndReturnsPaginatedItems(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();

        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $publication->setTitle('Améthyste');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->expects($this->once())
            ->method('findActivePaginated')
            ->with(1, 20)
            ->willReturn([$publication]);
        $publicationRepoMock->method('countActive')->willReturn(1);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        // Pas de loginUser() : la liste doit être accessible à un visiteur anonyme.
        $client->request('GET', '/api/publications');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertSame('Améthyste', $data['items'][0]['title']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(1, $data['total']);
    }

    public function testIndexClampsLimitToFiftyMax(): void
    {
        $client = static::createClient();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->expects($this->once())
            ->method('findActivePaginated')
            ->with(1, 50)
            ->willReturn([]);
        $publicationRepoMock->method('countActive')->willReturn(0);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        $client->request('GET', '/api/publications?limit=500');

        $this->assertResponseIsSuccessful();
    }

    // ── US 2.2 : détail (public) ─────────────────────────────────

    public function testShowIsPublicAndIncrementsViewCount(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $postServiceMock = $this->createMock(PostService::class);
        $postServiceMock->expects($this->once())->method('recordView')->with($publication);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(PostService::class, $postServiceMock);

        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame($publication->getId()->toRfc4122(), $data['id']);
        $this->assertSame($author->getUsername(), $data['author']['username']);
    }

    public function testShowUnknownPostReturns404(): void
    {
        $client = static::createClient();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);

        $client->request('GET', '/api/publications/' . Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowWithInvalidIdReturns400(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/publications/not-a-uuid');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    // ── US 2.1 : création (authentifiée) ─────────────────────────

    public function testCreateRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/publications', [], [], [], null);

        $this->assertContains(
            $client->getResponse()->getStatusCode(),
            [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN, Response::HTTP_FOUND]
        );
    }

    public function testCreateSuccessReturns201WithPostPayload(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();

        $publication = new Publication($author, 'https://media.gem-link.org/publications/2026/07/x.jpg');
        $publication->setTitle('Améthyste');

        $postServiceMock = $this->createMock(PostService::class);
        $postServiceMock->expects($this->once())
            ->method('createPost')
            ->willReturn($publication);

        $client->getContainer()->set(PostService::class, $postServiceMock);
        $client->loginUser($author, 'api');

        $tmpFile = tempnam(sys_get_temp_dir(), 'gemlink_upload_');
        file_put_contents($tmpFile, "\xFF\xD8\xFF\xE0fake-jpeg-content");
        $uploadedFile = new UploadedFile($tmpFile, 'pierre.jpg', 'image/jpeg', null, true);

        $client->request(
            'POST',
            '/api/publications',
            ['title' => 'Améthyste', 'tags' => 'violet,quartz'],
            ['media' => $uploadedFile],
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Améthyste', $data['title']);
        $this->assertSame(Publication::STATUS_PENDING_ANALYSIS, $data['status']);
        $this->assertSame($author->getUsername(), $data['author']['username']);

        @unlink($tmpFile);
    }

    public function testCreateWithInvalidMediaReturns422(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();

        $postServiceMock = $this->createMock(PostService::class);
        $postServiceMock->method('createPost')
            ->willThrowException(new InvalidMediaException('Type de fichier non supporté.'));

        $client->getContainer()->set(PostService::class, $postServiceMock);
        $client->loginUser($author, 'api');

        $client->request('POST', '/api/publications', [], [], [], null);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ── US 2.1 : suppression (authentifiée, CA-4) ─────────────────

    public function testDeleteByUnauthorizedUserReturns403(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $stranger = $this->makeUser();

        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $postServiceMock = $this->createMock(PostService::class);
        $postServiceMock->method('softDelete')
            ->willThrowException(new PostAccessDeniedException('Non autorisé.'));

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(PostService::class, $postServiceMock);
        $client->loginUser($stranger, 'api');

        $client->request('DELETE', '/api/publications/' . $publication->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteUnknownPostReturns404(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn(null);

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->loginUser($author, 'api');

        $client->request('DELETE', '/api/publications/' . Uuid::v7()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAuthorCanDeleteOwnPostReturns204(): void
    {
        $client = static::createClient();
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $publicationRepoMock = $this->createMock(PublicationRepository::class);
        $publicationRepoMock->method('findOneActiveById')->willReturn($publication);

        $postServiceMock = $this->createMock(PostService::class);
        $postServiceMock->expects($this->once())->method('softDelete');

        $client->getContainer()->set(PublicationRepository::class, $publicationRepoMock);
        $client->getContainer()->set(PostService::class, $postServiceMock);
        $client->loginUser($author, 'api');

        $client->request('DELETE', '/api/publications/' . $publication->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('gemuser_' . uniqid());
        $user->setEmail(uniqid() . '@example.com');
        $user->setPasswordHash('hashed');
        $user->setRole('USER');

        return $user;
    }
}
