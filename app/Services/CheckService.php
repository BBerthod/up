<?php

namespace App\Services;

use App\Contracts\MonitorChecker;
use App\Enums\CheckStatus;
use App\Enums\IncidentCause;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorIncident;
use App\Services\Checkers\DnsChecker;
use App\Services\Checkers\HttpChecker;
use App\Services\Checkers\PingChecker;
use App\Services\Checkers\PortChecker;
use Illuminate\Support\Facades\Cache;

class CheckService
{
    private array $checkers = [];

    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function check(Monitor $monitor): MonitorCheck
    {
        $checker = $this->resolveChecker($monitor->type ?? MonitorType::HTTP);
        $result = $checker->check($monitor);

        $check = MonitorCheck::create([
            'monitor_id' => $monitor->id,
            'status' => $result->status,
            'response_time_ms' => $result->responseTimeMs,
            'status_code' => $result->statusCode,
            'ssl_expires_at' => $result->sslExpiresAt,
            'error_message' => $result->errorMessage,
            'checked_at' => now(),
        ]);

        $monitor->update(['last_checked_at' => now()]);

        $lock = Cache::lock("monitor:check:{$monitor->id}", 30);

        if ($lock->get()) {
            try {
                $previousCheck = $monitor->checks()->latest('checked_at')->skip(1)->first();
                $wasUp = $previousCheck === null || $previousCheck->status === CheckStatus::UP;
                $isUp = $result->status === CheckStatus::UP;

                if ($wasUp && ! $isUp) {
                    $incident = MonitorIncident::create([
                        'monitor_id' => $monitor->id,
                        'started_at' => now(),
                        'cause' => $result->cause,
                    ]);
                    $this->notificationService->notifyDown($monitor, $incident, $check);
                } elseif (! $wasUp && $isUp) {
                    $incident = $monitor->incidents()
                        ->whereNull('resolved_at')
                        ->latest('started_at')
                        ->first();

                    if ($incident) {
                        $incident->resolve();
                        $this->notificationService->notifyUp($monitor, $incident, $check);
                    }
                }

                $this->checkThresholds($monitor, $check);
            } finally {
                $lock->release();
            }
        }

        return $check;
    }

    private function resolveChecker(MonitorType $type): MonitorChecker
    {
        return $this->checkers[$type->value] ??= match ($type) {
            MonitorType::HTTP => new HttpChecker,
            MonitorType::PING => new PingChecker,
            MonitorType::PORT => new PortChecker,
            MonitorType::DNS => new DnsChecker,
        };
    }

    private function checkThresholds(Monitor $monitor, MonitorCheck $check): void
    {
        if ($check->status !== CheckStatus::UP || ! $monitor->critical_threshold_ms) {
            return;
        }

        $recentChecks = $monitor->checks()
            ->latest('checked_at')
            ->limit(3)
            ->pluck('response_time_ms');

        if ($recentChecks->count() < 3) {
            return;
        }

        $allExceedCritical = $recentChecks->every(fn ($ms) => $ms >= $monitor->critical_threshold_ms);

        if ($allExceedCritical) {
            $hasActiveIncident = $monitor->incidents()->whereNull('resolved_at')->exists();

            if (! $hasActiveIncident) {
                $incident = MonitorIncident::create([
                    'monitor_id' => $monitor->id,
                    'started_at' => now(),
                    'cause' => IncidentCause::TIMEOUT,
                ]);
                $this->notificationService->notifyDown($monitor, $incident, $check);
            }
        }
    }
}
