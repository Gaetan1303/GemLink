<?php

namespace App\Tests\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Repository\PublicationLikeRepository;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Service\FeedCacheService;
use App\Service\LikeService;
use App\Service\PostService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/** Contrat HTTP des publications, isolé de Redis et PostgreSQL. */
#[AllowMockObjectsWithoutExpectations]
final class PublicationControllerTest extends WebTestCase
{
    public function testIndexIsPublicAndIncludesLikeState(): void
    {
        [$client, $posts, , $likes, $feed] = $this->clientWithDependencies();
        $publication = new Publication($this->user(), 'https://media.gem-link.org/x.jpg');
        $posts->method('findActiveByIds')->willReturn([$publication]);
        $feed->method('recentIds')->willReturn([$publication->getId()->toRfc4122()]);
        $likes->method('countForPublication')->willReturn(3);
        $likes->method('findOneFor')->willReturn(null);

        $client->request('GET', '/api/publications');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(3, $data['items'][0]['likeCount']);
        self::assertFalse($data['items'][0]['likedByCurrentUser']);
    }

    public function testShowIsPublicAndRecordsView(): void
    {
        [$client, $posts, $postService, $likes] = $this->clientWithDependencies();
        $publication = new Publication($this->user(), 'https://media.gem-link.org/x.jpg');
        $posts->method('findOneActiveById')->willReturn($publication);
        $postService->expects($this->once())->method('recordView')->with($publication);
        $likes->method('countForPublication')->willReturn(0);

        $client->request('GET', '/api/publications/' . $publication->getId()->toRfc4122());
        self::assertResponseIsSuccessful();
    }

    public function testShowUnknownPostReturns404(): void
    {
        [$client, $posts] = $this->clientWithDependencies();
        $posts->method('findOneActiveById')->willReturn(null);
        $client->request('GET', '/api/publications/' . Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCreateRequiresAuthentication(): void
    {
        [$client] = $this->clientWithDependencies();
        $client->request('POST', '/api/publications');
        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
    }

    public function testCreateReturns201(): void
    {
        [$client, , $postService, $likes] = $this->clientWithDependencies();
        $author = $this->user();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $postService->method('createPost')->willReturn($publication);
        $likes->method('countForPublication')->willReturn(0);
        $client->loginUser($author, 'api');

        $client->request('POST', '/api/publications', ['title' => 'Améthyste']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    public function testCreateWithInvalidMediaReturns422(): void
    {
        [$client, , $postService] = $this->clientWithDependencies();
        $postService->method('createPost')->willThrowException(new InvalidMediaException('Type non supporté.'));
        $client->loginUser($this->user(), 'api');
        $client->request('POST', '/api/publications');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLikeToggleRequiresAuthentication(): void
    {
        [$client] = $this->clientWithDependencies();
        $client->request('POST', '/api/publications/' . Uuid::v7()->toRfc4122() . '/like');
        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_UNAUTHORIZED, Response::HTTP_FORBIDDEN]);
    }

    public function testLikeToggleReturnsCurrentState(): void
    {
        [$client, $posts, , , , $likeService] = $this->clientWithDependencies();
        $author = $this->user();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');
        $posts->method('findOneActiveById')->willReturn($publication);
        $likeService->expects($this->once())->method('toggle')->willReturn(['liked' => true, 'likeCount' => 1]);
        $client->loginUser($this->user(), 'api');

        $client->request('POST', '/api/publications/' . $publication->getId()->toRfc4122() . '/like');
        self::assertResponseIsSuccessful();
        self::assertSame(['liked' => true, 'likeCount' => 1], json_decode((string) $client->getResponse()->getContent(), true));
    }

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: \PHPUnit\Framework\MockObject\MockObject, 2: \PHPUnit\Framework\MockObject\MockObject, 3: \PHPUnit\Framework\MockObject\MockObject, 4: \PHPUnit\Framework\MockObject\MockObject, 5: \PHPUnit\Framework\MockObject\MockObject} */
    private function clientWithDependencies(): array
    {
        $client = static::createClient();
        $container = static::getContainer();
        $posts = $this->createStub(PublicationRepository::class);
        $postService = $this->createMock(PostService::class);
        $likes = $this->createStub(PublicationLikeRepository::class);
        $feed = $this->createStub(FeedCacheService::class);
        $likeService = $this->createMock(LikeService::class);
        $pierres = $this->createStub(PublicationPierreRepository::class);
        $pierres->method('findBestMatch')->willReturn(null);
        $container->set(PublicationRepository::class, $posts);
        $container->set(PostService::class, $postService);
        $container->set(PublicationLikeRepository::class, $likes);
        $container->set(FeedCacheService::class, $feed);
        $container->set(LikeService::class, $likeService);
        $container->set(PublicationPierreRepository::class, $pierres);
        return [$client, $posts, $postService, $likes, $feed, $likeService];
    }

    private function user(): User
    {
        $user = new User();
        $user->setUsername('u' . uniqid())->setEmail(uniqid() . '@example.com')->setPasswordHash('hash')->setRole('USER');
        return $user;
    }
}
