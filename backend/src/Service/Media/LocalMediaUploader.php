<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Exception\InvalidMediaException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Implémentation dev/test :  dans public/uploads et retourne
 * une URL servie par Symfony lui-même. 
 */
final class LocalMediaUploader implements MediaUploaderInterface
{
    public function __construct(
        private readonly string $uploadDir,   // '%kernel.project_dir%/public/uploads'
        private readonly string $publicBaseUrl, // 'http://localhost:8000/uploads'
    ) {
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s.%s', Uuid::v7(), $extension);
        $targetDirectory = rtrim($this->uploadDir, '/') . '/' . trim($directory, '/');

        try {
            $file->move($targetDirectory, $filename);
        } catch (FileException $exception) {
            throw new InvalidMediaException('Impossible d\'enregistrer le fichier média.', previous: $exception);
        }

        return rtrim($this->publicBaseUrl, '/') . '/' . trim($directory, '/') . '/' . $filename;
    }
}
