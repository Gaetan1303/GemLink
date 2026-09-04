<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Repository\ReportRepository;
use App\Service\ModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/moderation')]
#[IsGranted('ROLE_MODERATOR')]
final class ModerationController extends AbstractController
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly AuditLogRepository $auditLogs,
        private readonly ModerationService $moderation,
    ) {
    }

    #[Route('/reports', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $status = strtoupper((string) $request->query->get('status', 'PENDING'));
        if (!in_array($status, Report::STATUSES, true)) {
            return $this->json(['message' => 'Statut invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reports = $this->reports->findForModeration($status);
        $reasonsByPublication = [];
        $publicationIds = [];

        foreach ($reports as $report) {
            $publicationId = $report->getPublication()->getId()->toRfc4122();
            $publicationIds[$publicationId] = $report->getPublication()->getId();
            $reasonsByPublication[$publicationId][] = $this->serializeReason($report);
        }

        $historyByPublication = [];
        foreach ($this->auditLogs->findModerationHistoryForPublications(array_values($publicationIds)) as $auditLog) {
            $historyByPublication[$auditLog->getTargetId()->toRfc4122()][] = $this->serializeAuditLog($auditLog);
        }

        $items = array_map(function (Report $report) use ($reasonsByPublication, $historyByPublication): array {
            $publicationId = $report->getPublication()->getId()->toRfc4122();

            return $this->serialize(
                $report,
                $reasonsByPublication[$publicationId] ?? [],
                $historyByPublication[$publicationId] ?? [],
            );
        }, $reports);

        return $this->json(['items' => $items]);
    }

    #[Route('/reports/{id}/decision', methods: ['POST'])]
    public function decide(string $id, Request $request): JsonResponse
    {
        try {
            $report = $this->reports->find(Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Signalement invalide.'], Response::HTTP_BAD_REQUEST);
        }
        if (!$report instanceof Report) {
            return $this->json(['message' => 'Signalement introuvable.'], Response::HTTP_NOT_FOUND);
        }
        if ($report->getStatus() !== 'PENDING') {
            return $this->json(['message' => 'Ce signalement a déjà été traité.'], Response::HTTP_CONFLICT);
        }

        $payload = json_decode($request->getContent(), true);
        $decision = is_array($payload) ? strtoupper((string) ($payload['decision'] ?? '')) : '';
        if (!in_array($decision, ['ACCEPTED', 'REJECTED'], true)) {
            return $this->json(['message' => 'Décision invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $reason = is_array($payload) ? ($payload['reason'] ?? null) : null;
        if ($reason !== null && (!is_string($reason) || mb_strlen(trim($reason)) > 1000)) {
            return $this->json(['message' => 'Le motif de modération est limité à 1000 caractères.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var User $moderator */
        $moderator = $this->getUser();
        $this->moderation->decide($report, $moderator, $decision, $reason);

        $history = array_map(
            $this->serializeAuditLog(...),
            $this->auditLogs->findModerationHistoryForPublications([$report->getPublication()->getId()]),
        );

        return $this->json($this->serialize($report, [$this->serializeReason($report)], $history));
    }

    /** @param array<int, array<string, mixed>> $reasonDetails @param array<int, array<string, mixed>> $history */
    private function serialize(Report $report, array $reasonDetails = [], array $history = []): array
    {
        $publication = $report->getPublication();

        return [
            'id' => $report->getId()->toRfc4122(),
            'reasonType' => $report->getReasonType(),
            'description' => $report->getDescription(),
            'status' => $report->getStatus(),
            'createdAt' => $report->getCreatedAt()->format(DATE_ATOM),
            'reportCount' => count($reasonDetails),
            'reasonDetails' => $reasonDetails,
            'moderationHistory' => $history,
            'reporter' => [
                'id' => $report->getUser()->getId()->toRfc4122(),
                'username' => $report->getUser()->getUsername(),
            ],
            'publication' => [
                'id' => $publication->getId()->toRfc4122(),
                'title' => $publication->getTitle(),
                'mediaUrl' => $publication->getMediaUrl(),
                'status' => $publication->getStatus(),
                'deletedAt' => $publication->getDeletedAt()?->format(DATE_ATOM),
                'author' => $publication->getUser()->getUsername(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function serializeReason(Report $report): array
    {
        return [
            'reportId' => $report->getId()->toRfc4122(),
            'reasonType' => $report->getReasonType(),
            'description' => $report->getDescription(),
            'reporter' => [
                'id' => $report->getUser()->getId()->toRfc4122(),
                'username' => $report->getUser()->getUsername(),
            ],
            'createdAt' => $report->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeAuditLog(AuditLog $auditLog): array
    {
        return [
            'id' => $auditLog->getId()->toRfc4122(),
            'moderator' => [
                'id' => $auditLog->getUser()->getId()->toRfc4122(),
                'username' => $auditLog->getUser()->getUsername(),
            ],
            'action' => $auditLog->getAction(),
            'target' => [
                'type' => $auditLog->getTargetType(),
                'id' => $auditLog->getTargetId()->toRfc4122(),
            ],
            'reason' => $auditLog->getReason(),
            'createdAt' => $auditLog->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
