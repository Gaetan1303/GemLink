<?php

namespace App\Controller;

use App\Entity\Report;
use App\Entity\User;
use App\Repository\PublicationRepository;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

final class ReportController extends AbstractController
{
    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly ReportRepository $reports,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/api/publications/{id}/reports', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(string $id, Request $request): JsonResponse
    {
        try { $publicationId = Uuid::fromString($id); }
        catch (\InvalidArgumentException) { return $this->json(['message' => 'Publication invalide.'], 400); }
        $publication = $this->publications->findOneActiveById($publicationId);
        if ($publication === null) return $this->json(['message' => 'Publication introuvable.'], 404);

        /** @var User $user */
        $user = $this->getUser();
        if ($publication->getUser()->getId()->equals($user->getId())) {
            return $this->json(['message' => 'Vous ne pouvez pas signaler votre propre publication.'], 422);
        }
        if ($this->reports->findOneBy(['user' => $user, 'publication' => $publication]) !== null) {
            return $this->json(['message' => 'Cette publication a déjà été signalée.'], 409);
        }

        $payload = json_decode($request->getContent(), true);
        $reason = is_array($payload) ? ($payload['reasonType'] ?? '') : '';
        $description = is_array($payload) && is_string($payload['description'] ?? null)
            ? trim($payload['description']) : null;
        if (!is_string($reason) || !in_array($reason, Report::REASONS, true)) {
            return $this->json(['message' => 'Motif de signalement invalide.'], 422);
        }
        if ($description !== null && mb_strlen($description) > 1000) {
            return $this->json(['message' => 'La description est limitée à 1000 caractères.'], 422);
        }

        $report = (new Report($user, $publication))->setReasonType($reason)->setDescription($description);
        $this->em->persist($report);
        $this->em->flush();
        return $this->json(['id' => $report->getId()->toRfc4122(), 'status' => $report->getStatus()], Response::HTTP_CREATED);
    }
}
