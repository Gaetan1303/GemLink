<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Publication;
use App\Entity\User;
use App\Entity\Tag;
use App\Exception\InvalidMediaException;
use App\Exception\PostAccessDeniedException;
use App\Exception\PostValidationException;
use App\Repository\TagRepository;
use App\Service\AiOrchestrationService;
use App\Service\Media\MediaUploadService;
use App\Service\Media\UploadedMedia;
use App\Service\PostService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * US 2.1 : PostService orchestre MediaUploadService (CA-1/CA-2/CA-3, upload)
 * et AiOrchestrationService (CA-3, déclenchement IA), et porte lui-même les
 * autorisations de suppression (CA-4).
 */
final class PostServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TagRepository&MockObject $tags;
    private MediaUploadService&MockObject $mediaUploadService;
    private AiOrchestrationService&MockObject $aiOrchestration;
    private PostService $postService;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->tags = $this->createMock(TagRepository::class);
        $this->mediaUploadService = $this->createMock(MediaUploadService::class);
        $this->aiOrchestration = $this->createMock(AiOrchestrationService::class);

        $this->postService = new PostService(
            $this->em,
            $this->tags,
            $this->mediaUploadService,
            $this->aiOrchestration,
        );
    }

    public function testCreatePostPropagatesMediaUploadFailure(): void
    {
        $this->mediaUploadService->method('upload')
            ->willThrowException(new InvalidMediaException('Fichier manquant.'));

        $this->em->expects($this->never())->method('persist');
        $this->aiOrchestration->expects($this->never())->method('requestAnalysis');

        $this->expectException(InvalidMediaException::class);

        $this->postService->createPost($this->makeUser(), null, 'Titre', null, []);
    }

    public function testCreatePostPersistsPublicationAndRequestsAnalysis(): void
    {
        $author = $this->makeUser();
        $file = $this->createMock(UploadedFile::class);

        $this->mediaUploadService->expects($this->once())
            ->method('upload')
            ->with($file, $this->stringContains('publications/'))
            ->willReturn(new UploadedMedia(
                'https://media.gem-link.org/publications/2026/07/abc.jpg',
                Publication::MEDIA_TYPE_IMAGE,
                'image/jpeg',
                1024,
            ));

        $this->tags->method('findOneByName')->willReturn(null);

       $this->em->expects($this->atLeastOnce())
    ->method('persist')
    ->with($this->callback(function ($object) {
        return $object instanceof Publication || $object instanceof Tag;
    }));
        $this->em->expects($this->once())->method('flush');

        $this->aiOrchestration->expects($this->once())
            ->method('requestAnalysis')
            ->with($this->isInstanceOf(Publication::class));

        $publication = $this->postService->createPost($author, $file, 'Améthyste', 'Trouvée en Bretagne', ['violet', 'quartz']);

        $this->assertSame('https://media.gem-link.org/publications/2026/07/abc.jpg', $publication->getMediaUrl());
        $this->assertSame('Améthyste', $publication->getTitle());
        $this->assertSame(Publication::STATUS_PENDING_ANALYSIS, $publication->getStatus());
        $this->assertCount(2, $publication->getTags());
    }

    public function testTooManyTagsAreRejected(): void
    {
        $author = $this->makeUser();
        $file = $this->createMock(UploadedFile::class);

        $this->mediaUploadService->method('upload')
            ->willReturn(new UploadedMedia('https://media.gem-link.org/x.jpg', Publication::MEDIA_TYPE_IMAGE, 'image/jpeg', 1024));
        $this->tags->method('findOneByName')->willReturn(null);

        $tooManyTags = array_map(static fn (int $i) => "tag{$i}", range(1, 11));

        $this->expectException(PostValidationException::class);

        $this->postService->createPost($author, $file, null, null, $tooManyTags);
    }

    public function testAuthorCanSoftDeleteTheirOwnPost(): void
    {
        $author = $this->makeUser('USER');
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->em->expects($this->once())->method('flush');

        $this->postService->softDelete($publication, $author);

        $this->assertTrue($publication->isDeleted());
    }

    public function testModeratorCanSoftDeleteAnotherUsersPost(): void
    {
        $author = $this->makeUser('USER');
        $moderator = $this->makeUser('MODERATOR');
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->em->expects($this->once())->method('flush');

        $this->postService->softDelete($publication, $moderator);

        $this->assertTrue($publication->isDeleted());
    }

    public function testUnrelatedUserCannotDeletePost(): void
    {
        $author = $this->makeUser('USER');
        $strangerUser = $this->makeUser('USER');
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->em->expects($this->never())->method('flush');

        $this->expectException(PostAccessDeniedException::class);

        $this->postService->softDelete($publication, $strangerUser);
    }

    public function testRecordViewIncrementsViewCountAndFlushes(): void
    {
        $author = $this->makeUser();
        $publication = new Publication($author, 'https://media.gem-link.org/x.jpg');

        $this->em->expects($this->once())->method('flush');

        $this->postService->recordView($publication);

        $this->assertSame(1, $publication->getViewCount());
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
