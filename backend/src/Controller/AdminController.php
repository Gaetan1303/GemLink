<?php

namespace App\Controller;

use App\Entity\JobFineTuning;
use App\Entity\User;
use App\Entity\VersionModeleIa;
use App\Message\RunFineTuningMessage;
use App\Repository\JobFineTuningRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Repository\VersionModeleIaRepository;
use App\Service\AdminDashboardService;
use App\Service\AdminSettingsProvider;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly VersionModeleIaRepository $models,
        private readonly JobFineTuningRepository $jobs,
        private readonly RefreshTokenRepository $refreshTokens,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AdminDashboardService $dashboard,
        private readonly AdminSettingsProvider $adminSettings,
    ) {}

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse { return $this->json($this->dashboard->metrics()); }

    #[Route('/users', methods: ['GET'])]
    public function users(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 25)));
        $criteria = [];
        if (($status = $request->query->get('status')) !== null) $criteria['status'] = strtoupper((string) $status);
        return $this->json([
            'items' => array_map(fn (User $user) => $this->userData($user), $this->users->findBy($criteria, ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit)),
            'page' => $page, 'limit' => $limit, 'total' => $this->users->count($criteria),
        ]);
    }

    #[Route('/users/{id}/role', methods: ['PATCH'])]
    public function changeRole(string $id, Request $request): JsonResponse
    {
        $target = $this->userOr404($id); if ($target instanceof JsonResponse) return $target;
        $payload = $this->payload($request); if ($payload instanceof JsonResponse) return $payload;
        $role = strtoupper((string) ($payload['role'] ?? ''));
        if (!in_array($role, ['USER', 'EXPERT', 'MODERATOR', 'ADMIN'], true)) return $this->json(['message' => 'Rôle invalide.'], 422);
        /** @var User $actor */ $actor = $this->getUser();
        if ($target->getRole() === 'ADMIN' && $role !== 'ADMIN') return $this->json(['message' => 'Un administrateur ne peut pas être rétrogradé par cette interface.'], 422);
        if ($role === 'ADMIN' && $target->getId()->equals($actor->getId())) return $this->json(['message' => 'Un autre administrateur doit attribuer le rôle admin.'], 422);
        $target->setRole($role); $this->em->flush();
        return $this->json($this->userData($target));
    }

    #[Route('/users/{id}/ban', methods: ['PATCH'])]
    public function ban(string $id, Request $request): JsonResponse
    {
        $target = $this->userOr404($id); if ($target instanceof JsonResponse) return $target;
        if ($target->getRole() === 'ADMIN') {
            return $this->json(['message' => 'Un administrateur ne peut pas être banni.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $payload = $this->payload($request); if ($payload instanceof JsonResponse) return $payload;
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') return $this->json(['message' => 'Un motif de bannissement est requis.'], 422);
        $until = null;
        if (isset($payload['until']) && $payload['until'] !== null) try { $until = new DateTimeImmutable((string) $payload['until']); } catch (\Exception) { return $this->json(['message' => 'Date de fin invalide.'], 422); }
        if ($until !== null && $until <= new DateTimeImmutable()) return $this->json(['message' => 'La date de fin doit être future.'], 422);
        $target->ban($reason, $until);
        $this->refreshTokens->revokeAllActiveForUser($target);
        $this->em->flush();
        return $this->json($this->userData($target));
    }

    #[Route('/users/{id}/unban', methods: ['PATCH'])]
    public function unban(string $id): JsonResponse
    {
        $target = $this->userOr404($id); if ($target instanceof JsonResponse) return $target;
        $target->unban(); $this->em->flush(); return $this->json($this->userData($target));
    }

    #[Route('/models/fine-tuning', methods: ['POST'])]
    public function startFineTuning(Request $request): JsonResponse
    {
        $payload = $this->payload($request); if ($payload instanceof JsonResponse) return $payload;
        $name = trim((string) ($payload['versionName'] ?? ''));
        if ($name === '') return $this->json(['message' => 'versionName est requis.'], 422);
        if ($this->models->findOneBy(['name' => $name]) !== null) return $this->json(['message' => 'Ce nom de version existe déjà.'], 409);
        $model = new VersionModeleIa($name, VersionModeleIa::TYPE_VIT);
        $job = new JobFineTuning($model, $this->adminSettings->getDatasetCandidateTrustThreshold());
        $this->em->persist($model); $this->em->persist($job); $this->em->flush();
        $this->bus->dispatch(new RunFineTuningMessage($job->getId()->toRfc4122()));
        return $this->json($this->jobData($job), Response::HTTP_ACCEPTED);
    }

    #[Route('/models/fine-tuning/{id}', methods: ['GET'])]
    public function fineTuningStatus(string $id): JsonResponse
    {
        try { $job = $this->jobs->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $job = null; }
        return $job === null ? $this->json(['message' => 'Job introuvable.'], 404) : $this->json($this->jobData($job));
    }

    #[Route('/models/vit', methods: ['GET'])]
    public function vitVersions(): JsonResponse { return $this->json(array_map(fn (VersionModeleIa $m) => $this->modelData($m), $this->models->findBy(['modelType' => VersionModeleIa::TYPE_VIT], ['createdAt' => 'DESC']))); }

    #[Route('/models/vit/{id}/activate', methods: ['POST'])]
    public function activateVit(string $id): JsonResponse
    {
        try { $model = $this->models->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $model = null; }
        if (!$model instanceof VersionModeleIa || $model->getModelType() !== VersionModeleIa::TYPE_VIT) return $this->json(['message' => 'Version ViT introuvable.'], 404);
        if ($model->getStatus() === VersionModeleIa::STATUS_TRAINING) return $this->json(['message' => 'Une version en entraînement ne peut pas être activée.'], 422);
        foreach ($this->models->findBy(['modelType' => VersionModeleIa::TYPE_VIT, 'status' => VersionModeleIa::STATUS_ACTIVE]) as $active) $active->deprecate();
        $model->activate(); $this->em->flush(); return $this->json($this->modelData($model));
    }

    private function payload(Request $request): array|JsonResponse { $data = json_decode($request->getContent(), true); return is_array($data) ? $data : $this->json(['message' => 'Payload JSON invalide.'], 422); }
    private function userOr404(string $id): User|JsonResponse { try { $user = $this->users->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $user = null; } return $user instanceof User ? $user : $this->json(['message' => 'Utilisateur introuvable.'], 404); }
    private function userData(User $u): array { return ['id' => $u->getId()->toRfc4122(), 'username' => $u->getUsername(), 'email' => $u->getEmail(), 'role' => strtolower($u->getRole()), 'status' => strtolower($u->getStatus()), 'trustScore' => $u->getTrustScore(), 'bannedReason' => $u->getBannedReason(), 'bannedUntil' => $u->getBannedUntil()?->format(DATE_ATOM), 'createdAt' => $u->getCreatedAt()->format(DATE_ATOM)]; }
    private function modelData(VersionModeleIa $m): array { return ['id' => $m->getId()->toRfc4122(), 'name' => $m->getName(), 'status' => strtolower($m->getStatus()), 'accuracy' => $m->getAccuracy(), 'f1Score' => $m->getF1Score()]; }
    private function jobData(JobFineTuning $j): array { return ['id' => $j->getId()->toRfc4122(), 'status' => strtolower($j->getStatus()), 'progress' => $j->getProgress(), 'minTrustScore' => $j->getMinTrustScore(), 'model' => $this->modelData($j->getVersionModele()), 'error' => $j->getErrorMessage()]; }
}
