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
        $mediaUrl = $this->uploader->upload($file, $directory);

        return new UploadedMedia(
            $mediaUrl,
            $validation->mediaType,
            $validation->mimeType,
            $validation->sizeBytes,
            $validation->durationSeconds,
        );
    }
}
