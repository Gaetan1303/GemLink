<?php

declare(strict_types=1);

namespace App\Service\Media;

/**
 * US 2.1 — résultat exposé par MediaUploadController après validation + transfert
 * CDN d'un fichier. Sert de "ticket" que le client renvoie ensuite à
 * PublicationController pour créer le post (découplage upload / création de post).
 */
final readonly class UploadedMedia
{
    public function __construct(
        public string $mediaUrl,
        public string $mediaType,   // Publication::MEDIA_TYPE_IMAGE | MEDIA_TYPE_VIDEO
        public string $mimeType,
        public int $sizeBytes,
        public ?float $durationSeconds = null,
    ) {
    }
}
