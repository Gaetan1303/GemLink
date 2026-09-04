<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Publication;
use App\Entity\VitrineMedia;
use App\Entity\VitrinePublication;
use App\Repository\VitrineRepository;
use App\Service\VitrineViewCounterService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US 4.2 - CA-1 et CA-2
 *
 * Expose la page publique d'une Vitrine, accessible sans authentification.
 *
 * La route /api/public/* doit être exclue du firewall JWT dans
 * config/packages/security.yaml (cf. CONFIG_A_AJOUTER.md).
 *
 * Vitrine n'expose pas de getOrderedItems() : ce contrôleur fusionne
 * lui-même getItems() (VitrinePublication) et getMediaItems() (VitrineMedia)
 * par position, comme le fait déjà VitrineService::resolveCoverMedia().
 *
 * aiResults est un stub vide (cf. serializeAiResults()) : Publication.php
 * n'a pas de relation directe vers PublicationPierre, il faudra injecter
 * son repository une fois partagé.
 */
#[Route('/api/public/vitrines')]
class VitrinePublicController
{
    public function __construct(
        private readonly VitrineRepository $vitrineRepository,
        private readonly VitrineViewCounterService $viewCounter,
    ) {
    }

    #[Route('/{slug}', name: 'api_public_vitrine_show', methods: ['GET'])]
    public function show(string $slug): JsonResponse
    {
        $vitrine = $this->vitrineRepository->findOnePublishedBySlug($slug);

        if (null === $vitrine) {
            throw new NotFoundHttpException('Vitrine introuvable.');
        }

        // Incrément bufferisé en Redis (CA-2) — pas d'écriture synchrone en
        // base ici, volontairement pas VitrineService::recordView().
        $this->viewCounter->incrementView($vitrine->getId()->toRfc4122());

        return new JsonResponse($this->serialize($vitrine));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(mixed $vitrine): array
    {
        $utilisateur = $vitrine->getUser();

        return [
            'id' => $vitrine->getId()->toRfc4122(),
            'slug' => $vitrine->getSlug(),
            'title' => $vitrine->getTitle(),
            'description' => $vitrine->getDescription(),
            'viewCount' => $vitrine->getViewCount(),
            'creator' => [
                'username' => $utilisateur->getUsername(),
                'avatarUrl' => $utilisateur->getAvatarUrl(),
            ],
            'items' => $this->serializeOrderedItems($vitrine),
        ];
    }

    /**
     * Fusionne VitrinePublication et VitrineMedia par position, comme le
     * fait le drag-and-drop unifié côté frontend (US 4.1).
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeOrderedItems(mixed $vitrine): array
    {
        $merged = [];

        foreach ($vitrine->getItems() as $item) {
            $merged[] = $item;
        }

        foreach ($vitrine->getMediaItems() as $media) {
            $merged[] = $media;
        }

        usort(
            $merged,
            static fn (mixed $a, mixed $b): int => $a->getPosition() <=> $b->getPosition(),
        );

        return array_map(
            fn (mixed $node): array => $node instanceof VitrinePublication
                ? $this->serializePublicationItem($node)
                : $this->serializeMediaItem($node),
            $merged,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePublicationItem(VitrinePublication $item): array
    {
        $publication = $item->getPublication();

        return [
            'type' => 'post',
            'id' => $publication->getId()->toRfc4122(),
            'position' => $item->getPosition(),
            'publication' => [
                'id' => $publication->getId()->toRfc4122(),
                'title' => $publication->getTitle(),
                'description' => $publication->getDescription(),
                'mediaUrl' => $publication->getMediaUrl(),
                'mediaType' => $publication->getMediaType(),
                'aiResults' => $this->serializeAiResults($publication),
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
            'mediaUrl' => $media->getMediaUrl(),
            'mediaType' => $media->getMediaType(),
        ];
    }

    /**
     * Publication.php (confirmé) n'expose aucune relation directe vers
     * PublicationPierre — contrairement à ce que je supposais. Les
     * résultats IA doivent donc être récupérés via un repository dédié
     * (ex: PublicationPierreRepository::findByPublication($publication))
     * injecté dans ce contrôleur, plutôt que lus depuis l'entité elle-même.
     * Retourne un tableau vide en attendant — à brancher une fois
     * PublicationPierre.php partagé.
     *
     * @return array<int, array<string, mixed>>
     */
    private function serializeAiResults(Publication $publication): array
    {
        return [];
    }
}
