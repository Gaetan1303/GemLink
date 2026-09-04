<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $database,
        private readonly \Redis $redis,
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiServiceUrl,
        private readonly string $internalApiKey,
        private readonly bool $aiEnabled,
    ) {
    }

    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $components = [
            'api' => true,
            'database' => $this->databaseIsReady(),
            'redis' => $this->redisIsReady(),
            'ai' => $this->aiIsReady(),
        ];

        return $this->json([
            'status' => !in_array(false, $components, true) ? 'healthy' : 'degraded',
            'components' => $components,
        ]);
    }

    private function databaseIsReady(): bool
    {
        try {
            return 1 === (int) $this->database->fetchOne('SELECT 1');
        } catch (\Throwable) {
            return false;
        }
    }

    private function redisIsReady(): bool
    {
        try {
            return false !== $this->redis->ping();
        } catch (\Throwable) {
            return false;
        }
    }

    private function aiIsReady(): bool
    {
        if (!$this->aiEnabled) {
            return false;
        }
        try {
            $response = $this->httpClient->request('GET', rtrim($this->aiServiceUrl, '/').'/health', [
                'headers' => ['X-Internal-Key' => $this->internalApiKey],
                'timeout' => 2,
            ]);
            $payload = $response->toArray(false);

            return 200 === $response->getStatusCode() && 'healthy' === ($payload['status'] ?? null);
        } catch (\Throwable) {
            return false;
        }
    }
}
