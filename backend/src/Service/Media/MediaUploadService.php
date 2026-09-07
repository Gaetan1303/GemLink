<?php



namespace App\Service\Media;

use App\Exception\InvalidMediaException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * US 2.1 — Responsabilité unique : valider (CA-2) puis transférer (CA-3) un
 * fichier média vers le CDN. Ne connaît rien de Publication ni de PostService
 * (SRP) : peut être réutilisé demain pour l'avatar, les vignettes, etc.,
 * chacun avec sa propre instance/configuration de MediaValidatorService si besoin.
 */
class MediaUploadService
{
    public function __construct(
        private readonly MediaValidatorService $validator,
        private readonly MediaUploaderInterface $uploader,
        private readonly ?AiImageSanitizer $images = null,
    ) {
    }

    /**
     * @throws InvalidMediaException si le fichier est absent, mal typé,
     *         trop volumineux, ou (vidéo) trop longue
     */
    public function upload(?UploadedFile $file, string $directory): UploadedMedia
    {
        if ($file === null) {
            throw new InvalidMediaException('Un fichier média est obligatoire.');
        }

        $validation = $this->validator->validate($file);
        $sizeBytes = $validation->isImage() ? $this->sanitizeUpload($file) : $validation->sizeBytes;
        $mediaUrl = $this->uploader->upload($file, $directory);

        return new UploadedMedia(
            $mediaUrl,
            $validation->mediaType,
            $validation->mimeType,
            $sizeBytes,
            $validation->durationSeconds,
        );
    }

    /** Upload temporaire du parcours public, volontairement limité à 1 Mo/image. */
    public function uploadPublicImage(?UploadedFile $file, string $directory): UploadedMedia
    {
        if ($file === null) throw new InvalidMediaException('Une image est obligatoire.');
        $validation = $this->validator->validatePublicIdentificationImage($file);
        $sizeBytes = $this->sanitizeUpload($file, 1024 * 1024);
        return new UploadedMedia($this->uploader->upload($file, $directory), $validation->mediaType, $validation->mimeType, $sizeBytes);
    }
    private function sanitizeUpload(UploadedFile $file, int $maxBytes = MediaValidatorService::MAX_IMAGE_SIZE_BYTES): int
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false) throw new InvalidMediaException('Image indisponible.');
        $images = $this->images ?? throw new InvalidMediaException('Validation image non configurée.');
        [$clean] = $images->sanitize($content);
        if (strlen($clean) > $maxBytes) throw new InvalidMediaException('Image nettoyée trop volumineuse.');
        if (file_put_contents($file->getPathname(), $clean) === false) throw new InvalidMediaException('Impossible de nettoyer l’image.');
        return strlen($clean);
    }
}
