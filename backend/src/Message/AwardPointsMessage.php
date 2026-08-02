<?php

namespace App\Message;

/** Requests one idempotent asynchronous points award. */
final class AwardPointsMessage
{
    public function __construct(
        private readonly string $userId,
        private readonly string $action,
        private readonly string $sourceId,
    ) {
    }

    public function getUserId(): string { return $this->userId; }
    public function getAction(): string { return $this->action; }
    public function getSourceId(): string { return $this->sourceId; }
}
