<?php

use Symfony\Component\Dotenv\Dotenv;

if (!class_exists('Redis')) {
    class Redis
    {
        private array $values = [];
        private array $lists = [];
        private array $sorted = [];

        public function get(string $key): string|false { return $this->values[$key] ?? false; }
        public function set(string $key, mixed $value, mixed $options = null): bool { $this->values[$key] = (string) $value; return true; }
        public function setEx(string $key, int $ttl, mixed $value): bool { return $this->set($key, $value); }
        public function del(string ...$keys): int { foreach ($keys as $key) { unset($this->values[$key], $this->lists[$key], $this->sorted[$key]); } return count($keys); }
        public function expire(string $key, int $ttl): bool { return true; }
        public function incr(string $key): int { return $this->values[$key] = (int) ($this->values[$key] ?? 0) + 1; }
        public function lRange(string $key, int $start, int $end): array { return array_slice($this->lists[$key] ?? [], $start, $end < 0 ? null : $end - $start + 1); }
        public function lPush(string $key, mixed ...$values): int { $this->lists[$key] ??= []; array_unshift($this->lists[$key], ...$values); return count($this->lists[$key]); }
        public function rPush(string $key, mixed ...$values): int { $this->lists[$key] ??= []; array_push($this->lists[$key], ...$values); return count($this->lists[$key]); }
        public function lTrim(string $key, int $start, int $end): bool { $this->lists[$key] = $this->lRange($key, $start, $end); return true; }
        public function lRem(string $key, mixed $value, int $count): int { $before = count($this->lists[$key] ?? []); $this->lists[$key] = array_values(array_filter($this->lists[$key] ?? [], static fn ($item) => $item !== $value)); return $before - count($this->lists[$key]); }
        public function zAdd(string $key, int|float $score, string $member): int { $this->sorted[$key][$member] = $score; return 1; }
        public function zRem(string $key, string $member): int { unset($this->sorted[$key][$member]); return 1; }
        public function zCard(string $key): int { return count($this->sorted[$key] ?? []); }
        public function zRevRange(string $key, int $start, int $end, bool $withScores = false): array { $items = $this->sorted[$key] ?? []; arsort($items); $items = array_slice($items, $start, $end - $start + 1, true); return $withScores ? $items : array_keys($items); }
        public function zRevRank(string $key, string $member): int|false { $items = $this->zRevRange($key, 0, -1, true); $rank = array_search($member, array_keys($items), true); return $rank === false ? false : $rank; }
    }
}

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
