<?php



namespace App\Service;

use App\Entity\Publication;
use App\Dto\{AiAnalysisResult, StoneCandidate, NearestReference, StoneAiReviewRequest};
use App\Repository\{PierreRepository, EmbeddingRepository};
use App\Service\Ai\CloudflareAiConfiguration;
use App\Exception\CloudflareAiException;
use Psr\Log\LoggerInterface;
use App\Message\AnalyzeMediaMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * US 2.1 — correspond à la boîte "IAOrchestration : Messenger queue · Retry
 * exponentiel" du diagramme d'architecture. Seul point d'entrée métier pour
 * déclencher une analyse IA : PostService ne connaît pas Messenger, seulement
 * ce service. Le retry exponentiel est configuré au niveau du transport
 * (config/packages/messenger.yaml, retry_strategy) et appliqué automatiquement
 * par Symfony Messenger quand AnalyzeMediaMessageHandler relance une exception ;
 * voir App\EventListener\AnalyzeMediaFailureListener pour la bascule en
 * ANALYSIS_FAILED une fois les tentatives épuisées.
 */
class AiOrchestrationService
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ?SecondaryAiReviewerInterface $secondaryReviewer = null,
        private readonly ?CloudflareAiConfiguration $secondaryConfig = null,
        private readonly ?PierreRepository $pierres = null,
        private readonly ?EmbeddingRepository $embeddings = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /** Called only with the server's FastAPI result and stored media by Messenger handlers. */
    public function reviewAnalysis(AiAnalysisResult $primary, string $image, string $mime, string $requestId, string $requesterKey): AiAnalysisResult
    {
        $config = $this->secondaryConfig;
        if ($config === null || !$config->enabled || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) return $primary;
        $fallback = null;
        $result = $primary;
        try {
            if (!$config->validThresholds()) throw new CloudflareAiException('configuration');
            if ($primary->getConfidence() >= $config->autoAcceptThreshold) return $primary;
            $result = $primary->reviewed(null);
            if ($primary->getConfidence() < $config->reviewThreshold) return $result;
            $config->validate();
            if ($this->secondaryReviewer === null || $this->pierres === null || $this->embeddings === null) throw new CloudflareAiException('configuration');
            $candidates = [];
            $catalogue = [];
            foreach ($primary->getCandidateScores() as $name => $score) {
                $pierre = $this->pierres->findOneByNameIgnoreCase($name);
                if ($pierre === null || $score <= 0) continue;
                $id = $pierre->getId()->toRfc4122();
                if (isset($catalogue[$id])) continue;
                $catalogue[$id] = $pierre;
                $candidates[] = new StoneCandidate($id, $pierre->getName(), $score);
            }
            if ($candidates === []) return $result;
            $references = array_map(static fn (array $row) => new NearestReference($row['name'], $row['similarity']),
                $this->embeddings->findReviewReferences($primary->getEmbedding(), $primary->getClipModelVersion(), $requestId));
            $request = new StoneAiReviewRequest($requestId, $requesterKey, $candidates, $primary->getConfidence(), $references, base64_encode($image), $mime);
            $review = $this->secondaryReviewer->review($request);
            if ($review->decision === 'candidate' && $review->confidence >= $config->reviewThreshold && isset($catalogue[$review->stoneId])) {
                $scores = array_column($candidates, 'score', 'stoneId');
                $result = $primary->reviewed($catalogue[$review->stoneId], min($review->confidence, $scores[$review->stoneId]));
            }
            return $result;
        } catch (\Throwable $error) {
            // The optional reviewer (including its read-only context lookup) cannot
            // cause a Messenger retry or prevent the primary result being handled.
            $fallback = $error instanceof CloudflareAiException ? $error->reason : 'review_unavailable';
            $result = $primary->reviewed(null);
            return $result;
        } finally {
            $this->logger?->info('AI arbitration', ['request_id' => $requestId, 'confidence' => $primary->getConfidence(),
                'decision' => $result->isUnknown() ? 'unknown' : 'candidate', 'fallback' => $fallback]);
        }
    }

    public function requestAnalysis(Publication $publication): void
    {
        $this->messageBus->dispatch(new AnalyzeMediaMessage($publication->getId()->toRfc4122()));
    }
}
