<?php

namespace Tests\Feature\Services;

use App\DTOs\WarmUrlResult;
use App\Enums\WarmSiteMode;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WarmingServiceTest extends TestCase
{
    use RefreshDatabase;

    private WarmingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WarmingService::class);
    }

    public function test_resolve_urls_from_manual_list(): void
    {
        $site = WarmSite::factory()->create([
            'mode' => WarmSiteMode::URLS,
            'urls' => [
                'https://example.com/',
                'https://example.com/about',
                'https://example.com/contact',
            ],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(3, $urls);
        $this->assertContains('https://example.com/', $urls);
        $this->assertContains('https://example.com/about', $urls);
        $this->assertContains('https://example.com/contact', $urls);
    }

    public function test_resolve_urls_from_sitemap(): void
    {
        $sitemapXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://example.com/</loc></url>
    <url><loc>https://example.com/about</loc></url>
    <url><loc>https://example.com/contact</loc></url>
</urlset>
XML;

        Http::fake([
            '*example.com*' => Http::response($sitemapXml, 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);

        // Set sitemap_url explicitly so Http::fake can match a predictable URL.
        // The factory sitemap() state derives sitemap_url from $attrs['domain']
        // before create() overrides are applied, so we must set both together.
        $site = WarmSite::factory()->sitemap()->create([
            'domain' => 'example.com',
            'sitemap_url' => 'https://example.com/sitemap.xml',
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(3, $urls);
        $this->assertContains('https://example.com/', $urls);
        $this->assertContains('https://example.com/about', $urls);
        $this->assertContains('https://example.com/contact', $urls);
    }

    public function test_resolve_urls_respects_max_urls(): void
    {
        $allUrls = [];
        for ($i = 1; $i <= 100; $i++) {
            $allUrls[] = "https://example.com/page-{$i}";
        }

        $site = WarmSite::factory()->create([
            'mode' => WarmSiteMode::URLS,
            'urls' => $allUrls,
            'max_urls' => 10,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(10, $urls);
    }

    public function test_resolve_urls_deduplicates(): void
    {
        // All three variants should collapse to one unique URL
        $site = WarmSite::factory()->create([
            'mode' => WarmSiteMode::URLS,
            'urls' => [
                'https://example.com/page',
                'https://example.com/page/',       // trailing slash variant
                'https://www.example.com/page',    // www prefix variant
            ],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(1, $urls);
    }

    public function test_warm_url_detects_cloudflare_hit(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('Hello', 200, [
                'cf-cache-status' => 'HIT',
            ]),
        ]);

        $result = $this->service->warmUrl('https://example.com/');

        $this->assertInstanceOf(WarmUrlResult::class, $result);
        $this->assertEquals('hit', $result->cacheStatus);
        $this->assertEquals(200, $result->statusCode);
        $this->assertTrue($result->isHit());
        $this->assertFalse($result->isError());
    }

    public function test_warm_url_detects_cache_miss(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('Hello', 200, [
                'cf-cache-status' => 'MISS',
            ]),
        ]);

        $result = $this->service->warmUrl('https://example.com/');

        $this->assertEquals('miss', $result->cacheStatus);
        $this->assertTrue($result->isMiss());
        $this->assertFalse($result->isHit());
        $this->assertFalse($result->isError());
    }

    public function test_warm_url_handles_timeout(): void
    {
        Http::fake([
            'https://example.com/' => function () {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $result = $this->service->warmUrl('https://example.com/');

        $this->assertInstanceOf(WarmUrlResult::class, $result);
        $this->assertTrue($result->isError());
        $this->assertNotNull($result->errorMessage);
        $this->assertEquals('unknown', $result->cacheStatus);
        $this->assertEquals(0, $result->statusCode);
    }

    public function test_warm_url_returns_unknown_without_cache_headers(): void
    {
        Http::fake([
            'https://example.com/' => Http::response('Hello', 200, []),
        ]);

        $result = $this->service->warmUrl('https://example.com/');

        $this->assertEquals('unknown', $result->cacheStatus);
        $this->assertEquals(200, $result->statusCode);
        $this->assertFalse($result->isError());
        $this->assertFalse($result->isHit());
        $this->assertFalse($result->isMiss());
    }

    public function test_warm_url_sends_custom_headers(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->service->warmUrl('https://example.com/page', ['X-Custom' => 'test-value']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Custom', 'test-value');
        });
    }

    public function test_warm_url_blocks_dangerous_headers(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->service->warmUrl('https://example.com/page', [
            'X-Safe' => 'allowed',
            'Host' => 'evil.com',
            'Cookie' => 'session=stolen',
        ]);

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Safe', 'allowed')
                && ! $request->hasHeader('Cookie', 'session=stolen');
        });
    }

    public function test_resolve_urls_rejects_private_ips(): void
    {
        $site = WarmSite::factory()->create([
            'mode' => WarmSiteMode::URLS,
            'urls' => [
                'https://example.com/safe',
                'http://127.0.0.1/admin',
                'http://169.254.169.254/latest/meta-data/',
                'http://192.168.1.1/router',
                'http://10.0.0.1/internal',
            ],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        // Only the safe public URL should remain
        $this->assertCount(1, $urls);
        $this->assertContains('https://example.com/safe', $urls);
        $this->assertNotContains('http://127.0.0.1/admin', $urls);
        $this->assertNotContains('http://169.254.169.254/latest/meta-data/', $urls);
        $this->assertNotContains('http://192.168.1.1/router', $urls);
        $this->assertNotContains('http://10.0.0.1/internal', $urls);
    }
}
