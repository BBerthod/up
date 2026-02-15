<?php

namespace App\Models;

use App\Enums\CheckStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorCheck extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'status',
        'response_time_ms',
        'status_code',
        'ssl_expires_at',
        'error_message',
        'checked_at',
    ];

    protected $casts = [
        'status' => CheckStatus::class,
        'ssl_expires_at' => 'datetime',
        'checked_at' => 'datetime',
        'response_time_ms' => 'integer',
        'status_code' => 'integer',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function scopeUp($query)
    {
        return $query->where('status', CheckStatus::UP);
    }

    public function scopeDown($query)
    {
        return $query->where('status', CheckStatus::DOWN);
    }

    public function isWarning(): bool
    {
        if (! $this->monitor->warning_threshold_ms) {
            return false;
        }

        return $this->response_time_ms >= $this->monitor->warning_threshold_ms;
    }

    public function isCritical(): bool
    {
        if (! $this->monitor->critical_threshold_ms) {
            return false;
        }

        return $this->response_time_ms >= $this->monitor->critical_threshold_ms;
    }
}
