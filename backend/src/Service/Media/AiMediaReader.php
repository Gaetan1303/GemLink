<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Exception\AiAnalysisException;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Reads only server-owned upload paths or the exact configured R2 CDN prefix. */
final class AiMediaReader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiImageSanitizer $images,
        private readonly string $mediaStorageMode,
        private readonly string $uploadDir,
        private readonly string $localPublicBaseUrl,
        private readonly string $r2PublicBaseUrl,
    ) {}

    /** @return array{0: string, 1: string} */
    public function read(string $mediaUrl, bool $allowVideo = false): array
    {
        $maxBytes = $allowVideo ? MediaValidatorService::MAX_VIDEO_SIZE_BYTES : MediaValidatorService::MAX_IMAGE_SIZE_BYTES;
        if ($this->mediaStorageMode === 'local') {
            $relative = $this->relativePath($mediaUrl, $this->localPublicBaseUrl);
            $root = realpath($this->uploadDir);
            $path = $root === false ? false : realpath($root . '/' . $relative);
            if ($path === false || !str_starts_with($path, $root . '/') || !is_file($path) || filesize($path) > $maxBytes) throw new AiAnalysisException('Média local non autorisé.');
            $content = file_get_contents($path);
            if ($content === false) throw new AiAnalysisException('Média local indisponible.');
        } elseif ($this->mediaStorageMode === 'r2') {
            $this->relativePath($mediaUrl, $this->r2PublicBaseUrl);
            $url = parse_url($mediaUrl);
            if (($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['query']) || isset($url['fragment'])) throw new AiAnalysisException('URL média non autorisée.');
            $http = new NoPrivateNetworkHttpClient($this->httpClient);
            $response = $http->request('GET', $mediaUrl, ['timeout' => 15, 'max_duration' => 30, 'max_redirects' => 0]);
            try {
                if ($response->getStatusCode() !== 200) throw new AiAnalysisException('Média CDN indisponible.');
                $content = '';
                foreach ($http->stream($response) as $chunk) {
                    $content .= $chunk->getContent();
                    if (strlen($content) > $maxBytes) throw new AiAnalysisException('Média trop volumineux.');
                }
            } finally { $response->cancel(); }
        } else { throw new AiAnalysisException('Stockage média non configuré.'); }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        // Preserve the existing video path. Secondary vision only accepts images.
        if ($allowVideo && $mime === 'video/mp4') return [$content, $mime];
        return $this->images->sanitize($content);
    }

    private function relativePath(string $url, string $base): string
    {
        $prefix = rtrim($base, '/') . '/';
        if ($base === '' || !str_starts_with($url, $prefix)) throw new AiAnalysisException('Origine média non autorisée.');
        $relative = substr($url, strlen($prefix));
        if (!preg_match('~^[a-zA-Z0-9_/-]+\.[a-zA-Z0-9]+$~D', $relative) || str_contains($relative, '..')) throw new AiAnalysisException('Chemin média non autorisé.');
        return $relative;
    }
}
