<?php

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Compatibility adapter kept for older GemLink service configurations.
 *
 * Some rollback/demo revisions still reference this FQCN from Symfony's
 * container configuration. The current demo uses local shared storage, so
 * delegating to LocalMediaUploader preserves the expected behaviour without
 * introducing a second upload implementation.
 */
final class StorageModeMediaUploader implements MediaUploaderInterface
{
    public function __construct(
        private readonly LocalMediaUploader $localUploader,
    ) {
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        return $this->localUploader->upload($file, $directory);
    }
}
