<?php

namespace App\Service;

use App\Exception\InvalidMediaException;
use App\Service\Media\MediaUploaderInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AvatarUploadService
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(private readonly MediaUploaderInterface $uploader) {}

    public function upload(UploadedFile $file): string
    {
        if (!$file->isValid()) throw new InvalidMediaException('Le fichier avatar est invalide ou incomplet.');
        if ((int) $file->getSize() > 2 * 1024 * 1024) throw new InvalidMediaException('L’avatar dépasse la taille maximale autorisée de 2 Mo.');
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? false : finfo_file($finfo, $file->getPathname());
        if ($finfo !== false) finfo_close($finfo);
        if (!is_string($mime) || !in_array($mime, self::ALLOWED, true)) throw new InvalidMediaException('Formats acceptés : jpeg, png, webp.');
        if (!function_exists('imagewebp')) throw new InvalidMediaException('Le redimensionnement des avatars est indisponible sur ce serveur.');
        $source = match ($mime) { 'image/jpeg' => @imagecreatefromjpeg($file->getPathname()), 'image/png' => @imagecreatefrompng($file->getPathname()), 'image/webp' => @imagecreatefromwebp($file->getPathname()) };
        if ($source === false) throw new InvalidMediaException('L’image avatar ne peut pas être lue.');
        $tmp = false;
        try {
            $w = imagesx($source); $h = imagesy($source); $side = min($w, $h);
            $target = imagecreatetruecolor(256, 256);
            imagealphablending($target, false); imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            imagecopyresampled($target, $source, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 256, 256, $side, $side);
            $tmp = tempnam(sys_get_temp_dir(), 'gemlink_avatar_');
            if ($tmp === false || !imagewebp($target, $tmp, 85)) throw new InvalidMediaException('Impossible de redimensionner l’avatar.');
            imagedestroy($target);
            return $this->uploader->upload(new UploadedFile($tmp, 'avatar.webp', 'image/webp', null, true), sprintf('avatars/%s', (new DateTimeImmutable())->format('Y/m')));
        } finally { imagedestroy($source); if (is_string($tmp) && is_file($tmp)) @unlink($tmp); }
    }
}
