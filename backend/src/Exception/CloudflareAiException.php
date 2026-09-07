<?php

declare(strict_types=1);

namespace App\Exception;

/** Safe categorical errors: never retain remote bodies, URLs, tokens or transport messages. */
final class CloudflareAiException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus = 0)
    {
        parent::__construct('Cloudflare AI: ' . $reason, $httpStatus);
    }

    public function isRetryable(): bool
    {
        return in_array($this->reason, ['network', 'timeout'], true)
            || ($this->reason === 'http' && in_array($this->httpStatus, [429, 502, 503], true));
    }
}
