<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\InvalidMediaException;
use App\Service\Media\MediaUploadService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * US 2.1 — Contrôleur dédié exclusivement au transfert d'un fichier média
 * vers le CDN (SRP). Ne connaît rien de Publication : il ne fait que valider
 * et stocker un fichier, puis renvoie son URL publique.
 *
 * Pensé pour être réutilisé par de futures fonctionnalités d'upload (vidéos
 * de posts, et plus tard avatar, vignettes...) sans dupliquer la logique de
 * validation/transfert — voir PublicationController qui consomme l'URL
 * renvoyée ici pour créer le post.
 *
 * Compromis assumé (MVP) : un fichier uploadé ici mais jamais rattaché à un
 * post reste orphelin sur le CDN (le client peut abandonner le formulaire
 * après l'upload). À traiter plus tard par un job de purge périodique si le
 * volume le justifie — hors périmètre de l'US 2.1.
 */
#[Route('/api/media')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class MediaUploadController extends AbstractController
{
    public function __construct(
        private readonly MediaUploadService $mediaUploadService,
    ) {
    }

    /**
     * CA-2 : formats/tailles/durée validés côté serveur (magic bytes).
     * CA-3 : transfert immédiat vers le CDN externe.
     */
    #[Route('', name: 'media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        $directory = sprintf('publications/%s', (new DateTimeImmutable())->format('Y/m'));

        try {
            $media = $this->mediaUploadService->upload($file, $directory);
        } catch (InvalidMediaException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'mediaUrl' => $media->mediaUrl,
            'mediaType' => $media->mediaType,
            'mimeType' => $media->mimeType,
            'sizeBytes' => $media->sizeBytes,
            'durationSeconds' => $media->durationSeconds,
        ], Response::HTTP_CREATED);
    }
}
