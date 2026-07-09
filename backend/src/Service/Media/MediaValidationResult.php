<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Publication;

/**
 * US 2.1 CA-2 : résultat de la validation d'un fichier média (type réel détecté
 * par magic bytes, indépendamment de l'extension fournie par le client).
 */
final readonly class MediaValidationResult
{
    public function __construct(
        public string $mediaType,   
        public string $mimeType,   
        public int $sizeBytes,
        public ?float $durationSeconds = null,
    ) {
    }

    public function isImage(): bool
    {
        return $this->mediaType === Publication::MEDIA_TYPE_IMAGE;
    }

    public function isVideo(): bool
    {
        return $this->mediaType === Publication::MEDIA_TYPE_VIDEO;
    }
}
