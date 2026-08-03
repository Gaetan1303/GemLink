<?php

namespace App\Controller;

use App\Service\LeaderboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LeaderboardController extends AbstractController
{
    #[Route('/api/leaderboard', methods: ['GET'])]
    public function index(Request $request, LeaderboardService $leaderboard): JsonResponse
    {
        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));
        return $this->json($leaderboard->ranking($offset, $limit));
    }
}
