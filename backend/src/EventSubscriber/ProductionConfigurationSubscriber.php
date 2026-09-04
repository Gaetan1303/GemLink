<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelInterface;

/** Fails closed when a production deployment is missing mandatory secrets. */
#[AsEventListener(event: 'kernel.request', priority: 4096)]
final class ProductionConfigurationSubscriber
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly string $appSecret,
        private readonly string $jwtPassphrase,
        private readonly string $mailerDsn,
        private readonly string $internalApiKey,
        private readonly string $mediaStorageMode,
        private readonly string $r2AccessKeyId,
        private readonly string $r2AccountId,
        private readonly string $r2SecretAccessKey,
        private readonly string $r2Bucket,
        private readonly string $r2Endpoint,
        private readonly string $r2PublicBaseUrl,
        private readonly string $corsAllowOrigin,
        private readonly string $frontendUrl,
        private readonly string $aiServiceUrl,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || 'prod' !== $this->kernel->getEnvironment()) {
            return;
        }

        $missing = [];
        $this->requireMinimumLength($missing, 'APP_SECRET', $this->appSecret, 32);
        $this->requireMinimumLength($missing, 'JWT_PASSPHRASE', $this->jwtPassphrase, 12);
        $this->requireMinimumLength($missing, 'INTERNAL_API_KEY', $this->internalApiKey, 32);
        $this->requireNonEmpty($missing, 'MAILER_DSN', $this->mailerDsn);
        if ($this->kernel->isDebug()) {
            $missing[] = 'APP_DEBUG=0';
        }
        if (!str_starts_with($this->frontendUrl, 'https://')) {
            $missing[] = 'FRONTEND_URL=https://...';
        }
        if (!str_starts_with($this->corsAllowOrigin, '^https://') || str_contains($this->corsAllowOrigin, '.*')) {
            $missing[] = 'CORS_ALLOW_ORIGIN anchored HTTPS regex';
        }
        $aiHost = parse_url($this->aiServiceUrl, PHP_URL_HOST);
        if ('http' !== parse_url($this->aiServiceUrl, PHP_URL_SCHEME) || !is_string($aiHost) || !str_ends_with($aiHost, '.railway.internal')) {
            $missing[] = 'AI_SERVICE_URL private Railway URL';
        }

        if ('r2' !== $this->mediaStorageMode) {
            $missing[] = 'MEDIA_STORAGE_MODE=r2';
        } else {
            $this->requireNonEmpty($missing, 'R2_ACCESS_KEY_ID', $this->r2AccessKeyId);
            $this->requireNonEmpty($missing, 'R2_ACCOUNT_ID', $this->r2AccountId);
            $this->requireMinimumLength($missing, 'R2_SECRET_ACCESS_KEY', $this->r2SecretAccessKey, 32);
            $this->requireNonEmpty($missing, 'R2_BUCKET', $this->r2Bucket);
            $this->requireNonEmpty($missing, 'R2_ENDPOINT', $this->r2Endpoint);
            $this->requireNonEmpty($missing, 'R2_PUBLIC_BASE_URL', $this->r2PublicBaseUrl);
            if (!str_starts_with($this->r2Endpoint, 'https://') || !str_starts_with($this->r2PublicBaseUrl, 'https://')) {
                $missing[] = 'R2 HTTPS endpoints';
            }
        }

        if ([] !== $missing) {
            // Never include values: this exception may be collected by Railway.
            throw new \RuntimeException('Invalid production configuration: '.implode(', ', $missing));
        }
    }

    /** @param list<string> $missing */
    private function requireNonEmpty(array &$missing, string $name, string $value): void
    {
        if ('' === trim($value)) {
            $missing[] = $name;
        }
    }

    /** @param list<string> $missing */
    private function requireMinimumLength(array &$missing, string $name, string $value, int $length): void
    {
        if (strlen($value) < $length) {
            $missing[] = $name;
        }
    }
}
