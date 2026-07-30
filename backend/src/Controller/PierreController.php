<?php

namespace App\Controller;

use App\Repository\PierreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Catalogue minimal utilisé par l'autocomplétion des corrections IA. */
#[Route('/api/pierres')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PierreController extends AbstractController
{
    public function __construct(private readonly PierreRepository $pierres)
    {
    }

    #[Route('/search', name: 'pierre_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));

        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(
            static fn ($pierre) => ['id' => (string) $pierre->getId(), 'nom' => $pierre->getName()],
            $this->pierres->searchByName($query),
        ));
    }
}
