<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\VitrineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

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