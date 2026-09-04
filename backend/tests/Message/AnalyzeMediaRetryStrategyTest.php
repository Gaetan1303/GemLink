<?php

namespace App\Tests\Messenger;

use App\Messenger\AnalyzeMediaRetryStrategy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * US 3.1 CA-3 : 3 tentatives, délais 30 s / 2 min / 10 min.
 */
final class AnalyzeMediaRetryStrategyTest extends TestCase
{
    private AnalyzeMediaRetryStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new AnalyzeMediaRetryStrategy();
    }

    public function testFirstAttemptWaits30Seconds(): void
    {
        $envelope = new Envelope(new \stdClass());

        $this->assertTrue($this->strategy->isRetryable($envelope));
        $this->assertSame(30_000, $this->strategy->getWaitingTime($envelope));
    }

    public function testSecondAttemptWaits2Minutes(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new RedeliveryStamp(1));

        $this->assertTrue($this->strategy->isRetryable($envelope));
        $this->assertSame(120_000, $this->strategy->getWaitingTime($envelope));
    }

    public function testThirdAttemptWaits10Minutes(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new RedeliveryStamp(2));

        $this->assertTrue($this->strategy->isRetryable($envelope));
        $this->assertSame(600_000, $this->strategy->getWaitingTime($envelope));
    }

    public function testNotRetryableAfterThirdAttempt(): void
    {
        $envelope = (new Envelope(new \stdClass()))->with(new RedeliveryStamp(3));

        $this->assertFalse($this->strategy->isRetryable($envelope));
    }
}
