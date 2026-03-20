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
     */
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
                    'Cache-Control' => 'max-age=0',
                ])
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
     * @return string[]
     */
    private function parseSitemap(string $sitemapUrl): array
    {
        try {
            $response = Http::timeout(15)
                ->connectTimeout(5)
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
    private function filterSafeUrls(array $urls): array
    {
        return array_values(array_filter($urls, function (string $url): bool {
            $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');

            if ($host === '') {
                return false;
            }

            // Block loopback and common localhost aliases
            if (in_array($host, ['localhost', '0.0.0.0'], true)) {
                return false;
            }

            // Only perform IP-based checks when the host looks like an IP literal
            if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                // Block private IPv4 ranges (RFC 1918)
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                    if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return false;
                    }
                }

                // Block link-local / AWS / GCP cloud metadata IP
                if (str_starts_with($host, '169.254.')) {
                    return false;
                }

                // Block IPv6 loopback
                if ($host === '::1' || $host === '[::1]') {
                    return false;
                }
            }

            return true;
        }));
    }
}
