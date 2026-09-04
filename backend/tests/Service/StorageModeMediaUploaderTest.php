<?php

namespace App\Tests\Service;

use App\Service\Media\LocalMediaUploader;
use App\Service\Media\R2MediaUploader;
use App\Service\Media\StorageModeMediaUploader;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class StorageModeMediaUploaderTest extends TestCase
{
    public function testLocalModeWritesToTheLocalUploader(): void
    {
        $root = sys_get_temp_dir().'/gemlink-media-'.bin2hex(random_bytes(4));
        mkdir($root);
        $source = tempnam(sys_get_temp_dir(), 'gemlink-upload-');
        file_put_contents($source, 'image');
        $filesystem = $this->createMock(FilesystemOperator::class);
        $uploader = new StorageModeMediaUploader(
            new LocalMediaUploader($root, 'http://localhost/uploads'),
            new R2MediaUploader($filesystem, 'https://media.example'),
            'local',
        );

        $url = $uploader->upload(new UploadedFile($source, 'stone.jpg', 'image/jpeg', null, true), 'posts');

        self::assertStringStartsWith('http://localhost/uploads/posts/', $url);
        self::assertFileExists($root.'/posts/'.basename($url));
    }

    public function testR2ModeWritesThroughFlysystemWithoutObjectAcl(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'gemlink-upload-');
        file_put_contents($source, 'image');
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->expects(self::once())
            ->method('writeStream')
            ->with(
                self::matchesRegularExpression('#^posts/.+\.[a-z0-9]+$#'),
                self::callback('is_resource'),
                self::callback(fn (array $options): bool => !isset($options['visibility']) && isset($options['mimetype'])),
            );
        $uploader = new StorageModeMediaUploader(
            new LocalMediaUploader('/unused', 'http://localhost/uploads'),
            new R2MediaUploader($filesystem, 'https://media.gem-link.org'),
            'r2',
        );

        $url = $uploader->upload(new UploadedFile($source, 'stone.jpg', 'image/jpeg', null, true), 'posts');

        self::assertStringStartsWith('https://media.gem-link.org/posts/', $url);
    }

    public function testUnknownModeFailsExplicitly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StorageModeMediaUploader(
            new LocalMediaUploader('/unused', 'http://localhost/uploads'),
            new R2MediaUploader($this->createStub(FilesystemOperator::class), 'https://media.example'),
            'other',
        );
    }
}
