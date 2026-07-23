<?php



namespace App\Service\Media;

use App\Entity\Publication;
use App\Exception\InvalidMediaException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * US 2.1 CA-1 / CA-2.
 *
 * Valide un fichier média uploadé :
 *  - présence obligatoire (CA-1, vérifié en amont par le contrôleur/service appelant),
 *  - type MIME réel (magic bytes, via fileinfo) indépendant de l'extension du fichier,
 *  - taille maximale selon le type (10 Mo image / 100 Mo vidéo),
 *  - durée maximale pour les vidéos (60 s), lue via `ffprobe` si le binaire est disponible.
 *
 */
class MediaValidatorService
{
    public const ALLOWED_IMAGE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public const ALLOWED_VIDEO_MIME_TYPES = ['video/mp4'];

    public const MAX_IMAGE_SIZE_BYTES = 10 * 1024 * 1024;   // 10 Mo
    public const MAX_VIDEO_SIZE_BYTES = 100 * 1024 * 1024;  // 100 Mo
    public const MAX_VIDEO_DURATION_SECONDS = 60;

    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly string $ffprobeBinary = 'ffprobe',
    ) {
    }

    public function validate(UploadedFile $file): MediaValidationResult
    {
        if (!$file->isValid()) {
            throw new InvalidMediaException('Le fichier média est invalide ou incomplet.');
        }

        $pathname = $file->getPathname();
        $mimeType = $this->detectRealMimeType($pathname);
        $sizeBytes = (int) $file->getSize();

        $mediaType = match (true) {
            in_array($mimeType, self::ALLOWED_IMAGE_MIME_TYPES, true) => Publication::MEDIA_TYPE_IMAGE,
            in_array($mimeType, self::ALLOWED_VIDEO_MIME_TYPES, true) => Publication::MEDIA_TYPE_VIDEO,
            default => throw new InvalidMediaException(sprintf(
                'Type de fichier non supporté (%s). Formats acceptés : jpeg, png, webp, mp4.',
                $mimeType
            )),
        };

        if ($mediaType === Publication::MEDIA_TYPE_IMAGE) {
            if ($sizeBytes > self::MAX_IMAGE_SIZE_BYTES) {
                throw new InvalidMediaException('L\'image dépasse la taille maximale autorisée de 10 Mo.');
            }

            return new MediaValidationResult($mediaType, $mimeType, $sizeBytes);
        }

        // Vidéo
        if ($sizeBytes > self::MAX_VIDEO_SIZE_BYTES) {
            throw new InvalidMediaException('La vidéo dépasse la taille maximale autorisée de 100 Mo.');
        }

        $duration = $this->probeDurationSeconds($pathname);

        if ($duration !== null && $duration > self::MAX_VIDEO_DURATION_SECONDS) {
            throw new InvalidMediaException('La vidéo dépasse la durée maximale autorisée de 60 secondes.');
        }

        return new MediaValidationResult($mediaType, $mimeType, $sizeBytes, $duration);
    }

    /**
     * Lit les "magic bytes" du fichier via l'extension fileinfo, indépendamment
     * de l'extension ou du Content-Type déclaré par le client (CA-2).
     */
    private function detectRealMimeType(string $pathname): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            throw new InvalidMediaException('Impossible d\'analyser le type du fichier média.');
        }

        $mimeType = finfo_file($finfo, $pathname);
        finfo_close($finfo);

        if ($mimeType === false || $mimeType === '') {
            throw new InvalidMediaException('Impossible de déterminer le type réel du fichier média.');
        }

        return $mimeType;
    }

    private function probeDurationSeconds(string $pathname): ?float
    {
        $process = new Process([
            $this->ffprobeBinary,
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $pathname,
        ]);

        try {
            $process->run();
        } catch (ProcessExceptionInterface $exception) {
            $this->logger->warning('ffprobe indisponible, validation de durée vidéo ignorée.', [
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!$process->isSuccessful()) {
            $this->logger->warning('ffprobe a échoué, validation de durée vidéo ignorée.', [
                'error' => $process->getErrorOutput(),
            ]);

            return null;
        }

        $output = trim($process->getOutput());

        return is_numeric($output) ? (float) $output : null;
    }
}
