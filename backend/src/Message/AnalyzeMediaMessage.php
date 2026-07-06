<?php

declare(strict_types=1);

namespace App\Message;

/**
 * US 2.1 CA-3 : déclenche l'analyse IA (YOLO -> ViT -> CLIP) d'un post
 * nouvellement créé, de manière asynchrone (transport Redis via Messenger).
 */
final class AnalyzeMediaMessage
{
    public function __construct(
        private readonly string $publicationId,
    ) {
    }

    public function getPublicationId(): string
    {
        return $this->publicationId;
    }
}
