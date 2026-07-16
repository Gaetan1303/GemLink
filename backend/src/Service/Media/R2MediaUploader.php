<?php



namespace App\Service\Media;

use App\Exception\InvalidMediaException;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * US 2.1 CA-3 : transfert du média vers Cloudflare R2 (API S3-compatible),
 * via Flysystem (league/flysystem-aws-s3-v3). Le client S3 est configuré
 * dans config/services.yaml à partir des variables d'environnement R2_*.
 *
 * Le stockage est pensé "sans egress fees" (R2) mais reste compatible avec
 * tout backend S3 (ex. Backblaze B2 en secours) puisqu'on ne dépend que de
 * l'interface Flysystem générique.
 */
final class R2MediaUploader implements MediaUploaderInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $publicBaseUrl, // ex. 'https://media.gem-link.org'
    ) {
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        $extension = $file->guessExtension() ?? $file->getClientOriginalExtension() ?: 'bin';
        $key = trim($directory, '/') . '/' . Uuid::v7() . '.' . $extension;

        $stream = fopen($file->getPathname(), 'rb');

        if ($stream === false) {
            throw new InvalidMediaException('Impossible de lire le fichier média avant transfert.');
        }

        try {
            $this->filesystem->writeStream($key, $stream, [
                'mimetype' => $file->getMimeType(),
                'visibility' => 'public',
            ]);
        } catch (UnableToWriteFile $exception) {
            throw new InvalidMediaException('Le transfert du fichier vers le CDN a échoué.', previous: $exception);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return rtrim($this->publicBaseUrl, '/') . '/' . $key;
    }
}
