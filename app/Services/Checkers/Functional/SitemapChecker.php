<?php

namespace App\Services\Checkers\Functional;

use App\DTOs\FunctionalResult;
use App\Models\FunctionalCheck;
use Illuminate\Support\Facades\Http;

class SitemapChecker
{
    public function check(FunctionalCheck $check): FunctionalResult
    {
        $startTime = microtime(true);
        $url       = $check->resolveUrl();

        try {
            $response = Http::timeout(30)->connectTimeout(10)
                ->withHeaders(['User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)'])
                ->get($url);

            $body = $response->body();
            $xml  = null;
            $urls = [];

            try {
                $xml = new \SimpleXMLElement($body);
                $xml->registerXPathNamespace('sm', 'http://www.sitemaps.org/schemas/sitemap/0.9');
                $urlElements = $xml->xpath('//sm:url/sm:loc') ?: $xml->xpath('//url/loc') ?: [];
                $urls        = array_map(fn ($el) => (string) $el, $urlElements);
            } catch (\Throwable) {
                // handled by is_valid_xml rule
            }

            $details = [];
            $passed  = true;

            foreach ($check->rules as $rule) {
                $detail    = $this->applyRule($rule, $body, $xml, $urls, $check);
                $details[] = $detail;
                if (! $detail['passed']) {
                    $passed = false;
                }
            }

            return new FunctionalResult(
                passed:     $passed,
                durationMs: (int) ((microtime(true) - $startTime) * 1000),
                details:    $details,
            );
        } catch (\Throwable $e) {
            return new FunctionalResult(
                passed:       false,
                durationMs:   (int) ((microtime(true) - $startTime) * 1000),
                details:      [],
                errorMessage: $e->getMessage(),
            );
        }
    }

    private function applyRule(array $rule, string $body, mixed $xml, array $urls, FunctionalCheck $check): array
    {
        return match ($rule['type']) {
            'is_valid_xml' => [
                'rule'    => 'is_valid_xml',
                'passed'  => $xml !== null,
                'message' => $xml !== null ? 'Valid XML' : 'Invalid XML or empty response',
            ],
            'min_urls' => [
                'rule'    => 'min_urls',
                'value'   => $rule['value'],
                'passed'  => count($urls) >= (int) $rule['value'],
                'message' => count($urls) . ' URLs found (min: ' . $rule['value'] . ')',
            ],
            'urls_accessible' => [
                'rule'    => 'urls_accessible',
                'value'   => $rule['value'] ?? 20,
                'passed'  => $this->checkUrlsSample($urls, (int) ($rule['value'] ?? 20)),
                'message' => 'Sample of ' . min(count($urls), (int) ($rule['value'] ?? 20)) . ' URLs checked',
            ],
            'track_changes' => $this->trackChanges($urls, $check),
            default => [
                'rule'    => $rule['type'],
                'passed'  => false,
                'message' => "Unknown rule type: {$rule['type']}",
            ],
        };
    }

    private function checkUrlsSample(array $urls, int $sample): bool
    {
        foreach (array_slice($urls, 0, $sample) as $url) {
            try {
                $status = Http::timeout(10)->head($url)->status();
                if (! in_array($status, [200, 301, 302, 304])) {
                    return false;
                }
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    private function trackChanges(array $currentUrls, FunctionalCheck $check): array
    {
        $lastResult = $check->results()->latest('checked_at')->first();

        if (! $lastResult) {
            return [
                'rule'    => 'track_changes',
                'passed'  => true,
                'message' => 'Baseline established (' . count($currentUrls) . ' URLs)',
                'urls'    => $currentUrls,
            ];
        }

        $previousUrls = collect($lastResult->details)
            ->firstWhere('rule', 'track_changes')['urls'] ?? [];

        $added   = array_values(array_diff($currentUrls, $previousUrls));
        $removed = array_values(array_diff($previousUrls, $currentUrls));
        $changed = count($added) > 0 || count($removed) > 0;

        return [
            'rule'    => 'track_changes',
            'passed'  => ! $changed,
            'message' => $changed
                ? count($added) . ' URL(s) added, ' . count($removed) . ' URL(s) removed'
                : 'No changes (' . count($currentUrls) . ' URLs)',
            'urls'    => $currentUrls,
            'added'   => $added,
            'removed' => $removed,
        ];
    }
}
