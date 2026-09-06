<?php

namespace App\Tests\Service\Ai;

use App\Service\{AiOrchestrationService, CloudflareAiService};
use App\Tests\Support\SecondaryAiFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\TraceableHttpClient;

final class SecondaryAiWiringTest extends KernelTestCase
{
    use SecondaryAiFixtures;

    public function testComposeKeepsReviewerCredentialsInSymfonyOnly(): void
    {
        $compose = \Symfony\Component\Yaml\Yaml::parseFile(dirname(__DIR__, 4) . '/compose.yaml');
        foreach (['api', 'worker'] as $service) self::assertArrayHasKey('CLOUDFLARE_AI_API_TOKEN', $compose['services'][$service]['environment']);
        foreach ($compose['services'] as $name => $service) {
            if (!in_array($name, ['api', 'worker'], true)) self::assertArrayNotHasKey('CLOUDFLARE_AI_API_TOKEN', $service['environment'] ?? []);
        }
    }

    public function testRealContainerWiresReviewerWithoutEagerRedisAndWithoutProfilerCapture(): void
    {
        self::bootKernel();
        $service = self::getContainer()->get(AiOrchestrationService::class);
        $reviewer = new \ReflectionProperty($service, 'secondaryReviewer');
        $reviewer = $reviewer->getValue($service);
        self::assertInstanceOf(CloudflareAiService::class, $reviewer);
        $http = new \ReflectionProperty($reviewer, 'httpClient');
        self::assertNotInstanceOf(TraceableHttpClient::class, $http->getValue($reviewer));
        $primary = $this->primary(.1);
        self::assertSame($primary, $service->reviewAnalysis($primary, $this->image(), 'image/png', $this->request()->requestId, 'user:test'));
    }
}
