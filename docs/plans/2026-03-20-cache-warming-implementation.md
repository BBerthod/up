# Cache Warming — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a standalone Cache Warming section to Up that periodically visits site URLs to keep CDN/application caches hot.

**Architecture:** Two new models (WarmSite, WarmRun), a WarmingService for business logic, two queue jobs (DispatchWarmRuns/RunWarmSite), a resource controller with Inertia Vue pages, following existing patterns exactly.

**Tech Stack:** Laravel 12, Vue 3 + Inertia v2, PrimeVue, PostgreSQL, Redis queues

**Design doc:** `docs/plans/2026-03-20-cache-warming-design.md`

---

### Task 1: Enums

**Files:**
- Create: `app/Enums/WarmSiteMode.php`
- Create: `app/Enums/WarmRunStatus.php`

**Step 1: Create WarmSiteMode enum**

```php
<?php

namespace App\Enums;

enum WarmSiteMode: string
{
    case URLS = 'urls';
    case SITEMAP = 'sitemap';

    public function label(): string
    {
        return match ($this) {
            self::URLS => 'Manual URLs',
            self::SITEMAP => 'Sitemap',
        };
    }
}
```

**Step 2: Create WarmRunStatus enum**

```php
<?php

namespace App\Enums;

enum WarmRunStatus: string
{
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
```

**Step 3: Commit**

```bash
git add app/Enums/WarmSiteMode.php app/Enums/WarmRunStatus.php
git commit -m "feat(warming): add WarmSiteMode and WarmRunStatus enums."
```

---

### Task 2: Migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_warm_sites_table.php`
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_warm_runs_table.php`

**Step 1: Create warm_sites migration**

```bash
php artisan make:migration create_warm_sites_table
```

Content:
```php
Schema::create('warm_sites', function (Blueprint $table) {
    $table->id();
    $table->foreignId('team_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('domain');
    $table->string('mode'); // WarmSiteMode enum
    $table->string('sitemap_url')->nullable();
    $table->json('urls')->nullable();
    $table->unsignedSmallInteger('frequency_minutes')->default(60);
    $table->unsignedSmallInteger('max_urls')->default(50);
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_warmed_at')->nullable();
    $table->timestamps();

    $table->index(['team_id', 'is_active']);
    $table->index('last_warmed_at');
    $table->unique(['team_id', 'domain']);
});
```

**Step 2: Create warm_runs migration**

```bash
php artisan make:migration create_warm_runs_table
```

Content:
```php
Schema::create('warm_runs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('warm_site_id')->constrained()->cascadeOnDelete();
    $table->unsignedSmallInteger('urls_total')->default(0);
    $table->unsignedSmallInteger('urls_hit')->default(0);
    $table->unsignedSmallInteger('urls_miss')->default(0);
    $table->unsignedSmallInteger('urls_error')->default(0);
    $table->unsignedSmallInteger('avg_response_ms')->default(0);
    $table->string('status'); // WarmRunStatus enum
    $table->text('error_message')->nullable();
    $table->timestamp('started_at');
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();

    $table->index(['warm_site_id', 'created_at']);
});
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*warm*
git commit -m "feat(warming): add warm_sites and warm_runs tables."
```

---

### Task 3: Models + Factories

**Files:**
- Create: `app/Models/WarmSite.php`
- Create: `app/Models/WarmRun.php`
- Create: `database/factories/WarmSiteFactory.php`
- Create: `database/factories/WarmRunFactory.php`

**Step 1: Create WarmSite model**

```php
<?php

namespace App\Models;

use App\Enums\WarmSiteMode;
use App\Models\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarmSite extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'mode',
        'sitemap_url',
        'urls',
        'frequency_minutes',
        'max_urls',
        'is_active',
        'last_warmed_at',
    ];

    protected $casts = [
        'mode' => WarmSiteMode::class,
        'urls' => 'array',
        'frequency_minutes' => 'integer',
        'max_urls' => 'integer',
        'is_active' => 'boolean',
        'last_warmed_at' => 'datetime',
    ];

    public function warmRuns(): HasMany
    {
        return $this->hasMany(WarmRun::class);
    }

    public function latestRun(): HasMany
    {
        return $this->hasMany(WarmRun::class)->one()->latestOfMany();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForWarming($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_warmed_at')
                    ->orWhereRaw("last_warmed_at <= now() - make_interval(mins => frequency_minutes)");
            });
    }

    public function getResolvedSitemapUrlAttribute(): string
    {
        return $this->sitemap_url ?? "https://{$this->domain}/sitemap.xml";
    }
}
```

**Step 2: Create WarmRun model**

```php
<?php

namespace App\Models;

use App\Enums\WarmRunStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'warm_site_id',
        'urls_total',
        'urls_hit',
        'urls_miss',
        'urls_error',
        'avg_response_ms',
        'status',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => WarmRunStatus::class,
        'urls_total' => 'integer',
        'urls_hit' => 'integer',
        'urls_miss' => 'integer',
        'urls_error' => 'integer',
        'avg_response_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function warmSite(): BelongsTo
    {
        return $this->belongsTo(WarmSite::class);
    }

    public function getHitRatioAttribute(): float
    {
        if ($this->urls_total === 0) {
            return 0;
        }

        return round(($this->urls_hit / $this->urls_total) * 100, 1);
    }

    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->completed_at || ! $this->started_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at);
    }
}
```

**Step 3: Create WarmSiteFactory**

```php
<?php

namespace Database\Factories;

use App\Enums\WarmSiteMode;
use App\Models\Team;
use App\Models\WarmSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarmSiteFactory extends Factory
{
    protected $model = WarmSite::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->words(2, true),
            'domain' => fake()->domainName(),
            'mode' => WarmSiteMode::URLS,
            'sitemap_url' => null,
            'urls' => ['https://example.com/page1', 'https://example.com/page2'],
            'frequency_minutes' => 60,
            'max_urls' => 50,
            'is_active' => true,
            'last_warmed_at' => null,
        ];
    }

    public function sitemap(): static
    {
        return $this->state(fn (array $attrs) => [
            'mode' => WarmSiteMode::SITEMAP,
            'sitemap_url' => 'https://' . $attrs['domain'] . '/sitemap.xml',
            'urls' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function dueForWarming(): static
    {
        return $this->state(fn () => [
            'last_warmed_at' => now()->subHours(2),
            'frequency_minutes' => 60,
        ]);
    }
}
```

**Step 4: Create WarmRunFactory**

```php
<?php

namespace Database\Factories;

use App\Enums\WarmRunStatus;
use App\Models\WarmRun;
use App\Models\WarmSite;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarmRunFactory extends Factory
{
    protected $model = WarmRun::class;

    public function definition(): array
    {
        $total = fake()->numberBetween(10, 50);
        $hit = fake()->numberBetween(0, $total);
        $miss = $total - $hit;

        return [
            'warm_site_id' => WarmSite::factory(),
            'urls_total' => $total,
            'urls_hit' => $hit,
            'urls_miss' => $miss,
            'urls_error' => 0,
            'avg_response_ms' => fake()->numberBetween(100, 800),
            'status' => WarmRunStatus::COMPLETED,
            'error_message' => null,
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => WarmRunStatus::RUNNING,
            'completed_at' => null,
        ]);
    }

    public function failed(string $message = 'Sitemap fetch failed'): static
    {
        return $this->state(fn () => [
            'status' => WarmRunStatus::FAILED,
            'error_message' => $message,
        ]);
    }
}
```

**Step 5: Commit**

```bash
git add app/Models/WarmSite.php app/Models/WarmRun.php database/factories/WarmSiteFactory.php database/factories/WarmRunFactory.php
git commit -m "feat(warming): add WarmSite and WarmRun models with factories."
```

---

### Task 4: DTO + WarmingService

**Files:**
- Create: `app/DTOs/WarmUrlResult.php`
- Create: `app/Services/WarmingService.php`
- Test: `tests/Feature/Services/WarmingServiceTest.php`

**Step 1: Create WarmUrlResult DTO**

```php
<?php

namespace App\DTOs;

readonly class WarmUrlResult
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public string $cacheStatus, // 'hit', 'miss', 'unknown'
        public int $responseTimeMs,
        public ?string $errorMessage = null,
    ) {}

    public function isHit(): bool
    {
        return $this->cacheStatus === 'hit';
    }

    public function isMiss(): bool
    {
        return $this->cacheStatus === 'miss';
    }

    public function isError(): bool
    {
        return $this->errorMessage !== null;
    }
}
```

**Step 2: Write WarmingService tests first**

Create `tests/Feature/Services/WarmingServiceTest.php`:

```php
<?php

namespace Tests\Feature\Services;

use App\DTOs\WarmUrlResult;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'mode' => 'urls',
            'urls' => ['https://example.com/a', 'https://example.com/b'],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(2, $urls);
        $this->assertContains('https://example.com/a', $urls);
    }

    public function test_resolve_urls_from_sitemap(): void
    {
        Http::fake([
            'example.com/sitemap.xml' => Http::response(
                '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/page1</loc></url><url><loc>https://example.com/page2</loc></url></urlset>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $site = WarmSite::factory()->sitemap()->create([
            'domain' => 'example.com',
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(2, $urls);
        $this->assertContains('https://example.com/page1', $urls);
    }

    public function test_resolve_urls_respects_max_urls(): void
    {
        $site = WarmSite::factory()->create([
            'mode' => 'urls',
            'urls' => array_map(fn ($i) => "https://example.com/page{$i}", range(1, 100)),
            'max_urls' => 10,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(10, $urls);
    }

    public function test_resolve_urls_deduplicates(): void
    {
        $site = WarmSite::factory()->create([
            'mode' => 'urls',
            'urls' => [
                'https://example.com/page',
                'https://example.com/page/',
                'https://www.example.com/page',
            ],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(1, $urls);
    }

    public function test_warm_url_detects_cloudflare_hit(): void
    {
        Http::fake([
            'example.com/*' => Http::response('ok', 200, [
                'cf-cache-status' => 'HIT',
            ]),
        ]);

        $result = $this->service->warmUrl('https://example.com/page');

        $this->assertEquals('hit', $result->cacheStatus);
        $this->assertEquals(200, $result->statusCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_warm_url_detects_cache_miss(): void
    {
        Http::fake([
            'example.com/*' => Http::response('ok', 200, [
                'cf-cache-status' => 'MISS',
            ]),
        ]);

        $result = $this->service->warmUrl('https://example.com/page');

        $this->assertEquals('miss', $result->cacheStatus);
    }

    public function test_warm_url_handles_timeout(): void
    {
        Http::fake([
            'example.com/*' => Http::throw(new \Illuminate\Http\Client\ConnectionException('timeout')),
        ]);

        $result = $this->service->warmUrl('https://example.com/page');

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('timeout', $result->errorMessage);
    }

    public function test_warm_url_returns_unknown_without_cache_headers(): void
    {
        Http::fake([
            'example.com/*' => Http::response('ok', 200),
        ]);

        $result = $this->service->warmUrl('https://example.com/page');

        $this->assertEquals('unknown', $result->cacheStatus);
    }

    public function test_resolve_urls_rejects_private_ips(): void
    {
        $site = WarmSite::factory()->create([
            'mode' => 'urls',
            'urls' => [
                'https://example.com/safe',
                'http://127.0.0.1/admin',
                'http://169.254.169.254/metadata',
            ],
            'max_urls' => 50,
        ]);

        $urls = $this->service->resolveUrls($site);

        $this->assertCount(1, $urls);
        $this->assertContains('https://example.com/safe', $urls);
    }
}
```

**Step 3: Run tests to verify they fail**

```bash
vendor/bin/phpunit tests/Feature/Services/WarmingServiceTest.php
```

Expected: FAIL (WarmingService class not found)

**Step 4: Implement WarmingService**

Create `app/Services/WarmingService.php`:

```php
<?php

namespace App\Services;

use App\DTOs\WarmUrlResult;
use App\Enums\WarmSiteMode;
use App\Models\WarmSite;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarmingService
{
    public function resolveUrls(WarmSite $site): array
    {
        $urls = $site->mode === WarmSiteMode::SITEMAP
            ? $this->parseSitemap($site->resolved_sitemap_url)
            : ($site->urls ?? []);

        $urls = $this->deduplicateUrls($urls);
        $urls = $this->filterSafeUrls($urls);

        if (count($urls) > $site->max_urls) {
            shuffle($urls);
            $urls = array_slice($urls, 0, $site->max_urls);
        }

        return array_values($urls);
    }

    public function warmUrl(string $url): WarmUrlResult
    {
        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withoutVerifying()
                ->maxRedirects(3)
                ->withHeaders([
                    'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Cache-Control' => 'max-age=0',
                ])
                ->get($url);

            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);
            $cacheStatus = $this->detectCacheStatus($response->headers());

            return new WarmUrlResult(
                url: $url,
                statusCode: $response->status(),
                cacheStatus: $cacheStatus,
                responseTimeMs: $responseTimeMs,
            );
        } catch (ConnectionException $e) {
            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);

            return new WarmUrlResult(
                url: $url,
                statusCode: 0,
                cacheStatus: 'unknown',
                responseTimeMs: $responseTimeMs,
                errorMessage: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);

            return new WarmUrlResult(
                url: $url,
                statusCode: 0,
                cacheStatus: 'unknown',
                responseTimeMs: $responseTimeMs,
                errorMessage: $e->getMessage(),
            );
        }
    }

    private function parseSitemap(string $sitemapUrl): array
    {
        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
                ])
                ->get($sitemapUrl);

            if (! $response->successful()) {
                Log::warning('Sitemap fetch failed', ['url' => $sitemapUrl, 'status' => $response->status()]);

                return [];
            }

            $body = $response->body();

            if (strlen($body) > 5 * 1024 * 1024) {
                Log::warning('Sitemap too large', ['url' => $sitemapUrl, 'size' => strlen($body)]);

                return [];
            }

            libxml_use_internal_errors(true);
            $xml = new \SimpleXMLElement($body, LIBXML_NONET);
            $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            $locs = $xml->xpath('//sm:url/sm:loc') ?: $xml->xpath('//url/loc') ?: [];

            return array_map(fn ($loc) => (string) $loc, $locs);
        } catch (\Throwable $e) {
            Log::error('Sitemap parse error', ['url' => $sitemapUrl, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function detectCacheStatus(array $headers): string
    {
        // Cloudflare
        $cfStatus = $headers['cf-cache-status'][0] ?? null;
        if ($cfStatus) {
            return in_array(strtoupper($cfStatus), ['HIT', 'STALE']) ? 'hit' : 'miss';
        }

        // Nginx
        $nginxStatus = $headers['X-Cache-Status'][0] ?? $headers['x-cache-status'][0] ?? null;
        if ($nginxStatus) {
            return strtoupper($nginxStatus) === 'HIT' ? 'hit' : 'miss';
        }

        // Varnish / CloudFront / Generic
        $xCache = $headers['X-Cache'][0] ?? $headers['x-cache'][0] ?? null;
        if ($xCache) {
            return stripos($xCache, 'hit') !== false ? 'hit' : 'miss';
        }

        // Age header heuristic
        $age = $headers['Age'][0] ?? $headers['age'][0] ?? null;
        if ($age !== null && (int) $age > 0) {
            return 'hit';
        }

        return 'unknown';
    }

    private function deduplicateUrls(array $urls): array
    {
        $normalized = [];

        foreach ($urls as $url) {
            $key = $this->normalizeUrl($url);
            if ($key && ! isset($normalized[$key])) {
                $normalized[$key] = $url;
            }
        }

        return array_values($normalized);
    }

    private function normalizeUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return null;
        }

        $host = strtolower(preg_replace('/^www\./', '', $parsed['host']));
        $path = rtrim($parsed['path'] ?? '/', '/') ?: '/';
        $scheme = $parsed['scheme'] ?? 'https';

        return "{$scheme}://{$host}{$path}";
    }

    private function filterSafeUrls(array $urls): array
    {
        return array_values(array_filter($urls, function (string $url) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '';

            // Reject localhost
            if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
                return false;
            }

            // Reject private IPs
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            // Reject cloud metadata
            if ($ip === '169.254.169.254') {
                return false;
            }

            return true;
        }));
    }
}
```

**Step 5: Run tests**

```bash
vendor/bin/phpunit tests/Feature/Services/WarmingServiceTest.php
```

Expected: ALL PASS

**Step 6: Commit**

```bash
git add app/DTOs/WarmUrlResult.php app/Services/WarmingService.php tests/Feature/Services/WarmingServiceTest.php
git commit -m "feat(warming): add WarmingService with sitemap parsing, cache detection, SSRF protection."
```

---

### Task 5: Jobs

**Files:**
- Create: `app/Jobs/DispatchWarmRuns.php`
- Create: `app/Jobs/RunWarmSite.php`
- Test: `tests/Feature/Jobs/DispatchWarmRunsTest.php`
- Test: `tests/Feature/Jobs/RunWarmSiteTest.php`

**Step 1: Write DispatchWarmRuns test**

Create `tests/Feature/Jobs/DispatchWarmRunsTest.php`:

```php
<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DispatchWarmRuns;
use App\Jobs\RunWarmSite;
use App\Models\WarmSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DispatchWarmRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_jobs_for_due_sites(): void
    {
        Queue::fake();

        $due = WarmSite::factory()->dueForWarming()->create();
        $notDue = WarmSite::factory()->create(['last_warmed_at' => now()]);
        $inactive = WarmSite::factory()->inactive()->create();

        (new DispatchWarmRuns)->handle();

        Queue::assertPushed(RunWarmSite::class, 1);
        Queue::assertPushed(RunWarmSite::class, fn ($job) => $job->warmSite->id === $due->id);
    }

    public function test_dispatches_for_never_warmed_sites(): void
    {
        Queue::fake();

        WarmSite::factory()->create(['last_warmed_at' => null]);

        (new DispatchWarmRuns)->handle();

        Queue::assertPushed(RunWarmSite::class, 1);
    }
}
```

**Step 2: Implement DispatchWarmRuns**

```php
<?php

namespace App\Jobs;

use App\Models\WarmSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWarmRuns implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $sites = WarmSite::withoutGlobalScopes()
            ->dueForWarming()
            ->get();

        foreach ($sites as $site) {
            RunWarmSite::dispatch($site);
        }
    }
}
```

**Step 3: Write RunWarmSite test**

Create `tests/Feature/Jobs/RunWarmSiteTest.php`:

```php
<?php

namespace Tests\Feature\Jobs;

use App\Enums\WarmRunStatus;
use App\Jobs\RunWarmSite;
use App\Models\WarmRun;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RunWarmSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_warm_run_with_stats(): void
    {
        Http::fake([
            'example.com/page1' => Http::response('ok', 200, ['cf-cache-status' => 'HIT']),
            'example.com/page2' => Http::response('ok', 200, ['cf-cache-status' => 'MISS']),
        ]);

        $site = WarmSite::factory()->create([
            'mode' => 'urls',
            'urls' => ['https://example.com/page1', 'https://example.com/page2'],
        ]);

        (new RunWarmSite($site))->handle(app(WarmingService::class));

        $this->assertDatabaseHas('warm_runs', [
            'warm_site_id' => $site->id,
            'urls_total' => 2,
            'urls_hit' => 1,
            'urls_miss' => 1,
            'urls_error' => 0,
            'status' => 'completed',
        ]);

        $site->refresh();
        $this->assertNotNull($site->last_warmed_at);
    }

    public function test_handles_errors_gracefully(): void
    {
        Http::fake([
            'example.com/ok' => Http::response('ok', 200),
            'example.com/fail' => Http::throw(new \Illuminate\Http\Client\ConnectionException('timeout')),
        ]);

        $site = WarmSite::factory()->create([
            'mode' => 'urls',
            'urls' => ['https://example.com/ok', 'https://example.com/fail'],
        ]);

        (new RunWarmSite($site))->handle(app(WarmingService::class));

        $run = WarmRun::where('warm_site_id', $site->id)->first();
        $this->assertEquals(WarmRunStatus::COMPLETED, $run->status);
        $this->assertEquals(1, $run->urls_error);
    }

    public function test_skips_if_lock_unavailable(): void
    {
        $site = WarmSite::factory()->create();

        // Acquire lock manually
        $lock = Cache::lock("warming:{$site->id}", 120);
        $lock->get();

        Http::fake();

        (new RunWarmSite($site))->handle(app(WarmingService::class));

        $this->assertDatabaseMissing('warm_runs', ['warm_site_id' => $site->id]);

        $lock->release();
    }
}
```

**Step 4: Implement RunWarmSite**

```php
<?php

namespace App\Jobs;

use App\Enums\WarmRunStatus;
use App\Models\WarmRun;
use App\Models\WarmSite;
use App\Services\WarmingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunWarmSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [300, 3600];

    public function __construct(public WarmSite $warmSite) {}

    public function retryUntil(): \DateTime
    {
        $seconds = $this->warmSite->max_urls * 15;
        $seconds = max(120, min($seconds, 7200));

        return now()->addSeconds($seconds);
    }

    public function handle(WarmingService $warmingService): void
    {
        $lock = Cache::lock("warming:{$this->warmSite->id}", 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $run = WarmRun::create([
                'warm_site_id' => $this->warmSite->id,
                'status' => WarmRunStatus::RUNNING,
                'started_at' => now(),
            ]);

            $urls = $warmingService->resolveUrls($this->warmSite);

            if (empty($urls)) {
                $run->update([
                    'status' => WarmRunStatus::FAILED,
                    'error_message' => 'No URLs to warm (sitemap empty or fetch failed)',
                    'completed_at' => now(),
                ]);
                $this->warmSite->update(['last_warmed_at' => now()]);

                return;
            }

            $hits = 0;
            $misses = 0;
            $errors = 0;
            $totalMs = 0;
            $consecutiveErrors = 0;

            foreach ($urls as $i => $url) {
                if ($i > 0) {
                    usleep(1_000_000); // 1 second between requests
                }

                $result = $warmingService->warmUrl($url);

                if ($result->isError()) {
                    $errors++;
                    $consecutiveErrors++;

                    // Stop early if 3 consecutive errors (site likely down)
                    if ($consecutiveErrors >= 3) {
                        $run->update([
                            'urls_total' => $i + 1,
                            'urls_hit' => $hits,
                            'urls_miss' => $misses,
                            'urls_error' => $errors,
                            'avg_response_ms' => ($i + 1 - $errors) > 0 ? (int) ($totalMs / ($i + 1 - $errors)) : 0,
                            'status' => WarmRunStatus::COMPLETED,
                            'error_message' => "Stopped early: 3 consecutive errors. Last: {$result->errorMessage}",
                            'completed_at' => now(),
                        ]);
                        $this->warmSite->update(['last_warmed_at' => now()]);

                        return;
                    }
                } else {
                    $consecutiveErrors = 0;
                    $totalMs += $result->responseTimeMs;

                    if ($result->isHit()) {
                        $hits++;
                    } else {
                        $misses++;
                    }

                    // Back off on 429
                    if ($result->statusCode === 429) {
                        sleep(5);
                    }
                }
            }

            $successCount = count($urls) - $errors;

            $run->update([
                'urls_total' => count($urls),
                'urls_hit' => $hits,
                'urls_miss' => $misses,
                'urls_error' => $errors,
                'avg_response_ms' => $successCount > 0 ? (int) ($totalMs / $successCount) : 0,
                'status' => WarmRunStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            $this->warmSite->update(['last_warmed_at' => now()]);
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunWarmSite job failed', [
            'warm_site_id' => $this->warmSite->id,
            'error' => $e->getMessage(),
        ]);
    }
}
```

**Step 5: Run tests**

```bash
vendor/bin/phpunit tests/Feature/Jobs/
```

Expected: ALL PASS

**Step 6: Commit**

```bash
git add app/Jobs/DispatchWarmRuns.php app/Jobs/RunWarmSite.php tests/Feature/Jobs/
git commit -m "feat(warming): add DispatchWarmRuns and RunWarmSite jobs."
```

---

### Task 6: Scheduler Entry

**Files:**
- Modify: `routes/console.php`

**Step 1: Add scheduler entry**

After the last `Schedule::` line in `routes/console.php`, add:

```php
use App\Jobs\DispatchWarmRuns;

Schedule::job(new DispatchWarmRuns)->everyMinute()->withoutOverlapping()->onOneServer();
```

**Step 2: Commit**

```bash
git add routes/console.php
git commit -m "feat(warming): register DispatchWarmRuns in scheduler."
```

---

### Task 7: Policy + FormRequest

**Files:**
- Create: `app/Policies/WarmSitePolicy.php`
- Create: `app/Http/Requests/StoreWarmSiteRequest.php`
- Create: `app/Http/Requests/UpdateWarmSiteRequest.php`
- Modify: `app/Providers/AuthServiceProvider.php` (register policy)

**Step 1: Create WarmSitePolicy**

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarmSite;
use Illuminate\Auth\Access\HandlesAuthorization;

class WarmSitePolicy
{
    use HandlesAuthorization;

    public function view(User $user, WarmSite $warmSite): bool
    {
        return $user->team_id === $warmSite->team_id;
    }

    public function update(User $user, WarmSite $warmSite): bool
    {
        return $user->team_id === $warmSite->team_id;
    }

    public function delete(User $user, WarmSite $warmSite): bool
    {
        return $user->team_id === $warmSite->team_id;
    }

    public function warmNow(User $user, WarmSite $warmSite): bool
    {
        return $user->team_id === $warmSite->team_id;
    }
}
```

**Step 2: Create StoreWarmSiteRequest**

```php
<?php

namespace App\Http\Requests;

use App\Enums\WarmSiteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarmSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i',
                Rule::unique('warm_sites')->where('team_id', auth()->user()->team_id),
            ],
            'mode' => ['required', Rule::enum(WarmSiteMode::class)],
            'sitemap_url' => 'nullable|url|required_if:mode,sitemap',
            'urls' => 'nullable|array|max:500|required_if:mode,urls',
            'urls.*' => 'url',
            'frequency_minutes' => ['required', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'max_urls' => 'required|integer|min:1|max:500',
        ];
    }
}
```

**Step 3: Create UpdateWarmSiteRequest**

```php
<?php

namespace App\Http\Requests;

use App\Enums\WarmSiteMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarmSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*\.[a-z]{2,}$/i',
                Rule::unique('warm_sites')->where('team_id', auth()->user()->team_id)->ignore($this->route('warming')),
            ],
            'mode' => ['sometimes', 'required', Rule::enum(WarmSiteMode::class)],
            'sitemap_url' => 'nullable|url',
            'urls' => 'nullable|array|max:500',
            'urls.*' => 'url',
            'frequency_minutes' => ['sometimes', 'required', 'integer', Rule::in([15, 30, 60, 120, 360, 720, 1440])],
            'max_urls' => 'sometimes|required|integer|min:1|max:500',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
```

**Step 4: Register policy** — Check if auto-discovery is used. If not, register in `AuthServiceProvider`.

**Step 5: Commit**

```bash
git add app/Policies/WarmSitePolicy.php app/Http/Requests/StoreWarmSiteRequest.php app/Http/Requests/UpdateWarmSiteRequest.php
git commit -m "feat(warming): add WarmSitePolicy and form request validation."
```

---

### Task 8: Controller + Routes

**Files:**
- Create: `app/Http/Controllers/WarmSiteController.php`
- Modify: `routes/web.php`

**Step 1: Create WarmSiteController**

Follow the MonitorController pattern exactly. Resource controller with Inertia rendering. Delegate to WarmingService for warmNow action. Include:
- `index()` — list with last run stats
- `create()` — form with frequency options and mode labels
- `store()` — validate + create + redirect
- `show()` — site dashboard with recent runs
- `edit()` — pre-populated form
- `update()` — validate + update
- `destroy()` — delete with cascade
- `warmNow()` — dispatch RunWarmSite immediately

Props should match the types defined in the design doc (frequencies array, modes, lastRunStats, recentRuns).

**Step 2: Add routes to `routes/web.php`**

Inside the `auth` middleware group, after the Sources routes:

```php
use App\Http\Controllers\WarmSiteController;

Route::resource('warming', WarmSiteController::class);
Route::post('/warming/{warming}/warm-now', [WarmSiteController::class, 'warmNow'])->name('warming.warm-now');
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/WarmSiteController.php routes/web.php
git commit -m "feat(warming): add WarmSiteController with CRUD and warm-now action."
```

---

### Task 9: Controller Feature Tests

**Files:**
- Create: `tests/Feature/Http/Controllers/WarmSiteControllerTest.php`

**Step 1: Write tests**

Key test methods:
- `test_guest_cannot_access_warming_index`
- `test_user_can_view_warming_index`
- `test_user_only_sees_own_team_sites`
- `test_user_can_create_warm_site_with_urls_mode`
- `test_user_can_create_warm_site_with_sitemap_mode`
- `test_validation_rejects_invalid_domain`
- `test_validation_rejects_duplicate_domain_per_team`
- `test_user_can_update_warm_site`
- `test_user_cannot_update_other_teams_site`
- `test_user_can_delete_warm_site`
- `test_warm_now_dispatches_job`
- `test_show_page_displays_recent_runs`

Follow `MonitorControllerTest` pattern: `RefreshDatabase`, `createAuthenticatedUser()`, `actingAs()`, `assertStatus()`, `assertDatabaseHas()`, `Queue::fake()`.

**Step 2: Run tests**

```bash
vendor/bin/phpunit tests/Feature/Http/Controllers/WarmSiteControllerTest.php
```

Expected: ALL PASS

**Step 3: Commit**

```bash
git add tests/Feature/Http/Controllers/WarmSiteControllerTest.php
git commit -m "test(warming): add WarmSiteController feature tests."
```

---

### Task 10: Vue Pages

**Files:**
- Create: `resources/js/Pages/CacheWarming/Index.vue`
- Create: `resources/js/Pages/CacheWarming/Create.vue`
- Create: `resources/js/Pages/CacheWarming/Edit.vue`
- Create: `resources/js/Pages/CacheWarming/Show.vue`

Follow existing patterns:
- **Index**: DataView list with status badges, frequency labels, last run hit ratio, toggle active, "Warm Now" button. Pattern: `Monitors/Index.vue` with glass cards.
- **Create**: `useForm()` with dynamic mode switcher (sitemap_url vs urls textarea). Pattern: `Monitors/Create.vue`.
- **Edit**: Same form pre-populated. Pattern: `Monitors/Edit.vue`.
- **Show**: Header + stats cards + recent runs table. Pattern: `Monitors/Show.vue` (simplified — no charts in v1).

Use PrimeVue components: `DataView`, `SelectButton`, `Button`, `DataTable`, `Column`, `Tag`, `InputSwitch`.

Human-readable frequency labels: `{ 15: '15 minutes', 30: '30 minutes', 60: '1 hour', 120: '2 hours', 360: '6 hours', 720: '12 hours', 1440: '24 hours' }`.

Hit ratio badge colors: `>= 80 → green`, `>= 50 → yellow`, `< 50 → red`.

**Commit after each page or all 4 together:**

```bash
git add resources/js/Pages/CacheWarming/
git commit -m "feat(warming): add Vue pages for cache warming (Index, Create, Edit, Show)."
```

---

### Task 11: Navigation Entry

**Files:**
- Modify: `resources/js/Layouts/AppLayout.vue`

**Step 1: Add nav item**

In the `navigation` computed array (after Sources, before the admin `if` block), add:

```js
{ name: 'Cache Warming', href: '/warming', icon: 'flame', current: false },
```

**Step 2: Add flame icon SVG** in the template `<Link>` loop where other icons are rendered:

```html
<svg v-else-if="item.icon === 'flame'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z" stroke-linecap="round" stroke-linejoin="round"/></svg>
```

**Step 3: Commit**

```bash
git add resources/js/Layouts/AppLayout.vue
git commit -m "feat(warming): add Cache Warming to navigation sidebar."
```

---

### Task 12: Lint + Final Verification

**Step 1: Run Pint**

```bash
vendor/bin/pint
```

**Step 2: Run PHPStan**

```bash
vendor/bin/phpstan analyse
```

Fix any issues.

**Step 3: Run full test suite**

```bash
vendor/bin/phpunit
```

All tests must pass.

**Step 4: Commit fixes**

```bash
git add -A
git commit -m "chore(warming): apply lint fixes and resolve static analysis issues."
```

---

## Summary

| Task | Files | Estimated |
|------|-------|-----------|
| 1. Enums | 2 new | 2 min |
| 2. Migration | 2 new | 5 min |
| 3. Models + Factories | 4 new | 10 min |
| 4. DTO + WarmingService + tests | 3 new | 20 min |
| 5. Jobs + tests | 4 new | 15 min |
| 6. Scheduler | 1 modify | 2 min |
| 7. Policy + FormRequests | 3 new | 10 min |
| 8. Controller + Routes | 2 new/modify | 15 min |
| 9. Controller tests | 1 new | 15 min |
| 10. Vue pages | 4 new | 30 min |
| 11. Navigation | 1 modify | 5 min |
| 12. Lint + verify | — | 5 min |
| **Total** | **~26 files** | **~2.5h** |
