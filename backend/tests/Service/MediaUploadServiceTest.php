<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Publication;
use App\Exception\InvalidMediaException;
use App\Service\Media\MediaUploaderInterface;
use App\Service\Media\MediaUploadService;
use App\Service\Media\MediaValidationResult;
use App\Service\Media\MediaValidatorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * US 2.1 — MediaUploadService orchestre validation (CA-2) + transfert CDN
 * (CA-3), indépendamment de tout post (SRP, voir MediaUploadController).
 */
final class MediaUploadServiceTest extends TestCase
{
    private MediaValidatorService&MockObject $validator;
    private MediaUploaderInterface&MockObject $uploader;
    private MediaUploadService $service;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(MediaValidatorService::class);
        $this->uploader = $this->createMock(MediaUploaderInterface::class);
        $this->service = new MediaUploadService($this->validator, $this->uploader);
    }

    public function testUploadWithoutFileIsRejected(): void
    {
        $this->expectException(InvalidMediaException::class);

        $this->service->upload(null, 'publications/2026/07');
    }

    public function testUploadValidatesThenTransfersAndReturnsMediaReference(): void
    {
        $file = $this->createMock(UploadedFile::class);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($file)
            ->willReturn(new MediaValidationResult(Publication::MEDIA_TYPE_VIDEO, 'video/mp4', 2048, 12.5));

        $this->uploader->expects($this->once())
            ->method('upload')
            ->with($file, 'publications/2026/07')
            ->willReturn('https://media.gem-link.org/publications/2026/07/x.mp4');

        $media = $this->service->upload($file, 'publications/2026/07');

        $this->assertSame('https://media.gem-link.org/publications/2026/07/x.mp4', $media->mediaUrl);
        $this->assertSame(Publication::MEDIA_TYPE_VIDEO, $media->mediaType);
        $this->assertSame(12.5, $media->durationSeconds);
    }

    public function testUploadNeverCallsUploaderWhenValidationFails(): void
    {
        $file = $this->createMock(UploadedFile::class);

        $this->validator->method('validate')->willThrowException(new InvalidMediaException('Type invalide.'));
        $this->uploader->expects($this->never())->method('upload');

        $this->expectException(InvalidMediaException::class);

        $this->service->upload($file, 'publications/2026/07');
    }
}
