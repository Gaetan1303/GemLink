<?php

declare(strict_types=1);

namespace App\Service\Media;

use App\Exception\InvalidMediaException;

/** Decode/re-encode instead of passing filenames, EXIF or trailing payloads to AI. */
final class AiImageSanitizer
{
    public const MAX_SIDE = 8192;
    public const MAX_PIXELS = 16000000;

    /** @return array{0: string, 1: string} cleaned bytes and actual MIME */
    public function sanitize(string $content): array
    {
        if ($content === '' || strlen($content) > MediaValidatorService::MAX_IMAGE_SIZE_BYTES) throw new InvalidMediaException('Image trop volumineuse ou vide.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        $size = @getimagesizefromstring($content);
        if (!in_array($mime, MediaValidatorService::ALLOWED_IMAGE_MIME_TYPES, true) || $size === false || ($size['mime'] ?? null) !== $mime) throw new InvalidMediaException('Format image invalide.');
        if ($size[0] < 1 || $size[1] < 1 || max($size[0], $size[1]) > self::MAX_SIDE || $size[0] * $size[1] > self::MAX_PIXELS) throw new InvalidMediaException('Dimensions image non autorisées.');
        $image = @imagecreatefromstring($content);
        if ($image === false) throw new InvalidMediaException('Image indécodable.');
        // Preserve JPEG display orientation before removing EXIF. data:// is an
        // in-memory stream assembled from validated bytes, never a caller URL.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data('data://application/octet-stream;base64,' . base64_encode($content));
            $orientation = is_array($exif) ? ($exif['Orientation'] ?? 1) : 1;
            if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
            $angle = match ($orientation) { 3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0 };
            if ($angle !== 0) $image = imagerotate($image, $angle, 0);
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        try {
            $ok = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, 92),
                'image/png' => imagepng($image),
                'image/webp' => imagewebp($image, null, 92),
            };
            $clean = ob_get_contents();
        } finally { ob_end_clean(); }
        if (!$ok || !is_string($clean) || $clean === '' || strlen($clean) > MediaValidatorService::MAX_IMAGE_SIZE_BYTES) throw new InvalidMediaException('Image réencodée trop volumineuse ou invalide.');
        return [$clean, $mime];
    }

    /** @return array{0: string, 1: string} */
    public function fromBase64(string $image): array
    {
        if (strlen($image) > 13981016 || ($bytes = base64_decode($image, true)) === false || base64_encode($bytes) !== $image) throw new InvalidMediaException('Base64 image invalide.');
        return $this->sanitize($bytes);
    }
}
