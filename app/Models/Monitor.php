<?php

namespace App\Models;

use App\Enums\CheckStatus;
use App\Enums\MonitorMethod;
use App\Models\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Monitor extends Model
{
    use BelongsToTeam;
    use HasFactory;

    protected $fillable = [
        'name',
        'url',
        'method',
        'expected_status_code',
        'keyword',
        'interval',
        'is_active',
        'last_checked_at',
        'warning_threshold_ms',
        'critical_threshold_ms',
    ];

    protected $casts = [
        'method' => MonitorMethod::class,
        'is_active' => 'boolean',
        'last_checked_at' => 'datetime',
        'warning_threshold_ms' => 'integer',
        'critical_threshold_ms' => 'integer',
    ];

    public function checks(): HasMany
    {
        return $this->hasMany(MonitorCheck::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(MonitorIncident::class);
    }

    public function lighthouseScores(): HasMany
    {
        return $this->hasMany(MonitorLighthouseScore::class);
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class, 'monitor_notification_channel');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeDueForCheck($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('last_checked_at');

                if ($q->getConnection()->getDriverName() === 'pgsql') {
                    $q->orWhereRaw('last_checked_at <= now() - make_interval(mins => "interval")');
                } else {
                    $q->orWhereRaw("last_checked_at <= datetime('now', '-' || \"interval\" || ' minutes')");
                }
            });
    }

    public function isUp(): bool
    {
        return $this->checks()->latest('checked_at')->value('status') === CheckStatus::UP;
    }

    public function isDown(): bool
    {
        return ! $this->isUp();
    }
}
