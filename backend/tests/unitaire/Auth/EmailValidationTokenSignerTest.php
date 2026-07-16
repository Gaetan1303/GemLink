<?php



namespace App\Tests\Unitaire\Auth;

use App\Service\EmailValidationTokenSigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailValidationTokenSignerTest extends TestCase
{
    public function testCreateAndVerifySignedToken(): void
    {
        $signer = new EmailValidationTokenSigner('test-secret');

        $result = $signer->createSignedToken('user-id-123');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $result['token']);
        $this->assertSame('user-id-123', $signer->decodeAndVerify($result['token'])['sub']);
        $this->assertGreaterThan(time(), $result['expiresAt']->getTimestamp());
        $this->assertLessThanOrEqual(time() + 3600, $result['expiresAt']->getTimestamp());
    }

    public function testVerifyRejectsTamperedToken(): void
    {
        $signer = new EmailValidationTokenSigner('test-secret');
        $result = $signer->createSignedToken('user-id-123');

        $parts = explode('.', $result['token']);
        $parts[0] = strrev($parts[0]);

        $this->expectException(InvalidArgumentException::class);
        $signer->decodeAndVerify(implode('.', $parts));
    }
}
