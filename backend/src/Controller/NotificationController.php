<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $items = $this->notifications->findRecentForUser($user, $request->query->getInt('limit', 50));

        return $this->json([
            'items' => array_map($this->serialize(...), $items),
            'unreadCount' => $this->notifications->countUnreadForUser($user),
        ]);
    }

    #[Route('/read-all', methods: ['POST'])]
    public function readAll(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        foreach ($this->notifications->findRecentForUser($user, 100) as $notification) {
            $notification->markAsRead();
        }
        $this->em->flush();

        return $this->json(['unreadCount' => 0]);
    }

    #[Route('/{id}/read', methods: ['POST'])]
    public function read(string $id): JsonResponse
    {
        try {
            $notification = $this->notifications->find(Uuid::fromString($id));
        } catch (\InvalidArgumentException) {
            $notification = null;
        }
        /** @var User $user */
        $user = $this->getUser();
        if (!$notification instanceof Notification || !$notification->getUser()->getId()->equals($user->getId())) {
            return $this->json(['message' => 'Notification introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $notification->markAsRead();
        $this->em->flush();

        return $this->json($this->serialize($notification));
    }

    /** @return array<string, mixed> */
    private function serialize(Notification $notification): array
    {
        return [
            'id' => $notification->getId()->toRfc4122(),
            'type' => $notification->getType(),
            'content' => $notification->getContent(),
            'isRead' => $notification->isRead(),
            'targetId' => $notification->getTargetId()?->toRfc4122(),
            'targetType' => $notification->getTargetType(),
            'actor' => $notification->getActor() === null ? null : [
                'id' => $notification->getActor()->getId()->toRfc4122(),
                'username' => $notification->getActor()->getUsername(),
            ],
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
