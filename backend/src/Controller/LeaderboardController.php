<?php

namespace App\Controller;

use App\Service\LeaderboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    #[Route('/api/leaderboard', methods: ['GET'])]
    public function index(LeaderboardService $leaderboard): JsonResponse
    {
        $user = $this->getUser();
        return $this->json($leaderboard->ranking($user instanceof \App\Entity\User ? $user : null));
    }
}
