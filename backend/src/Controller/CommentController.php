<?php



namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Publication;
use App\Entity\User;
use App\Exception\CommentAccessDeniedException;
use App\Exception\CommentValidationException;
use App\Repository\CommentaireRepository;
use App\Repository\PublicationRepository;
use App\Service\CommentService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.4 — Commentaires MVP (CA-1 à CA-4).
 *
 * Comme PublicationController : la lecture (liste) est publique (un
 * visiteur non authentifié doit pouvoir lire les commentaires d'un post,
 * cf. security.yaml), la création et la suppression exigent une
 * authentification complète.
 */
final class CommentController extends AbstractController
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 50;

    public function __construct(
        private readonly CommentService $commentService,
        private readonly CommentaireRepository $comments,
        private readonly PublicationRepository $publications,
    ) {
    }

    /**
     * CA-3 : commentaires actifs d'un post, ordre chronologique croissant,
     * pagination cursor-based (curseur = id du dernier commentaire de la
     * page précédente). Query params : cursor (optionnel), limit (défaut
     * 20, max 50).
     */
    #[Route('/api/publications/{postId}/comments', name: 'comment_index', methods: ['GET'])]
    public function index(string $postId, Request $request): JsonResponse
    {
        $publication = $this->findActivePublicationOrFail($postId);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        $cursor = $this->parseCursor($request->query->get('cursor'));

        if ($cursor === false) {
            return $this->json(['message' => 'Curseur de pagination invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $limit = min(self::MAX_PAGE_SIZE, max(1, $request->query->getInt('limit', self::DEFAULT_PAGE_SIZE)));

        $items = $this->comments->findActiveByPublicationPaginated($publication->getId(), $cursor, $limit);
        $hasMore = count($items) === $limit;
        $lastItem = $items[count($items) - 1] ?? null;

        return $this->json([
            'items' => array_map($this->serializeComment(...), $items),
            'nextCursor' => $hasMore && $lastItem !== null ? $lastItem->getId()->toRfc4122() : null,
            'limit' => $limit,
        ]);
    }

    /**
     * CA-1 : création d'un commentaire par un utilisateur authentifié.
     * CA-4 : déclenche la notification in-app de l'auteur du post.
     */
    #[Route('/api/publications/{postId}/comments', name: 'comment_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(string $postId, Request $request): JsonResponse
    {
        /** @var User $author */
        $author = $this->getUser();

        $publication = $this->findActivePublicationOrFail($postId);

        if ($publication instanceof JsonResponse) {
            return $publication;
        }

        $payload = json_decode($request->getContent(), true);
        $content = is_array($payload) && isset($payload['content']) && is_string($payload['content'])
            ? $payload['content']
            : (string) $request->request->get('content', '');

        try {
            $comment = $this->commentService->createComment($author, $publication, $content);
        } catch (CommentValidationException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializeComment($comment), Response::HTTP_CREATED);
    }

    /**
     * CA-2 : suppression réservée à l'auteur, un modérateur ou un
     * administrateur. Soft delete tracé dans l'audit log.
     */
    #[Route('/api/comments/{id}', name: 'comment_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete(string $id): JsonResponse
    {
        /** @var User $actor */
        $actor = $this->getUser();

        $comment = $this->findActiveCommentOrFail($id);

        if ($comment instanceof JsonResponse) {
            return $comment;
        }

        try {
            $this->commentService->deleteComment($comment, $actor);
        } catch (CommentAccessDeniedException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_FORBIDDEN);
        }

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @return Publication|JsonResponse une réponse d'erreur (400/404) toute
     *         prête, ou le post actif trouvé
     */
    private function findActivePublicationOrFail(string $id): Publication|JsonResponse
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
     * @return Commentaire|JsonResponse une réponse d'erreur (400/404) toute
     *         prête, ou le commentaire actif trouvé
     */
    private function findActiveCommentOrFail(string $id): Commentaire|JsonResponse
    {
        try {
            $commentId = Uuid::fromString($id);
        } catch (\InvalidArgumentException) {
            return $this->json(['message' => 'Identifiant de commentaire invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $comment = $this->comments->findOneActiveById($commentId);

        if ($comment === null) {
            return $this->json(['message' => 'Commentaire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $comment;
    }

    /**
     * @return Uuid|null|false null si pas de curseur fourni, false si le
     *         curseur fourni n'est pas un UUID valide
     */
    private function parseCursor(?string $cursor): Uuid|null|false
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        try {
            return Uuid::fromString($cursor);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(Commentaire $comment): array
    {
        $author = $comment->getUser();

        return [
            'id' => $comment->getId()->toRfc4122(),
            'publicationId' => $comment->getPublication()->getId()->toRfc4122(),
            'author' => [
                'id' => $author->getId()->toRfc4122(),
                'username' => $author->getUsername(),
                'avatarUrl' => $author->getAvatarUrl(),
            ],
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $comment->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }
}
