<?php

namespace App\Services\Checkers;

use App\Contracts\MonitorChecker;
use App\DTOs\CheckResult;
use App\Enums\CheckStatus;
use App\Enums\IncidentCause;
use App\Models\Monitor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HttpChecker implements MonitorChecker
{
    public function check(Monitor $monitor): CheckResult
    {
        $startTime = microtime(true);
        $statusCode = null;
        $errorMessage = null;
        $status = CheckStatus::UP;
        $cause = null;

        if ($this->isPrivateUrl($monitor->url)) {
            return new CheckResult(
                status: CheckStatus::DOWN,
                responseTimeMs: 0,
                statusCode: null,
                sslExpiresAt: null,
                errorMessage: 'URL targets a private or reserved IP address',
                cause: IncidentCause::ERROR,
            );
        }

        try {
            $response = $this->makeHttpRequest($monitor);
            $statusCode = $response->status();

            if ($statusCode !== $monitor->expected_status_code) {
                $status = CheckStatus::DOWN;
                $cause = IncidentCause::STATUS_CODE;
            }

            if ($status === CheckStatus::UP && filled($monitor->keyword)) {
                if (! str_contains($response->body(), $monitor->keyword)) {
                    $status = CheckStatus::DOWN;
                    $cause = IncidentCause::KEYWORD;
                }
            }
        } catch (ConnectionException $e) {
            $status = CheckStatus::DOWN;
            $errorMessage = $e->getMessage();

            $errorMsgLower = strtolower($errorMessage);
            if (str_contains($errorMsgLower, 'timeout')) {
                $cause = IncidentCause::TIMEOUT;
            } else {
                $cause = IncidentCause::ERROR;
            }
        }

        $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);
        $sslExpiresAt = $this->checkSslExpiry($monitor->url);

        return new CheckResult(
            status: $status,
            responseTimeMs: $responseTimeMs,
            statusCode: $statusCode,
            sslExpiresAt: $sslExpiresAt,
            errorMessage: $errorMessage,
            cause: $cause,
        );
    }

    private function isPrivateUrl(string $url): bool
    {
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        if (in_array($scheme, ['file', 'gopher', 'dict'], true)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null) {
            return true;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return false;
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        $privateRanges = [
            ['127.0.0.0', '255.0.0.0'],
            ['10.0.0.0', '255.0.0.0'],
            ['172.16.0.0', '255.240.0.0'],
            ['192.168.0.0', '255.255.0.0'],
            ['169.254.0.0', '255.255.0.0'],
            ['0.0.0.0', '255.0.0.0'],
        ];

        foreach ($privateRanges as [$network, $mask]) {
            $networkLong = ip2long($network);
            $maskLong = ip2long($mask);

            if (($ipLong & $maskLong) === ($networkLong & $maskLong)) {
                return true;
            }
        }

        return false;
    }

    private function makeHttpRequest(Monitor $monitor)
    {
        $method = strtolower($monitor->method->value);

        return Http::timeout(30)
            ->connectTimeout(10)
            ->withoutVerifying()
            ->withHeaders([
                'User-Agent' => 'Up-Monitor/1.0 (+https://github.com/BBerthod/up)',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->{$method}($monitor->url);
    }

    private function checkSslExpiry(string $url): ?\DateTime
    {
        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $parsedUrl = parse_url($url);
            $host = $parsedUrl['host'] ?? null;

            if (! $host) {
                return null;
            }

            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $socket) {
                return null;
            }

            $params = stream_context_get_params($socket);
            $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
            fclose($socket);

            if (! $cert || ! isset($cert['validTo_time_t'])) {
                return null;
            }

            return (new \DateTime)->setTimestamp($cert['validTo_time_t']);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
