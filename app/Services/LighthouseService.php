<?php

namespace App\Services;

use App\DTOs\LighthouseResult;
use App\Models\Monitor;
use App\Models\MonitorLighthouseScore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class LighthouseService
{
    public function audit(Monitor $monitor): MonitorLighthouseScore
    {
        $queryParams = [
            'url' => $monitor->url,
            'category' => ['performance', 'accessibility', 'best-practices', 'seo'],
            'strategy' => 'mobile',
        ];

        $apiKey = config('services.google.pagespeed_api_key');
        if ($apiKey) {
            $queryParams['key'] = $apiKey;
        }

        try {
            $response = Http::timeout(120)->get(
                'https://www.googleapis.com/pagespeedonline/v5/runPagespeed',
                $queryParams
            );

            if (! $response->successful()) {
                Log::error('Lighthouse API request failed', [
                    'monitor_id' => $monitor->id,
                    'url' => $monitor->url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new RuntimeException('Lighthouse API request failed');
            }

            $data = $response->json();
            $lighthouseResult = $data['lighthouseResult'] ?? null;

            if (! $lighthouseResult) {
                Log::error('Invalid Lighthouse API response', [
                    'monitor_id' => $monitor->id,
                    'url' => $monitor->url,
                ]);

                throw new RuntimeException('Invalid Lighthouse API response');
            }

            $categories = $lighthouseResult['categories'] ?? [];
            $audits = $lighthouseResult['audits'] ?? [];

            $result = new LighthouseResult(
                performance: $this->parseScore($categories, 'performance'),
                accessibility: $this->parseScore($categories, 'accessibility'),
                bestPractices: $this->parseScore($categories, 'best-practices'),
                seo: $this->parseScore($categories, 'seo'),
                lcp: $this->parseAudit($audits, 'largest-contentful-paint', 1),
                fcp: $this->parseAudit($audits, 'first-contentful-paint', 1),
                cls: $this->parseAudit($audits, 'cumulative-layout-shift', 4),
                tbt: $this->parseAudit($audits, 'total-blocking-time', 1),
                speedIndex: $this->parseAudit($audits, 'speed-index', 1),
            );

            return MonitorLighthouseScore::create([
                'monitor_id' => $monitor->id,
                'performance' => $result->performance,
                'accessibility' => $result->accessibility,
                'best_practices' => $result->bestPractices,
                'seo' => $result->seo,
                'lcp' => $result->lcp,
                'fcp' => $result->fcp,
                'cls' => $result->cls,
                'tbt' => $result->tbt,
                'speed_index' => $result->speedIndex,
                'scored_at' => now(),
            ]);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Lighthouse audit failed', [
                'monitor_id' => $monitor->id,
                'url' => $monitor->url,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Lighthouse audit failed: '.$e->getMessage(), 0, $e);
        }
    }

    private function parseScore(array $categories, string $key): int
    {
        return isset($categories[$key]['score'])
            ? (int) round($categories[$key]['score'] * 100)
            : 0;
    }

    private function parseAudit(array $audits, string $key, int $precision): ?float
    {
        return isset($audits[$key]['numericValue'])
            ? round((float) $audits[$key]['numericValue'], $precision)
            : null;
    }
}
