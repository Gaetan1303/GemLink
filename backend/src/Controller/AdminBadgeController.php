<?php

namespace App\Controller;

use App\Entity\Badge;
use App\Repository\BadgeRepository;
use App\Repository\PierreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/admin/badges')]
#[IsGranted('ROLE_ADMIN')]
final class AdminBadgeController extends AbstractController
{
    private const CONDITION_TYPES = [Badge::CONDITION_POST_COUNT, Badge::CONDITION_VALIDATION_COUNT, Badge::CONDITION_STONE_IDENTIFICATION_COUNT, Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT];

    public function __construct(private readonly BadgeRepository $badges, private readonly PierreRepository $pierres, private readonly EntityManagerInterface $em) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse { return $this->json(array_map($this->data(...), $this->badges->findBy([], ['createdAt' => 'DESC']))); }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->payload($request); if ($payload instanceof JsonResponse) return $payload;
        $badge = new Badge(is_string($payload['name'] ?? null) ? $payload['name'] : '');
        $error = $this->apply($badge, $payload); if ($error !== null) return $error;
        if ($this->badges->findOneBy(['name' => $badge->getName()]) !== null) return $this->invalid('Ce nom de badge existe déjà.');
        $this->em->persist($badge); $this->em->flush();
        return $this->json($this->data($badge), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $badge = $this->find($id); if ($badge instanceof JsonResponse) return $badge;
        $payload = $this->payload($request); if ($payload instanceof JsonResponse) return $payload;
        $error = $this->apply($badge, $payload); if ($error !== null) return $error;
        $this->em->flush(); return $this->json($this->data($badge));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $badge = $this->find($id); if ($badge instanceof JsonResponse) return $badge;
        $this->em->remove($badge); $this->em->flush(); return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @param array<string,mixed> $payload */
    private function apply(Badge $badge, array $payload): ?JsonResponse
    {
        if (array_key_exists('name', $payload) && is_string($payload['name'])) $badge->setName($payload['name']);
        if (array_key_exists('description', $payload)) { if (!is_string($payload['description']) && $payload['description'] !== null) return $this->invalid('description est invalide.'); $badge->setDescription($payload['description']); }
        $type = $payload['conditionType'] ?? $badge->getConditionType(); $value = $payload['conditionValue'] ?? $badge->getConditionValue();
        if (!is_string($type) || !in_array($type, self::CONDITION_TYPES, true) || !is_int($value) || $value < 1 || $badge->getName() === '' || mb_strlen($badge->getName()) > 100) return $this->invalid('Les conditions du badge sont invalides.');
        $badge->setCondition($type, $value);
        if ($type === Badge::CONDITION_MINERAL_IDENTIFICATION_COUNT) {
            $pierreId = $payload['pierreId'] ?? $badge->getPierre()?->getId()->toRfc4122();
            try { $pierre = is_string($pierreId) ? $this->pierres->find(Uuid::fromString($pierreId)) : null; } catch (\InvalidArgumentException) { $pierre = null; }
            if ($pierre === null) return $this->invalid('Une pierre est requise pour ce badge.');
            $badge->setPierre($pierre);
        } elseif (array_key_exists('pierreId', $payload)) $badge->setPierre(null);
        return null;
    }

    private function find(string $id): Badge|JsonResponse { try { $badge = $this->badges->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $badge = null; } return $badge instanceof Badge ? $badge : $this->json(['message' => 'Badge introuvable.'], 404); }
    /** @return array<string,mixed>|JsonResponse */ private function payload(Request $request): array|JsonResponse { $data = json_decode($request->getContent(), true); return is_array($data) ? $data : $this->invalid('Payload JSON invalide.'); }
    private function invalid(string $message): JsonResponse { return $this->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY); }
    /** @return array<string,mixed> */ private function data(Badge $badge): array { return ['id' => $badge->getId()->toRfc4122(), 'name' => $badge->getName(), 'description' => $badge->getDescription(), 'conditionType' => $badge->getConditionType(), 'conditionValue' => $badge->getConditionValue(), 'pierreId' => $badge->getPierre()?->getId()->toRfc4122()]; }
}
