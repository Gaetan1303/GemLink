<?php

namespace App\Controller;

use App\Entity\Niveau;
use App\Repository\BadgeRepository;
use App\Repository\NiveauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** Admin CRUD for the database-backed points thresholds. */
#[Route('/api/admin/levels')]
#[IsGranted('ROLE_ADMIN')]
final class AdminLevelController extends AbstractController
{
    public function __construct(
        private readonly NiveauRepository $levels,
        private readonly BadgeRepository $badges,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json(array_map($this->data(...), $this->levels->findAllOrdered()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) return $payload;
        if (!isset($payload['number']) || !is_int($payload['number'])) return $this->invalid('number est requis.');
        $level = new Niveau($payload['number'], (string) ($payload['name'] ?? ''), is_int($payload['minPoints'] ?? null) ? $payload['minPoints'] : -1);
        $error = $this->apply($level, $payload);
        if ($error !== null) return $error;
        if ($this->levels->findOneBy(['number' => $level->getNumber()]) !== null || $this->levels->findOneBy(['minPoints' => $level->getMinPoints()]) !== null) return $this->invalid('Le numéro et le seuil de points doivent être uniques.');
        $this->em->persist($level);
        $this->em->flush();
        return $this->json($this->data($level), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $level = $this->find($id);
        if ($level instanceof JsonResponse) return $level;
        $payload = $this->payload($request);
        if ($payload instanceof JsonResponse) return $payload;
        $error = $this->apply($level, $payload);
        if ($error !== null) return $error;
        $duplicate = $this->levels->findOneBy(['minPoints' => $level->getMinPoints()]);
        if ($duplicate !== null && !$duplicate->getId()->equals($level->getId())) return $this->invalid('Le seuil de points doit être unique.');
        $this->em->flush();
        return $this->json($this->data($level));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $level = $this->find($id);
        if ($level instanceof JsonResponse) return $level;
        if (count($this->levels->findAllOrdered()) === 1) return $this->invalid('Au moins un niveau doit rester configuré.');
        $this->em->remove($level);
        $this->em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** @param array<string,mixed> $payload */
    private function apply(Niveau $level, array $payload): ?JsonResponse
    {
        if (array_key_exists('name', $payload)) $level->setName(is_string($payload['name']) ? $payload['name'] : '');
        if (array_key_exists('minPoints', $payload)) {
            if (!is_int($payload['minPoints'])) return $this->invalid('minPoints doit être un entier.');
            $level->setMinPoints($payload['minPoints']);
        }
        if ($level->getNumber() < 1 || $level->getName() === '' || mb_strlen($level->getName()) > 50 || $level->getMinPoints() < 0) return $this->invalid('Les données du niveau sont invalides.');
        if (array_key_exists('badgeId', $payload)) {
            if ($payload['badgeId'] === null) $level->setBadge(null);
            elseif (!is_string($payload['badgeId'])) return $this->invalid('badgeId est invalide.');
            else {
                try { $badge = $this->badges->find(Uuid::fromString($payload['badgeId'])); } catch (\InvalidArgumentException) { $badge = null; }
                if ($badge === null) return $this->invalid('Badge introuvable.');
                $level->setBadge($badge);
            }
        }
        return null;
    }

    private function find(string $id): Niveau|JsonResponse
    {
        try { $level = $this->levels->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $level = null; }
        return $level instanceof Niveau ? $level : $this->json(['message' => 'Niveau introuvable.'], Response::HTTP_NOT_FOUND);
    }

    /** @return array<string,mixed>|JsonResponse */
    private function payload(Request $request): array|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        return is_array($payload) ? $payload : $this->invalid('Payload JSON invalide.');
    }

    private function invalid(string $message): JsonResponse { return $this->json(['message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY); }

    /** @return array<string,mixed> */
    private function data(Niveau $level): array
    {
        return ['id' => $level->getId()->toRfc4122(), 'number' => $level->getNumber(), 'name' => $level->getName(), 'minPoints' => $level->getMinPoints(), 'badgeId' => $level->getBadge()?->getId()->toRfc4122()];
    }
}
