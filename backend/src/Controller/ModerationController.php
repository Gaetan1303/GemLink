<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Entity\Report;
use App\Entity\User;
use App\Repository\ReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/moderation')]
#[IsGranted('ROLE_MODERATOR')]
final class ModerationController extends AbstractController
{
    public function __construct(
        private readonly ReportRepository $reports,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/reports', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $status = strtoupper((string) $request->query->get('status', 'PENDING'));
        if (!in_array($status, Report::STATUSES, true)) return $this->json(['message' => 'Statut invalide.'], 422);
        return $this->json(['items' => array_map($this->serialize(...), $this->reports->findForModeration($status))]);
    }

    #[Route('/reports/{id}/decision', methods: ['POST'])]
    public function decide(string $id, Request $request): JsonResponse
    {
        try { $report = $this->reports->find(Uuid::fromString($id)); }
        catch (\InvalidArgumentException) { return $this->json(['message' => 'Signalement invalide.'], 400); }
        if (!$report instanceof Report) return $this->json(['message' => 'Signalement introuvable.'], 404);
        if ($report->getStatus() !== 'PENDING') return $this->json(['message' => 'Ce signalement a déjà été traité.'], 409);

        $payload = json_decode($request->getContent(), true);
        $decision = is_array($payload) ? strtoupper((string) ($payload['decision'] ?? '')) : '';
        if (!in_array($decision, ['ACCEPTED', 'REJECTED'], true)) {
            return $this->json(['message' => 'Décision invalide.'], 422);
        }

        /** @var User $moderator */
        $moderator = $this->getUser();
        $report->decide($decision);
        if ($decision === 'ACCEPTED') $report->getPublication()->setDeletedAt(new DateTimeImmutable());
        $audit = new AuditLog(
            $moderator,
            $decision === 'ACCEPTED' ? AuditLog::ACTION_REPORT_ACCEPTED : AuditLog::ACTION_REPORT_REJECTED,
            AuditLog::TARGET_TYPE_REPORT,
            $report->getId(),
        );
        $this->em->persist($audit);
        $this->em->flush();
        return $this->json($this->serialize($report));
    }

    private function serialize(Report $report): array
    {
        $publication = $report->getPublication();
        return [
            'id' => $report->getId()->toRfc4122(),
            'reasonType' => $report->getReasonType(),
            'description' => $report->getDescription(),
            'status' => $report->getStatus(),
            'createdAt' => $report->getCreatedAt()->format(DATE_ATOM),
            'reporter' => ['id' => $report->getUser()->getId()->toRfc4122(), 'username' => $report->getUser()->getUsername()],
            'publication' => ['id' => $publication->getId()->toRfc4122(), 'title' => $publication->getTitle(), 'mediaUrl' => $publication->getMediaUrl(), 'author' => $publication->getUser()->getUsername()],
        ];
    }
}
