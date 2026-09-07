<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Exception\CloudflareAiException;

/** Existing Redis connection; atomic budgets across API and Messenger workers. */
class SecondaryAiUsageLimiter
{
    public function __construct(#[\Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure('Redis')] private readonly \Redis|\Closure $redis, private readonly CloudflareAiConfiguration $config) {}

    public function consume(string $requesterKey, string $requestId): void
    {
        $now = time();
        $prefix = 'gemlink:{secondary-ai}:';
        $user = hash('sha256', $requesterKey);
        $keys = [$prefix . 'day:' . gmdate('Y-m-d', $now), $prefix . 'user-day:' . $user . ':' . gmdate('Y-m-d', $now),
            $prefix . 'user-minute:' . $user . ':' . intdiv($now, 60), $prefix . 'request:' . $requestId];
        $script = <<<'LUA'
            for i = 1, 4 do
                if tonumber(redis.call('GET', KEYS[i]) or '0') >= tonumber(ARGV[i]) then return 0 end
            end
            for i = 1, 4 do
                if redis.call('INCR', KEYS[i]) == 1 then redis.call('EXPIRE', KEYS[i], ARGV[4 + i]) end
            end
            return 1
            LUA;
        try {
            $redis = $this->redis instanceof \Closure ? ($this->redis)() : $this->redis;
            $accepted = $redis->eval($script, [...$keys, $this->config->dailyQuota, $this->config->perUserDay,
                $this->config->perUserMinute, 1 + $this->config->maxRetries, 86400, 86400, 120, 172800], 4);
        } catch (\Throwable) { throw new CloudflareAiException('quota_unavailable'); }
        if ($accepted !== 1) throw new CloudflareAiException('quota_exceeded');
    }
}
