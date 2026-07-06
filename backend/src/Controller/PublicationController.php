<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Exception\PostAccessDeniedException;
use App\Exception\PostValidationException;
use App\Repository\PublicationRepository;
use App\Service\PostService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.1 — Publication d'un post MVP.
 *
 * NOTE : contrairement aux autres contrôleurs du projet (Contact, Rgpd, Newsletter,
 * publics et donc montés hors /api), celui-ci DOIT être authentifié. Seul le pattern
 * `^/api` est couvert par le firewall JWT stateless (config/packages/security.yaml),
 * d'où le préfixe /api explicite ci-dessous.
 *
 * Endpoint unique multipart (media + champs), conforme à la boîte "Posts &
 * Feed" du diagramme d'architecture. PostService délègue en interne la
 * validation/le transfert du fichier à MediaUploadService (SRP au niveau
 * classe, pas au niveau HTTP) — voir aussi MediaUploadController, qui expose
 * le même MediaUploadService de façon autonome pour de futurs cas d'usage
 * (avatar, vignettes...).
 */
#[Route('/api/publications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PublicationController extends AbstractController
{
    public function __construct(
        private readonly PostService $postService,
        private readonly PublicationRepository $publications,
    ) {
    }

    /**
     * CA-1, CA-2, CA-3 : création d'un post à partir d'un multipart/form-data
     * (champ fichier "media" + title/description/tags optionnels).
     */
    #[Route('', name: 'publication_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $author */
        $author = $this->getUser();

        $mediaFile = $request->files->get('media');
        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $tags = $this->extractTags($request);

        try {
            $publication = $this->postService->createPost(
                $author,
                $mediaFile,
                is_string($title) ? $title : null,
                is_string($description) ? $description : null,
                $tags,
            );
        } catch (InvalidMediaException|PostValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializePost($publication), Response::HTTP_CREATED);
    }

    /**
     * CA-4 : suppression réservée à l'auteur, un modérateur ou un administrateur.
     * Soft delete par défaut (deleted_at).
     */
    #[Route('/{id}', name: 'publication_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $this->getUser();

        try {
            $postId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de post invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $publication = $this->publications->findOneActiveById($postId);

        if ($publication === null) {
            return $this->json(['message' => 'Post introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->postService->softDelete($publication, $actor);
        } catch (PostAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return string[]
     */
    private function extractTags(Request $request): array
    {
        $rawTags = $request->request->get('tags');

        if (is_array($rawTags)) {
            return array_values(array_filter(array_map(
                static fn (mixed $tag): string => is_string($tag) ? trim($tag) : '',
                $rawTags,
            ), static fn (string $tag): bool => $tag !== ''));
        }

        if (!is_string($rawTags) || trim($rawTags) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $tag): string => trim($tag),
            explode(',', $rawTags),
        ), static fn (string $tag): bool => $tag !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePost(Publication $publication): array
    {
        return [
            'id' => $publication->getId()->toRfc4122(),
            'authorId' => $publication->getUser()->getId()->toRfc4122(),
            'title' => $publication->getTitle(),
            'description' => $publication->getDescription(),
            'mediaUrl' => $publication->getMediaUrl(),
            'mediaType' => $publication->getMediaType(),
            'status' => $publication->getStatus(),
            'tags' => array_map(
                static fn ($tag) => $tag->getName(),
                $publication->getTags()->toArray()
            ),
            'createdAt' => $publication->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
