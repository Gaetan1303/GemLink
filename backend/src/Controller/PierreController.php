<?php

namespace App\Controller;

use App\Repository\PierreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/** Catalogue minimal utilisé par l'autocomplétion des corrections IA. */
#[Route('/api/pierres')]
final class PierreController extends AbstractController
{
    public function __construct(private readonly PierreRepository $pierres)
    {
    }

    #[Route('/search', name: 'pierre_search', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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

    #[Route('/{id}', name: 'pierre_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try { $pierre = $this->pierres->find(Uuid::fromString($id)); } catch (\InvalidArgumentException) { $pierre = null; }
        if ($pierre === null) return $this->json(['message' => 'Pierre introuvable.'], 404);

        return $this->json([
            'id' => $pierre->getId()->toRfc4122(), 'name' => $pierre->getName(), 'category' => $pierre->getCategory(),
            'hardness' => $pierre->getHardness(), 'crystalSystem' => $pierre->getCrystalSystem(),
            'composition' => $pierre->getComposition(), 'description' => $pierre->getDescription(),
        ]);
    }
}
