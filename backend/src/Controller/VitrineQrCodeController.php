<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\VitrineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * US 4.2 - CA-3
 *
 * Permet au propriétaire d'une Vitrine de télécharger son QR code
 * depuis l'interface de gestion. Route authentifiée (firewall JWT standard).
 *
 * ⚠️ Suppose l'existence d'un Voter "VIEW" sur Vitrine restreignant l'accès
 * au propriétaire. Si ce Voter n'existe pas encore, l'ajouter ou adapter
 * la vérification d'accès à votre mécanisme existant.
 */
#[Route('/api/vitrines')]
class VitrineQrCodeController extends AbstractController
{
    public function __construct(
        private readonly VitrineRepository $vitrineRepository,
    ) {
    }

    #[Route('/{id}/qr-code', name: 'api_vitrine_qr_code_download', methods: ['GET'])]
    public function download(string $id): RedirectResponse
    {
        $vitrine = $this->vitrineRepository->find($id);

        if (null === $vitrine) {
            throw new NotFoundHttpException('Vitrine introuvable.');
        }

        $this->denyAccessUnlessGranted('VIEW', $vitrine);

        if (null === $vitrine->getQrCodeUrl()) {
            throw new NotFoundHttpException('QR code non généré pour cette Vitrine.');
        }

        return new RedirectResponse($vitrine->getQrCodeUrl());
    }
}
