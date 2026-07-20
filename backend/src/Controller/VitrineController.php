<?php



namespace App\Controller;

use App\Entity\User;
use App\Entity\Vitrine;
use App\Entity\VitrineMedia;
use App\Entity\VitrinePublication;
use App\Exception\InvalidMediaException;
use App\Exception\VitrineAccessDeniedException;
use App\Exception\VitrineEmptyException;
use App\Exception\VitrineValidationException;
use App\Repository\PublicationRepository;
use App\Repository\VitrineRepository;
use App\Service\VitrineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.1 — Création et gestion d'une Vitrine : posts liés ET médias
 * uploadés directement (CA-2). La consultation d'une Vitrine PUBLIÉE est
 * publique ; un brouillon n'est visible que par son propriétaire.
 */
#[Route('/api/vitrines')]
final class VitrineController extends AbstractController
{
    public function __construct(
        private readonly VitrineService $vitrineService,
        private readonly VitrineRepository $vitrines,
        private readonly PublicationRepository $publications,
    ) {
    }

    #[Route('', name: 'vitrine_index', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrines = $this->vitrines->findByUser($user);

        return $this->json([
            'items' => array_map($this->serializeVitrine(...), $vitrines),
        ]);
    }

    #[Route('/{id}', name: 'vitrine_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $viewer = $this->getUser();
        $isOwner = $viewer instanceof User && $vitrine->getUser()->getId()->equals($viewer->getId());

        if (!$vitrine->isPublished() && !$isOwner) {
            return $this->json(['message' => 'Vitrine introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$isOwner) {
            $this->vitrineService->recordView($vitrine);
        }

        return $this->json($this->serializeVitrine($vitrine));
    }

    #[Route('', name: 'vitrine_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $vitrine = $this->vitrineService->createVitrine(
                $user,
                is_string($data['title'] ?? null) ? $data['title'] : null,
                is_string($data['description'] ?? null) ? $data['description'] : null,
            );
        } catch (VitrineValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeVitrine($vitrine), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'vitrine_update', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $vitrine = $this->vitrineService->updateVitrine(
                $vitrine,
                $user,
                array_key_exists('title', $data) && is_string($data['title']) ? $data['title'] : null,
                array_key_exists('description', $data) ? (is_string($data['description']) ? $data['description'] : '') : null,
            );
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (VitrineValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeVitrine($vitrine));
    }

    #[Route('/{id}', name: 'vitrine_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        try {
            $this->vitrineService->deleteVitrine($vitrine, $user);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/items', name: 'vitrine_item_add', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addItem(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $publicationId = $data['publicationId'] ?? null;

        if (!is_string($publicationId) || $publicationId === '') {
            return $this->json(['message' => 'publicationId est requis.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $postId = Uuid::fromString($publicationId);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de post invalide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $publication = $this->publications->findOneActiveById($postId);
        if ($publication === null) {
            return $this->json(['message' => 'Post introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $item = $this->vitrineService->addItem($vitrine, $user, $publication);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (VitrineValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializePostItem($item), Response::HTTP_CREATED);
    }

    #[Route('/{id}/items/{publicationId}', name: 'vitrine_item_remove', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeItem(string $id, string $publicationId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $item = null;
        foreach ($vitrine->getItems() as $candidate) {
            if ($candidate->getPublication()->getId()->toRfc4122() === $publicationId) {
                $item = $candidate;
                break;
            }
        }

        if ($item === null) {
            return $this->json(['message' => 'Ce post ne fait pas partie de cette Vitrine.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->vitrineService->removeItem($vitrine, $user, $item);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/media', name: 'vitrine_media_add', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function addMedia(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $file = $request->files->get('media');

        try {
            $media = $this->vitrineService->addMedia($vitrine, $user, $file);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (InvalidMediaException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeMediaItem($media), Response::HTTP_CREATED);
    }

    #[Route('/{id}/media/{mediaId}', name: 'vitrine_media_remove', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function removeMedia(string $id, string $mediaId): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $media = null;
        foreach ($vitrine->getMediaItems() as $candidate) {
            if ($candidate->getId()->toRfc4122() === $mediaId) {
                $media = $candidate;
                break;
            }
        }

        if ($media === null) {
            return $this->json(['message' => 'Ce média ne fait pas partie de cette Vitrine.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->vitrineService->removeMedia($vitrine, $user, $media);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/items/reorder', name: 'vitrine_item_reorder', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function reorderItems(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $orderedItems = $data['orderedItems'] ?? null;

        if (!is_array($orderedItems) || $orderedItems === []) {
            return $this->json(['message' => 'orderedItems doit être un tableau non vide.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($orderedItems as $entry) {
            if (!is_array($entry) || !isset($entry['type'], $entry['id']) || !in_array($entry['type'], ['post', 'media'], true)) {
                return $this->json(
                    ['message' => 'Chaque élément doit contenir "type" ("post" ou "media") et "id".'],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        try {
            $this->vitrineService->reorderItems($vitrine, $user, $orderedItems);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (VitrineValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeVitrine($vitrine));
    }

    #[Route('/{id}/publish', name: 'vitrine_publish', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function publish(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        try {
            $this->vitrineService->publish($vitrine, $user);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (VitrineEmptyException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeVitrine($vitrine));
    }

    #[Route('/{id}/unpublish', name: 'vitrine_unpublish', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function unpublish(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $vitrine = $this->findOrFail($id);
        if ($vitrine instanceof JsonResponse) {
            return $vitrine;
        }

        try {
            $this->vitrineService->unpublish($vitrine, $user);
        } catch (VitrineAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializeVitrine($vitrine));
    }

    private function findOrFail(string $id): Vitrine|JsonResponse
    {
        try {
            $vitrineId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de Vitrine invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $vitrine = $this->vitrines->find($vitrineId);

        if ($vitrine === null) {
            return $this->json(['message' => 'Vitrine introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $vitrine;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVitrine(Vitrine $vitrine): array
    {
        $items = array_merge(
            array_map(fn (VitrinePublication $i): array => $this->serializePostItem($i), $vitrine->getItems()->toArray()),
            array_map(fn (VitrineMedia $i): array => $this->serializeMediaItem($i), $vitrine->getMediaItems()->toArray()),
        );

        usort($items, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return [
            'id' => $vitrine->getId()->toRfc4122(),
            'title' => $vitrine->getTitle(),
            'slug' => $vitrine->getSlug(),
            'description' => $vitrine->getDescription(),
            'status' => $vitrine->getStatus(),
            'viewCount' => $vitrine->getViewCount(),
            'itemsCount' => $vitrine->getItemsCount(),
            'items' => $items,
            'createdAt' => $vitrine->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $vitrine->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePostItem(VitrinePublication $item): array
    {
        $publication = $item->getPublication();

        return [
            'type' => 'post',
            'id' => $publication->getId()->toRfc4122(),
            'position' => $item->getPosition(),
            'addedAt' => $item->getAddedAt()->format(DATE_ATOM),
            'publication' => [
                'id' => $publication->getId()->toRfc4122(),
                'title' => $publication->getTitle(),
                'mediaUrl' => $publication->getMediaUrl(),
                'mediaType' => $publication->getMediaType(),
                'status' => $publication->getStatus(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMediaItem(VitrineMedia $media): array
    {
        return [
            'type' => 'media',
            'id' => $media->getId()->toRfc4122(),
            'position' => $media->getPosition(),
            'addedAt' => $media->getCreatedAt()->format(DATE_ATOM),
            'mediaUrl' => $media->getMediaUrl(),
            'mediaType' => $media->getMediaType(),
        ];
    }
}