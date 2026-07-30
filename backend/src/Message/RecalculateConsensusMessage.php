<?php

namespace App\Message;

/**
 * Déclenche le recalcul asynchrone du consensus pondéré d'une publication.
 */
final class RecalculateConsensusMessage
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
