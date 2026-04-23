<?php

namespace App\Services;

use App\DTOs\WarmUrlResult;
use App\Enums\WarmSiteMode;
use App\Models\WarmSite;
use App\Support\UrlSafetyValidator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WarmingService
{
    /**
     * Resolve the list of URLs to warm for a given site.
     *
     * In SITEMAP mode the sitemap XML is fetched and all <loc> entries are
     * extracted. In URLS mode the manually-configured array is used directly.
     *
     * URLs are deduplicated, filtered for safety (no private IPs / cloud
     * metadata addresses), and capped at max_urls with random rotation so
     * every URL has a chance to be warmed over successive runs.
     *
     * @return string[]
     */
    public function resolveUrls(WarmSite $site): array
    {
        $urls = match ($site->mode) {
            WarmSiteMode::SITEMAP => $this->parseSitemap((string) $site->resolved_sitemap_url),
            WarmSiteMode::URLS => (array) ($site->urls ?? []),
        };

        $urls = $this->deduplicateUrls($urls);
        $urls = $this->filterSafeUrls($urls);
        $urls = $this->filterByDomain($urls, $site->domain);

        if (count($urls) > $site->max_urls) {
            shuffle($urls);
            $urls = array_slice($urls, 0, $site->max_urls);
        }

        return array_values($urls);
    }

    /**
     * Perform a single cache-warming GET request and return the result.
     *
     * The cache status is determined by inspecting response headers in
     * priority order: Cloudflare → Nginx → Varnish/CloudFront → age header.
     *
     * @param  array<string, string>  $customHeaders  Additional headers to send with the request.
     *                                                Dangerous headers (Host, Cookie, etc.) are filtered out.
     */
    public function warmUrl(string $url, array $customHeaders = []): WarmUrlResult
    {
        $start = microtime(true);

        $blockedHeaders = ['host', 'cookie', 'content-length', 'transfer-encoding', 'connection', 'x-forwarded-for', 'x-real-ip', 'origin', 'referer'];
        $safeCustomHeaders = [];
        foreach ($customHeaders as $key => $value) {
            if (! in_array(strtolower($key), $blockedHeaders, true)) {
                $safeCustomHeaders[$key] = $value;
            }
        }

        try {
            $response = Http::timeout(10)
                ->connectTimeout(5)
                ->withoutVerifying()
                ->maxRedirects(3)
                ->withHeaders(array_merge([
                    'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
                    'Cache-Control' => 'max-age=0',
                ], $safeCustomHeaders))
                ->get($url);

            $responseTimeMs = (int) ((microtime(true) - $start) * 1000);

            $headers = [];
            foreach ($response->headers() as $name => $values) {
                $headers[strtolower($name)] = implode(', ', (array) $values);
            }

            $cacheStatus = $this->detectCacheStatus($headers);

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

            Log::warning('WarmingService: unexpected error warming URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return new WarmUrlResult(
                url: $url,
                statusCode: 0,
                cacheStatus: 'unknown',
                responseTimeMs: $responseTimeMs,
                errorMessage: $e->getMessage(),
            );
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch and parse a sitemap XML document, returning all <loc> values.
     *
     * Supports both flat sitemaps (<urlset>) and sitemap index files
     * (<sitemapindex>) which reference child sitemaps. Recursion is limited
     * to a maximum depth of 2 to prevent infinite loops or abuse.
     *
     * @return string[]
     */
    private function parseSitemap(string $sitemapUrl, int $depth = 0): array
    {
        if ($depth > 2) {
            Log::warning('WarmingService: sitemap recursion depth exceeded', ['url' => $sitemapUrl, 'depth' => $depth]);

            return [];
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
                ])
                ->get($sitemapUrl);

            if (! $response->successful()) {
                Log::warning('WarmingService: sitemap fetch returned non-2xx', [
                    'url' => $sitemapUrl,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $body = $response->body();

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);

            if ($xml === false) {
                Log::warning('WarmingService: failed to parse sitemap XML', [
                    'url' => $sitemapUrl,
                ]);

                return [];
            }

            $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');

            // Check if this is a sitemap index (contains <sitemap> elements)
            $sitemapLocs = $xml->xpath('//sm:sitemap/sm:loc') ?: $xml->xpath('//sitemap/loc') ?: [];

            if (! empty($sitemapLocs)) {
                // Limit child sitemaps to avoid timeout (each fetch can take up to 15s)
                $maxChildSitemaps = 10;
                $sitemapLocs = array_slice($sitemapLocs, 0, $maxChildSitemaps);

                $allUrls = [];
                foreach ($sitemapLocs as $childLoc) {
                    $childUrl = trim((string) $childLoc);
                    $childUrls = $this->parseSitemap($childUrl, $depth + 1);
                    $allUrls = array_merge($allUrls, $childUrls);

                    // Safety cap to avoid memory issues
                    if (count($allUrls) > 10000) {
                        Log::info('WarmingService: sitemap index URL cap reached', ['url' => $sitemapUrl, 'count' => count($allUrls)]);
                        break;
                    }
                }

                return $allUrls;
            }

            $elements = $xml->xpath('//sm:url/sm:loc') ?: $xml->xpath('//url/loc') ?: [];

            return array_map(fn ($el) => trim((string) $el), $elements);
        } catch (\Throwable $e) {
            Log::warning('WarmingService: exception during sitemap parsing', [
                'url' => $sitemapUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Determine the cache status from a normalised (lowercase key) header map.
     *
     * Priority order:
     *   1. cf-cache-status  (Cloudflare)
     *   2. x-cache-status   (Nginx)
     *   3. x-cache          (Varnish / CloudFront)
     *   4. age              (generic CDN/proxy)
     */
    private function detectCacheStatus(array $headers): string
    {
        // Cloudflare
        if (isset($headers['cf-cache-status'])) {
            $value = strtoupper(trim($headers['cf-cache-status']));

            return in_array($value, ['HIT', 'STALE'], true) ? 'hit' : 'miss';
        }

        // Nginx proxy_cache_status
        if (isset($headers['x-cache-status'])) {
            $value = strtoupper(trim($headers['x-cache-status']));

            return $value === 'HIT' ? 'hit' : 'miss';
        }

        // Varnish / CloudFront / generic x-cache
        if (isset($headers['x-cache'])) {
            $value = strtolower($headers['x-cache']);

            return str_contains($value, 'hit') ? 'hit' : 'miss';
        }

        // age > 0 means a cached response was served
        if (isset($headers['age']) && (int) $headers['age'] > 0) {
            return 'hit';
        }

        return 'unknown';
    }

    /**
     * Deduplicate a URL list by normalised form.
     *
     * Normalisation: lowercase scheme + host (strip leading "www."), lowercase
     * path with trailing slash removed, preserve query string as-is.
     *
     * @param  string[]  $urls
     * @return string[]
     */
    private function deduplicateUrls(array $urls): array
    {
        $seen = [];
        $result = [];

        foreach ($urls as $url) {
            $normalised = $this->normalizeUrl($url);
            if ($normalised === null) {
                continue;
            }

            if (! isset($seen[$normalised])) {
                $seen[$normalised] = true;
                $result[] = $url;
            }
        }

        return $result;
    }

    /**
     * Produce a normalised string for deduplication purposes.
     * Returns null if the URL cannot be parsed.
     */
    private function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        $path = rtrim($parts['path'] ?? '/', '/') ?: '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return "{$scheme}://{$host}{$path}{$query}";
    }

    /**
     * Remove URLs that resolve to private/loopback/link-local addresses to
     * prevent SSRF attacks.
     *
     * We only perform IP-based filtering: if the host is an IP literal we
     * check it directly. If the host is a domain name we do NOT perform DNS
     * resolution (to avoid failures with synthetic/fake domains in tests and
     * to keep this method fast in production — network-level egress filtering
     * is the defence-in-depth layer for domain-based SSRF).
     *
     * @param  string[]  $urls
     * @return string[]
     */
    /**
     * Keep only URLs whose host matches the warm site's domain.
     *
     * Sitemap indexes (e.g. Topelio) may reference child sitemaps from other
     * subdomains (fr.topelio.com inside us.topelio.com/sitemap.xml).
     * Without this filter, warming would hit the wrong locale.
     *
     * @param  string[]  $urls
     * @return string[]
     */
    private function filterByDomain(array $urls, string $domain): array
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return $urls;
        }

        return array_values(array_filter($urls, function (string $url) use ($domain): bool {
            $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

            return $host === $domain || $host === 'www.'.$domain;
        }));
    }

    private function filterSafeUrls(array $urls): array
    {
        return array_values(array_filter($urls, fn (string $url): bool => UrlSafetyValidator::isSafe($url)));
    }
}
