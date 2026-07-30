<?php



namespace App\Controller;

use App\Entity\Publication;
use App\Entity\User;
use App\Exception\InvalidMediaException;
use App\Exception\PostAccessDeniedException;
use App\Exception\PostValidationException;
use App\Repository\PublicationPierreRepository;
use App\Repository\PublicationRepository;
use App\Service\PostService;
use App\Service\FeedCacheService;
use DateTimeImmutable;
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
        private readonly PublicationPierreRepository $publicationPierres,
        private readonly FeedCacheService $feedCache,
    ) {
    }

    /**
     * Feed cursor-based. `nextCursor` is an opaque position, never an offset.
     */
    #[Route('', name: 'publication_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $limit = min(self::MAX_PAGE_SIZE, max(1, $request->query->getInt('limit', self::DEFAULT_PAGE_SIZE)));
        $cursor = $this->decodeCursor($request->query->get('cursor'));
        if ($cursor === false) return $this->json(['message' => 'Curseur de feed invalide.'], Response::HTTP_BAD_REQUEST);

        $tag = $this->stringQuery($request, 'tag');
        $mineral = $this->stringQuery($request, 'mineral');
        $minConfidence = $request->query->has('minConfidence') ? $request->query->get('minConfidence') : null;
        if ($minConfidence !== null && (!is_numeric($minConfidence) || (float) $minConfidence < 0 || (float) $minConfidence > 1)) {
            return $this->json(['message' => 'minConfidence doit être compris entre 0 et 1.'], Response::HTTP_BAD_REQUEST);
        }
        $personalized = $request->query->getBoolean('personalized');
        $user = $this->getUser();
        if ($personalized && !$user instanceof User) return $this->json(['message' => 'Authentification requise pour le feed personnalisé.'], Response::HTTP_UNAUTHORIZED);

        // The unfiltered first global page is served from the Redis List hot index.
        if ($cursor === null && !$personalized && $tag === null && $mineral === null && $minConfidence === null) {
            $items = array_slice($this->publications->findActiveByIds($this->feedCache->recentIds()), 0, $limit + 1);
        } else {
            $items = $this->publications->findFeed($cursor['date'] ?? null, $cursor['id'] ?? null, $limit, $tag, $mineral, $minConfidence === null ? null : (float) $minConfidence, $personalized ? $user : null);
        }
        $hasNextPage = count($items) > $limit;
        $items = array_slice($items, 0, $limit);
        $last = $items === [] ? null : $items[array_key_last($items)];

        return $this->json([
            'items' => array_map($this->serializePost(...), $items),
            'limit' => $limit,
            'nextCursor' => $hasNextPage && $last instanceof Publication ? $this->encodeCursor($last) : null,
            'hasNextPage' => $hasNextPage,
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

    private function stringQuery(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array{date: DateTimeImmutable, id: Uuid}|null|false */
    private function decodeCursor(mixed $cursor): array|null|false
    {
        if ($cursor === null || $cursor === '') return null;
        if (!is_string($cursor)) return false;
        $encoded = strtr($cursor, '-_', '+/');
        $decoded = base64_decode($encoded . str_repeat('=', (4 - strlen($encoded) % 4) % 4), true);
        if ($decoded === false) return false;
        try {
            $data = json_decode($decoded, true, 3, JSON_THROW_ON_ERROR);
            if (!is_array($data) || !isset($data['d'], $data['i'])) return false;
            return ['date' => new DateTimeImmutable((string) $data['d']), 'id' => Uuid::fromString((string) $data['i'])];
        } catch (\Throwable) { return false; }
    }

    private function encodeCursor(Publication $post): string
    {
        return rtrim(strtr(base64_encode(json_encode(['d' => $post->getCreatedAt()->format(DATE_ATOM), 'i' => $post->getId()->toRfc4122()], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
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
            // US 3.1 : résultat de l'identification IA (vide tant que le
            // statut n'est pas ANALYZED, ou si aucun match n'a été persisté).
            'identification' => $this->serializeIdentification($publication),
            'createdAt' => $publication->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeIdentification(Publication $publication): ?array
    {
        $match = $this->publicationPierres->findBestMatch($publication);

        if ($match === null) {
            return null;
        }

        $pierre = $match->getPierre();

        return [
            'nom' => $pierre->getName(),
            'categorie' => $pierre->getCategory(),
            'durete' => $pierre->getHardness(),
            'systemeCristallin' => $pierre->getCrystalSystem(),
            'composition' => $pierre->getComposition(),
            'description' => $pierre->getDescription(),
            'confidence' => $match->getConfidence(),
            'isHighConfidence' => $match->isHighConfidence(),
        ];
    }
}
