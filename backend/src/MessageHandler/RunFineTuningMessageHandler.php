<?php

namespace App\MessageHandler;

use App\Message\RunFineTuningMessage;
use App\Repository\JobFineTuningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Delegates training to the IA service; the persisted job is the polling source of truth. */
#[AsMessageHandler]
final class RunFineTuningMessageHandler
{
    public function __construct(
        private readonly JobFineTuningRepository $jobs,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiServiceUrl,
        private readonly string $internalApiKey,
    ) {}

    public function __invoke(RunFineTuningMessage $message): void
    {
        $job = $this->jobs->find(Uuid::fromString($message->jobId));
        if ($job === null || $job->getStatus() !== 'PENDING') return;

        $job->start()->setProgress(5);
        $this->em->flush();

        try {
            $response = $this->httpClient->request('POST', rtrim($this->aiServiceUrl, '/') . '/fine-tune', [
                'headers' => ['X-Internal-Key' => $this->internalApiKey],
                'json' => [
                    'jobId' => $message->jobId,
                    'modelVersion' => $job->getVersionModele()->getName(),
                    'minTrustScore' => $job->getMinTrustScore(),
                ],
                'timeout' => 3600,
            ]);
            if ($response->getStatusCode() >= 400) throw new \RuntimeException('Le service IA a refusé le fine-tuning.');
            $result = $response->toArray(false);
            $job->setProgress(90);
            $job->getVersionModele()->setMetrics(
                isset($result['accuracy']) ? (float) $result['accuracy'] : null,
                isset($result['f1Score']) ? (float) $result['f1Score'] : (isset($result['f1_score']) ? (float) $result['f1_score'] : null),
            )->deprecate(); // prête au rollback/à l'activation explicite par un admin
            $job->complete();
        } catch (\Throwable $exception) {
            $job->fail($exception->getMessage());
        }
        $this->em->flush();
    }
}
