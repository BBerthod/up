<?php

namespace App\Services\Checkers;

use App\Contracts\MonitorChecker;
use App\DTOs\CheckResult;
use App\Enums\CheckStatus;
use App\Enums\IncidentCause;
use App\Models\Monitor;

class DnsChecker implements MonitorChecker
{
    private const RECORD_TYPE_MAP = [
        'A' => DNS_A,
        'AAAA' => DNS_AAAA,
        'CNAME' => DNS_CNAME,
        'MX' => DNS_MX,
        'TXT' => DNS_TXT,
        'NS' => DNS_NS,
        'SOA' => DNS_SOA,
        'SRV' => DNS_SRV,
    ];

    public function check(Monitor $monitor): CheckResult
    {
        $domain = $this->extractDomain($monitor->url);
        $recordType = $monitor->dns_record_type;
        $expectedValue = $monitor->dns_expected_value;
        $startTime = microtime(true);

        try {
            $dnsType = self::RECORD_TYPE_MAP[$recordType] ?? DNS_A;
            $records = @dns_get_record($domain, $dnsType);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($records === false || empty($records)) {
                return new CheckResult(
                    status: CheckStatus::DOWN,
                    responseTimeMs: $responseTimeMs,
                    errorMessage: "No {$recordType} record found for {$domain}",
                    cause: IncidentCause::ERROR,
                );
            }

            $actualValue = $this->extractValue($records[0], $recordType);

            if ($this->valuesMatch($actualValue, $expectedValue)) {
                return new CheckResult(
                    status: CheckStatus::UP,
                    responseTimeMs: $responseTimeMs,
                );
            }

            return new CheckResult(
                status: CheckStatus::DOWN,
                responseTimeMs: $responseTimeMs,
                errorMessage: "DNS mismatch: expected '{$expectedValue}', got '{$actualValue}'",
                cause: IncidentCause::ERROR,
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

    private function extractDomain(string $url): string
    {
        $parsed = parse_url($url);

        return $parsed['host'] ?? $url;
    }

    private function extractValue(array $record, string $type): string
    {
        return match ($type) {
            'A', 'AAAA' => $record['ip'] ?? '',
            'CNAME' => $record['target'] ?? '',
            'MX' => $record['target'] ?? '',
            'TXT' => $record['txt'] ?? '',
            'NS' => $record['target'] ?? '',
            'SOA' => $record['mname'] ?? '',
            'SRV' => ($record['target'] ?? '').':'.($record['port'] ?? ''),
            default => $record['ip'] ?? $record['target'] ?? '',
        };
    }

    private function valuesMatch(string $actual, string $expected): bool
    {
        return strcasecmp(rtrim($actual, '.'), rtrim($expected, '.')) === 0;
    }
}
