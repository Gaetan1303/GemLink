<?php

namespace App\MessageHandler;

use App\Message\RunFineTuningMessage;
use App\Repository\JobFineTuningRepository;
use App\Repository\ValidationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class RunFineTuningMessageHandler
{
    public function __construct(
        private readonly JobFineTuningRepository $jobs,
        private readonly ValidationRepository $validations,
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
            $candidates = $this->exportCandidates($job->getMinTrustScore());
            if (count($candidates) < 4) {
                throw new \RuntimeException('Pas assez de validations fiables pour entraîner le modèle.');
            }

            $response = $this->httpClient->request('POST', rtrim($this->aiServiceUrl, '/') . '/fine-tune', [
                'headers' => ['X-Internal-Key' => $this->internalApiKey],
                'json' => [
                    'job_id' => $message->jobId,
                    'model_version' => $job->getVersionModele()->getName(),
                    'candidates' => $candidates,
                ],
                'timeout' => 30,
            ]);
            if ($response->getStatusCode() >= 400) {
                throw new \RuntimeException('Le service IA a refusé le fine-tuning.');
            }

            $result = $this->waitForCompletion($message->jobId, $job);
            $job->getVersionModele()->setMetrics(
                isset($result['accuracy']) ? (float) $result['accuracy'] : null,
                isset($result['f1Score']) ? (float) $result['f1Score'] : null,
            )->deprecate();
            $job->complete();
        } catch (\Throwable $exception) {
            $job->fail($exception->getMessage());
        }
        $this->em->flush();
    }

    private function exportCandidates(int $threshold): array
    {
        $candidates = [];
        foreach ($this->validations->findDatasetCandidates($threshold) as $validation) {
            if ($validation->getAction() === 'REJECT') continue;
            $label = $validation->getAction() === 'CORRECT'
                ? $validation->getProposedLabel()
                : $validation->getPierre()->getName();
            if ($label === null || trim($label) === '') continue;
            $candidates[] = [
                'media_url' => $validation->getPublication()->getMediaUrl(),
                'label' => trim($label),
            ];
        }
        return $candidates;
    }

    private function waitForCompletion(string $jobId, object $job): array
    {
        for ($attempt = 0; $attempt < 720; ++$attempt) {
            sleep(5);
            $response = $this->httpClient->request(
                'GET',
                rtrim($this->aiServiceUrl, '/') . '/fine-tune/' . rawurlencode($jobId),
                ['headers' => ['X-Internal-Key' => $this->internalApiKey], 'timeout' => 30],
            );
            $result = $response->toArray(false);
            $job->setProgress((int) ($result['progress'] ?? $job->getProgress()));
            $this->em->flush();
            if (($result['status'] ?? null) === 'FAILED') {
                throw new \RuntimeException((string) ($result['error'] ?? 'Le fine-tuning a échoué.'));
            }
            if (($result['status'] ?? null) === 'COMPLETED') return $result;
        }
        throw new \RuntimeException('Le fine-tuning a dépassé le délai maximal.');
    }
}
