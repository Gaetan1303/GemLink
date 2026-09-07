<?php

namespace App\Tests\Service\Ai;

use App\Exception\{AiAnalysisException, InvalidMediaException};
use App\Service\Media\{AiImageSanitizer, AiMediaReader};
use App\Tests\Support\SecondaryAiFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiImageAndMediaSecurityTest extends TestCase
{
    use SecondaryAiFixtures;

    public function testRemovesImageMetadataAndTrailingBytes(): void
    {
        $jpeg = $this->image('image/jpeg');
        $comment = 'private user notes and instructions';
        $jpeg = substr($jpeg, 0, 2) . "\xFF\xFE" . pack('n', strlen($comment) + 2) . $comment . substr($jpeg, 2) . 'TRAILING SECRET';
        [$clean, $mime] = (new AiImageSanitizer())->sanitize($jpeg);
        self::assertSame('image/jpeg', $mime);
        self::assertStringNotContainsString($comment, $clean);
        self::assertStringNotContainsString('TRAILING SECRET', $clean);
        self::assertNotFalse(imagecreatefromstring($clean));
    }

    public function testUploadStoresOnlyCleanBytesAndReportsActualSize(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'gemlink-clean-upload-');
        file_put_contents($path, $this->image() . 'PRIVATE_TRAILER');
        $uploader = $this->createMock(\App\Service\Media\MediaUploaderInterface::class);
        $uploader->expects(self::once())->method('upload')->willReturnCallback(function ($file) {
            self::assertStringNotContainsString('PRIVATE_TRAILER', file_get_contents($file->getPathname()));
            return 'https://media.example.test/x.png';
        });
        $service = new \App\Service\Media\MediaUploadService(new \App\Service\Media\MediaValidatorService(), $uploader, new AiImageSanitizer());
        try {
            $result = $service->uploadPublicImage(new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true), 'identifications');
            self::assertSame('image/png', $result->mimeType);
            self::assertSame(strlen(file_get_contents($path)), $result->sizeBytes);
        } finally { unlink($path); }
    }

    public function testOversizedDimensionsRejectedBeforeDecode(): void
    {
        $png = $this->image();
        $png = substr_replace($png, pack('NN', 20000, 20000), 16, 8);
        $this->expectException(InvalidMediaException::class);
        $this->expectExceptionMessage('Dimensions');
        (new AiImageSanitizer())->sanitize($png);
    }

    public function testOversizedBytesRejected(): void
    {
        $this->expectException(InvalidMediaException::class);
        (new AiImageSanitizer())->sanitize($this->image() . str_repeat('a', 10 * 1024 * 1024));
    }

    public static function badUrls(): iterable
    {
        foreach (['http://127.0.0.1/x.png', 'http://169.254.169.254/x.png', 'https://localhost/x.png', 'https://redis/x.png',
            'https://8.8.8.8.evil.test/x.png', 'https://8.8.8.8@127.0.0.1/x.png', 'https://8.8.8.8/../x.png',
            'https://8.8.8.8/%2e%2e/x.png', 'https://8.8.8.8/x.png?url=http://localhost', 'file:///etc/passwd'] as $url) yield [$url];
    }

    #[DataProvider('badUrls')]
    public function testRejectsSsrfBeforeHttp(string $url): void
    {
        $http = new MockHttpClient();
        $reader = new AiMediaReader($http, new AiImageSanitizer(), 'r2', '/tmp', 'http://localhost/uploads', 'https://8.8.8.8');
        try { $reader->read($url); self::fail(); } catch (AiAnalysisException) { self::assertSame(0, $http->getRequestsCount()); }
    }

    public function testAllowlistedPrivateIpStillRejected(): void
    {
        $http = new MockHttpClient();
        $reader = new AiMediaReader($http, new AiImageSanitizer(), 'r2', '/tmp', 'http://localhost/uploads', 'https://127.0.0.1');
        try { $reader->read('https://127.0.0.1/x.png'); self::fail(); }
        catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface) { self::assertSame(0, $http->getRequestsCount()); }
    }

    public function testCdnRedirectNotFollowed(): void
    {
        $http = new MockHttpClient(new MockResponse('', ['http_code' => 302, 'response_headers' => ['location: http://127.0.0.1/']]));
        $reader = new AiMediaReader($http, new AiImageSanitizer(), 'r2', '/tmp', 'http://localhost/uploads', 'https://8.8.8.8');
        try { $reader->read('https://8.8.8.8/x.png'); self::fail(); } catch (AiAnalysisException) { self::assertSame(1, $http->getRequestsCount()); }
    }

    public function testLocalSymlinkOutsideUploadDirectoryRejected(): void
    {
        $dir = sys_get_temp_dir() . '/gemlink-safe-media-' . bin2hex(random_bytes(5)); mkdir($dir);
        symlink('/etc/passwd', $dir . '/escape.png');
        try {
            $reader = new AiMediaReader(new MockHttpClient(), new AiImageSanitizer(), 'local', $dir, 'http://localhost/uploads', '');
            $this->expectException(AiAnalysisException::class);
            $reader->read('http://localhost/uploads/escape.png');
        } finally { unlink($dir . '/escape.png'); rmdir($dir); }
    }
}
