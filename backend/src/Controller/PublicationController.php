<?php



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
 * US 2.2 — Consultation des posts (liste + détail).
 *
 * NOTE : contrairement aux autres contrôleurs du projet (Contact, Rgpd, Newsletter,
 * publics et donc montés hors /api), la création/suppression DOIT être authentifiée.
 * Seul le pattern `^/api` est couvert par le firewall JWT stateless
 * (config/packages/security.yaml), d'où le préfixe /api explicite ci-dessous.
 *
 * En revanche, la liste et le détail sont PUBLICS (visiteur non authentifié
 * inclus, cf. diagramme d'architecture "Visiteur : Home · Galerie · Vitrine
 * publique") — #[IsGranted] est donc posé méthode par méthode, pas au niveau
 * de la classe.
 */
#[Route('/api/publications')]
final class PublicationController extends AbstractController
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 50;

    public function __construct(
        private readonly PostService $postService,
        private readonly PublicationRepository $publications,
    ) {
    }

    /**
     * US 2.2 — Feed public paginé, du plus récent au plus ancien.
     * Query params : page (défaut 1), limit (défaut 20, max 50).
     */
    #[Route('', name: 'publication_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_PAGE_SIZE, max(1, $request->query->getInt('limit', self::DEFAULT_PAGE_SIZE)));

        $items = $this->publications->findActivePaginated($page, $limit);
        $total = $this->publications->countActive();

        return $this->json([
            'items' => array_map($this->serializePost(...), $items),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => (int) ceil($total / $limit),
        ]);
    }

    /**
     * US 2.2 — Détail public d'un post. Incrémente le compteur de vues (best-effort).
     */
    #[Route('/{id}', name: 'publication_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $publication = $this->findActiveOrFail($id);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        $this->postService->recordView($publication);

        return $this->json($this->serializePost($publication));
    }

    /**
     * CA-1, CA-2, CA-3 : création d'un post à partir d'un multipart/form-data
     * (champ fichier "media" + title/description/tags optionnels).
     */
    #[Route('', name: 'publication_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
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
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $this->getUser();

        $publication = $this->findActiveOrFail($id);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        try {
            $this->postService->softDelete($publication, $actor);
        } catch (PostAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return Publication|JsonResponse une réponse d'erreur (400/404) toute prête,
     *         ou le post actif trouvé
     */
    private function findActiveOrFail(string $id): Publication|JsonResponse
    {
        try {
            $postId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de post invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $publication = $this->publications->findOneActiveById($postId);

        if ($publication === null) {
            return $this->json(['message' => 'Post introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $publication;
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
        $author = $publication->getUser();

        return [
            'id' => $publication->getId()->toRfc4122(),
            'author' => [
                'id' => $author->getId()->toRfc4122(),
                'username' => $author->getUsername(),
                'avatarUrl' => $author->getAvatarUrl(),
            ],
            'title' => $publication->getTitle(),
            'description' => $publication->getDescription(),
            'mediaUrl' => $publication->getMediaUrl(),
            'mediaType' => $publication->getMediaType(),
            'status' => $publication->getStatus(),
            'viewCount' => $publication->getViewCount(),
            'tags' => array_map(
                static fn ($tag) => $tag->getName(),
                $publication->getTags()->toArray()
            ),
            'createdAt' => $publication->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
