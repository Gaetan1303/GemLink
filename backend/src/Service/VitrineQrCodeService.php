<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Media\MediaUploaderInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * US 4.2 - CA-3
 *
 * Génère un QR code PNG pointant vers l'URL publique d'une Vitrine et le
 * stocke via MediaUploaderInterface (l'implémentation R2/local déjà
 * utilisée par MediaUploadService, cf. Media/MediaUploadService.php).
 *
 * Volontairement découplé de MediaUploadService : ce dernier fait passer
 * le fichier par MediaValidatorService (whitelist de mime-types pensée
 * pour des uploads utilisateur), ce qui n'a pas de sens pour un asset
 * généré côté serveur. On appelle donc directement l'uploader.
 *
 * API endroid/qr-code v6.1.3 confirmée par `composer show` : la classe
 * Builder n'existe plus 
 */
class VitrineQrCodeService
{
    private const QR_CODE_DIRECTORY = 'vitrines/qr-codes';

    // Palette GemLink : navy #040A20
    private const BRAND_COLOR_R = 4;
    private const BRAND_COLOR_G = 10;
    private const BRAND_COLOR_B = 32;

    public function __construct(
        private readonly MediaUploaderInterface $uploader,
        private readonly string $frontendUrl,
    ) {
    }

    /**
     * Génère le QR code de la Vitrine et le stocke sur le CDN.
     *
     * @param string $slug      slug de la Vitrine (URL canonique)
     * @param Uuid   $vitrineId UUID de la Vitrine (utilisé pour nommer le fichier)
     *
     * @return string URL publique du QR code stocké
     */
    public function generateAndStore(string $slug, Uuid $vitrineId): string
    {
        $publicUrl = $this->buildPublicUrl($slug);

        $qrCode = new QrCode(
            data: $publicUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 16,
            foregroundColor: new Color(self::BRAND_COLOR_R, self::BRAND_COLOR_G, self::BRAND_COLOR_B),
            backgroundColor: new Color(255, 255, 255),
        );

        $result = (new PngWriter())->write($qrCode);

        $tmpPath = tempnam(sys_get_temp_dir(), 'gemlink_qr_');

        if ($tmpPath === false) {
            throw new RuntimeException('Impossible de créer un fichier temporaire pour le QR code.');
        }

        file_put_contents($tmpPath, $result->getString());

        // MediaUploaderInterface::upload() attend un UploadedFile Symfony ;
        // le flag $test=true permet de construire l'objet à partir d'un
        // fichier généré en interne (pas un vrai upload HTTP), sans que
        // is_uploaded_file() ne rejette l'appel.
        $uploadedFile = new UploadedFile(
            $tmpPath,
            sprintf('%s.png', $vitrineId->toRfc4122()),
            'image/png',
            null,
            true,
        );

        try {
            return $this->uploader->upload($uploadedFile, self::QR_CODE_DIRECTORY);
        } finally {
            if (is_file($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function buildPublicUrl(string $slug): string
    {
        return sprintf('%s/vitrines/public/%s', rtrim($this->frontendUrl, '/'), $slug);
    }
}