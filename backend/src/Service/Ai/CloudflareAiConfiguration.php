<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Exception\CloudflareAiException;

final readonly class CloudflareAiConfiguration
{
    public function __construct(
        public bool $enabled,
        public string $accountId,
        #[\SensitiveParameter] public string $apiToken,
        public string $model,
        public string $fallbackModel,
        public int $timeout,
        public int $maxRetries,
        public float $autoAcceptThreshold,
        public float $reviewThreshold,
        public int $perUserMinute,
        public int $perUserDay,
        public int $dailyQuota,
    ) {}

    public function validate(): void
    {
        if (!$this->enabled) throw new CloudflareAiException('disabled');
        if (!preg_match('/^[a-f0-9]{32}$/D', $this->accountId) || trim($this->apiToken) === '' || preg_match('/[^\x21-\x7E]/', $this->apiToken)) {
            throw new CloudflareAiException('configuration');
        }
        foreach ([$this->model, $this->fallbackModel] as $index => $model) {
            if ($index === 1 && $model === '') continue;
            if (!preg_match('~^@[a-z0-9-]+/[a-z0-9-]+/[a-zA-Z0-9._-]+$~D', $model)) throw new CloudflareAiException('model_unavailable');
        }
        if ($this->timeout < 1 || $this->timeout > 60 || $this->maxRetries < 0 || $this->maxRetries > 1
            || !$this->validThresholds() || min($this->perUserMinute, $this->perUserDay, $this->dailyQuota) < 1) {
            throw new CloudflareAiException('configuration');
        }
    }

    public function validThresholds(): bool
    {
        return is_finite($this->reviewThreshold) && is_finite($this->autoAcceptThreshold)
            && $this->reviewThreshold >= 0 && $this->reviewThreshold < $this->autoAcceptThreshold && $this->autoAcceptThreshold <= 1;
    }
}
