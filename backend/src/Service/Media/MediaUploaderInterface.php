<?php

declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * US 2.1 CA-3 : abstraction du transfert du fichier média vers le stockage
 * externe (CDN). Deux implémentations :
 *  - LocalMediaUploader  : dev/test, écrit dans public/uploads.
 *  - R2MediaUploader     : production, Cloudflare R2 (S3-compatible) via Flysystem.
 */
interface MediaUploaderInterface
{
    /**
     * Transfère le fichier et retourne l'URL publique du média stocké.
     *
     * @param string $directory sous-dossier logique (ex. 'publications/2026/07')
     */
    public function upload(UploadedFile $file, string $directory): string;
}
