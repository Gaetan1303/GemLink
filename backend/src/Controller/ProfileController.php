<?php

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Repository\PublicationRepository;
use App\Repository\UserRepository;
use App\Repository\PointTransactionRepository;
use App\Service\ProfileService;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/profiles')]
final class ProfileController extends AbstractController
{
    public function __construct(private readonly UserRepository $users, private readonly PublicationRepository $publications, private readonly ProfileService $profiles, private readonly PointTransactionRepository $pointTransactions) {}

    #[Route('/{id}/points', name: 'profile_points', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function points(string $id): JsonResponse
    {
        $user = $this->findOrFail($id);
        if ($user instanceof JsonResponse) return $user;
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->getId()->equals($user->getId())) {
            return $this->json(['message' => 'Vous ne pouvez consulter que votre propre historique de points.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'total' => $user->getPoints(),
            'transactions' => array_map(static fn ($transaction) => [
                'action' => $transaction->getAction(),
                'amount' => $transaction->getAmount(),
                'date' => $transaction->getCreatedAt()->format(DATE_ATOM),
            ], $this->pointTransactions->findHistoryForUser($user)),
        ]);
    }

    #[Route('/{id}', name: 'profile_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->findOrFail($id);
        return $user instanceof JsonResponse ? $user : $this->json($this->serialize($user));
    }

    #[Route('/{id}', name: 'profile_update', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(string $id, Request $request): JsonResponse
    {
        $user = $this->findOrFail($id);
        if ($user instanceof JsonResponse) return $user;
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->getId()->equals($user->getId())) return $this->json(['message' => 'Vous ne pouvez modifier que votre propre profil.'], Response::HTTP_FORBIDDEN);
        $data = $request->request->all();
        try { $user = $this->profiles->update($user, $data, $request->files->get('avatar')); }
        catch (InvalidArgumentException|InvalidMediaException $e) { return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY); }
        return $this->json($this->serialize($user));
    }

    private function findOrFail(string $id): User|JsonResponse
    {
        try { $user = $this->users->find(Uuid::fromString($id)); } catch (InvalidArgumentException) { return $this->json(['message' => 'Identifiant de profil invalide.'], 400); }
        return $user ?? $this->json(['message' => 'Profil introuvable.'], 404);
    }

    /** @return array<string,mixed> */
    private function serialize(User $user): array
    {
        return ['id' => $user->getId()->toRfc4122(), 'username' => $user->getUsername(), 'avatarUrl' => $user->getAvatarUrl(), 'bio' => $user->getBio(), 'level' => $user->getLevel(),
            'badges' => array_map(static fn ($badge) => ['id' => $badge->getId()->toRfc4122(), 'name' => $badge->getName(), 'description' => $badge->getDescription()], $user->getBadges()->toArray()),
            'interestTags' => array_map(static fn ($tag) => $tag->getName(), $user->getInterestTags()->toArray()),
            'posts' => array_map(fn (Publication $p) => ['id' => $p->getId()->toRfc4122(), 'title' => $p->getTitle(), 'description' => $p->getDescription(), 'mediaUrl' => $p->getMediaUrl(), 'mediaType' => $p->getMediaType(), 'createdAt' => $p->getCreatedAt()->format(DATE_ATOM)], $this->publications->findActiveByUser($user))];
    }
}
