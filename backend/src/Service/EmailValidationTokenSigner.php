<?php



namespace App\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EmailValidationTokenSigner
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
    ) {}

    /**
     * @return array{token: string, expiresAt: DateTimeImmutable}
     */
    public function createSignedToken(string $userId, int $ttlSeconds = 3600): array
    {
        $expiresAt = (new DateTimeImmutable())->modify(sprintf('+%d seconds', $ttlSeconds));

        $payload = [
            'sub' => $userId,
            'exp' => $expiresAt->getTimestamp(),
            'jti' => bin2hex(random_bytes(16)),
        ];

        $encodedPayload = $this->base64UrlEncode((string) json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));

        return [
            'token' => sprintf('%s.%s', $encodedPayload, $signature),
            'expiresAt' => $expiresAt,
        ];
    }

    /**
     * @return array{sub: string, exp: int, jti: string}
     */
    public function decodeAndVerify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        [$encodedPayload, $providedSignature] = $parts;
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        $jsonPayload = $this->base64UrlDecode($encodedPayload);
        $payload = json_decode($jsonPayload, true);
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        $subject = $payload['sub'] ?? null;
        $expiresAt = $payload['exp'] ?? null;
        $jti = $payload['jti'] ?? null;

        if (!is_string($subject) || !is_int($expiresAt) || !is_string($jti)) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        if ($expiresAt < time()) {
            throw new InvalidArgumentException('Lien de validation expiré. Vous pouvez demander un nouveau lien.');
        }

        return [
            'sub' => $subject,
            'exp' => $expiresAt,
            'jti' => $jti,
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $normalized = strtr($value, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Token de validation invalide.');
        }

        return $decoded;
    }
}