<?php

namespace App\Services\Checkers;

use App\Contracts\MonitorChecker;
use App\DTOs\CheckResult;
use App\Enums\CheckStatus;
use App\Enums\IncidentCause;
use App\Models\Monitor;

class PingChecker implements MonitorChecker
{
    public function check(Monitor $monitor): CheckResult
    {
        $host = $this->extractHost($monitor->url);
        $startTime = microtime(true);

        try {
            $output = [];
            $exitCode = 0;
            $cmd = sprintf('ping -c 3 -W 5 %s 2>&1', escapeshellarg($host));
            exec($cmd, $output, $exitCode);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
            $outputText = implode("\n", $output);

            $avgTime = $this->parseAveragePingTime($outputText);
            if ($avgTime !== null) {
                $responseTimeMs = $avgTime;
            }

            if ($exitCode === 0 && str_contains($outputText, 'bytes from')) {
                return new CheckResult(
                    status: CheckStatus::UP,
                    responseTimeMs: $responseTimeMs,
                );
            }

            return new CheckResult(
                status: CheckStatus::DOWN,
                responseTimeMs: $responseTimeMs,
                errorMessage: '100% packet loss',
                cause: IncidentCause::TIMEOUT,
            );
        } catch (\Throwable $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            return new CheckResult(
                status: CheckStatus::DOWN,
                responseTimeMs: $responseTimeMs,
                errorMessage: $e->getMessage(),
                cause: IncidentCause::ERROR,
            );
        }
    }

    private function extractHost(string $url): string
    {
        $parsed = parse_url($url);

        return $parsed['host'] ?? $url;
    }

    private function parseAveragePingTime(string $output): ?int
    {
        // macOS/Linux: "round-trip min/avg/max/stddev = 1.234/5.678/9.012/1.234 ms"
        if (preg_match('/=\s*[\d.]+\/([\d.]+)\//', $output, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }
}
