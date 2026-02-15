<?php

namespace App\Models;

use App\Enums\IncidentCause;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorIncident extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'started_at',
        'resolved_at',
        'cause',
    ];

    protected $casts = [
        'cause' => IncidentCause::class,
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved($query)
    {
        return $query->whereNotNull('resolved_at');
    }

    public function duration(): CarbonInterval
    {
        $end = $this->resolved_at ?? now();

        return CarbonInterval::make($this->started_at->diff($end));
    }

    public function resolve(): void
    {
        $this->resolved_at = now();
        $this->save();
    }
}
