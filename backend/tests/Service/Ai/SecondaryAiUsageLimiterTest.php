<?php

namespace App\Tests\Service\Ai;

use App\Exception\CloudflareAiException;
use App\Service\Ai\SecondaryAiUsageLimiter;
use App\Tests\Support\SecondaryAiFixtures;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Uid\Uuid;

/** Real isolated Redis, never the application's database or queue. */
final class SecondaryAiUsageLimiterTest extends TestCase
{
    use SecondaryAiFixtures;
    private static ?Process $server = null;
    private static string $socket;
    private \Redis $redis;

    public static function setUpBeforeClass(): void
    {
        if (!is_executable('/usr/bin/redis-server')) self::markTestSkipped('redis-server is required for the atomic quota integration tests.');
        self::$socket = sys_get_temp_dir() . '/gemlink-ai-quota-' . bin2hex(random_bytes(5)) . '.sock';
        self::$server = new Process(['/usr/bin/redis-server', '--port', '0', '--unixsocket', self::$socket, '--save', '', '--appendonly', 'no']);
        self::$server->start();
        for ($i = 0; $i < 100 && !file_exists(self::$socket); ++$i) usleep(10000);
        if (!file_exists(self::$socket)) throw new \RuntimeException('Isolated Redis could not start: ' . self::$server->getErrorOutput());
    }

    public static function tearDownAfterClass(): void { self::$server?->stop(); }

    protected function setUp(): void
    {
        $this->redis = new \Redis(); $this->redis->connect(self::$socket);
        $this->redis->flushDB(); // This socket belongs exclusively to this test process.
    }

    public function testTwoAttemptLimitSurvivesWorkerReconstruction(): void
    {
        $id = Uuid::v7()->toRfc4122();
        (new SecondaryAiUsageLimiter($this->redis, $this->configuration()))->consume('user:one', $id);
        (new SecondaryAiUsageLimiter($this->redis, $this->configuration()))->consume('user:one', $id);
        $this->expectException(CloudflareAiException::class);
        (new SecondaryAiUsageLimiter($this->redis, $this->configuration()))->consume('user:one', $id);
    }

    public function testPerUserMinuteBudgetAndDistinctUsers(): void
    {
        $limiter = new SecondaryAiUsageLimiter($this->redis, $this->configuration(['perUserMinute' => 1]));
        $limiter->consume('user:one', Uuid::v7()->toRfc4122());
        $limiter->consume('user:two', Uuid::v7()->toRfc4122());
        $this->expectException(CloudflareAiException::class);
        $limiter->consume('user:one', Uuid::v7()->toRfc4122());
    }

    public function testPerUserDayBudget(): void
    {
        $limiter = new SecondaryAiUsageLimiter($this->redis, $this->configuration(['perUserDay' => 1]));
        $limiter->consume('user:one', Uuid::v7()->toRfc4122());
        $this->expectException(CloudflareAiException::class);
        $limiter->consume('user:one', Uuid::v7()->toRfc4122());
    }

    public function testGlobalDailyBudgetCannotBeBypassedByChangingUser(): void
    {
        $limiter = new SecondaryAiUsageLimiter($this->redis, $this->configuration(['dailyQuota' => 1]));
        $limiter->consume('user:one', Uuid::v7()->toRfc4122());
        $this->expectException(CloudflareAiException::class);
        $limiter->consume('user:two', Uuid::v7()->toRfc4122());
    }

    public function testConcurrentConsumersCannotExceedGlobalBudget(): void
    {
        if (!function_exists('pcntl_fork')) self::markTestSkipped('pcntl needed for concurrent quota proof.');
        $pids = [];
        for ($i = 0; $i < 8; ++$i) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $redis = new \Redis(); $redis->connect(self::$socket);
                try { (new SecondaryAiUsageLimiter($redis, $this->configuration(['dailyQuota' => 3])))->consume('user:' . $i, Uuid::v7()->toRfc4122()); exit(0); }
                catch (CloudflareAiException) { exit(10); }
            }
            $pids[] = $pid;
        }
        $accepted = 0;
        foreach ($pids as $pid) { pcntl_waitpid($pid, $status); $code = pcntl_wexitstatus($status); self::assertContains($code, [0, 10]); $accepted += $code === 0 ? 1 : 0; }
        self::assertSame(3, $accepted);
    }
}
