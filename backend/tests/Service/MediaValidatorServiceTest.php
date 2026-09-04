<?php



namespace App\Tests\Service;

use App\Entity\Publication;
use App\Exception\InvalidMediaException;
use App\Service\Media\MediaValidatorService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * US 2.1 CA-2 : type MIME réel (magic bytes) + limites de taille/durée.
 */
final class MediaValidatorServiceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/gemlink_media_test_' . uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir . '/*') ?: []);
        @rmdir($this->tmpDir);
    }

    public function testValidJpegIsAcceptedAsImage(): void
    {
        $validator = new MediaValidatorService();
        $file = $this->makeUploadedFile($this->createJpeg(), 'pierre.jpg');

        $result = $validator->validate($file);

        $this->assertTrue($result->isImage());
        $this->assertSame('image/jpeg', $result->mimeType);
    }

    public function testMimeTypeIsDetectedFromContentNotExtension(): void
    {
        // CA-2 : un .txt renommé en .jpg doit être détecté par ses vrais magic bytes.
        $validator = new MediaValidatorService();
        $file = $this->makeUploadedFile("plain text content\n", 'fake.jpg');

        $this->expectException(InvalidMediaException::class);
        $validator->validate($file);
    }

    public function testOversizedImageIsRejected(): void
    {
        $validator = new MediaValidatorService();
        $content = $this->createJpeg() . str_repeat('A', MediaValidatorService::MAX_IMAGE_SIZE_BYTES + 1);
        $file = $this->makeUploadedFile($content, 'huge.jpg');

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessage('10 Mo');
        $validator->validate($file);
    }

    public function testInvalidUploadedFileIsRejected(): void
    {
        $validator = new MediaValidatorService();
        $path = $this->writeTmpFile($this->createJpeg());

        // Simule une erreur d'upload PHP (ex. UPLOAD_ERR_INI_SIZE).
        $file = new UploadedFile($path, 'pierre.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->expectException(InvalidMediaException::class);
        $validator->validate($file);
    }

    public function testShortVideoWithinDurationLimitIsAccepted(): void
    {
        $ffprobeStub = $this->createFfprobeStub(durationSeconds: 42.0);
        $validator = new MediaValidatorService(ffprobeBinary: $ffprobeStub);
        $file = $this->makeUploadedFile($this->createMp4Header(), 'pierre.mp4', 'video/mp4');

        $result = $validator->validate($file);

        $this->assertTrue($result->isVideo());
        $this->assertSame(42.0, $result->durationSeconds);
    }

    public function testVideoExceedingDurationLimitIsRejected(): void
    {
        $ffprobeStub = $this->createFfprobeStub(durationSeconds: 61.0);
        $validator = new MediaValidatorService(ffprobeBinary: $ffprobeStub);
        $file = $this->makeUploadedFile($this->createMp4Header(), 'pierre.mp4', 'video/mp4');

        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessage('60 secondes');
        $validator->validate($file);
    }

    public function testMissingFfprobeBinaryRejectsUnverifiableVideo(): void
    {
        // CA-2 : environnement sans ffmpeg (ex. poste de dev) -> la vidéo est acceptée
        // sans vérification de durée plutôt que de bloquer toute publication vidéo.
        $validator = new MediaValidatorService(ffprobeBinary: '/path/does/not/exist/ffprobe');
        $file = $this->makeUploadedFile($this->createMp4Header(), 'pierre.mp4', 'video/mp4');

        $this->expectException(InvalidMediaException::class);
        $validator->validate($file);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createJpeg(): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            // Fallback minimal JPEG SOI/EOI si l'extension GD n'est pas disponible.
            return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
        }

        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($image);
        $content = ob_get_clean();
        imagedestroy($image);

        return (string) $content;
    }

    private function createMp4Header(): string
    {
        // En-tête minimal 'ftyp' suffisant pour que fileinfo détecte video/mp4.
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom" . str_repeat("\x00", 32);
    }

    private function makeUploadedFile(string $content, string $originalName, ?string $declaredMimeType = null): UploadedFile
    {
        $path = $this->writeTmpFile($content);

        return new UploadedFile($path, $originalName, $declaredMimeType, null, true);
    }

    private function writeTmpFile(string $content): string
    {
        $path = $this->tmpDir . '/' . uniqid('media_', true);
        file_put_contents($path, $content);

        return $path;
    }

    private function createFfprobeStub(float $durationSeconds): string
    {
        $isWindows = DIRECTORY_SEPARATOR === '\\';
        $path = $this->tmpDir . ($isWindows ? '/ffprobe_stub.bat' : '/ffprobe_stub.sh');
        file_put_contents($path, $isWindows
            ? "@echo off\r\necho {$durationSeconds}\r\n"
            : "#!/bin/sh\necho {$durationSeconds}\n");
        if (!$isWindows) chmod($path, 0755);

        return $path;
    }
}
