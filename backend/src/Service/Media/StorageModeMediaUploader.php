<?php

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Selects local storage in development and Cloudflare R2 in production. */
final class StorageModeMediaUploader implements MediaUploaderInterface
{
    public function __construct(
        private readonly LocalMediaUploader $local,
        private readonly R2MediaUploader $r2,
        private readonly string $storageMode,
    ) {
        if (!in_array($storageMode, ['local', 'r2'], true)) {
            throw new \InvalidArgumentException('MEDIA_STORAGE_MODE must be local or r2.');
        }
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        return match ($this->storageMode) {
            'local' => $this->local->upload($file, $directory),
            'r2' => $this->r2->upload($file, $directory),
        };
    }
}
