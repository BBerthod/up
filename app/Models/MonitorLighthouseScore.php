<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorLighthouseScore extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'monitor_id',
        'performance',
        'accessibility',
        'best_practices',
        'seo',
        'scored_at',
    ];

    protected $casts = [
        'performance' => 'integer',
        'accessibility' => 'integer',
        'best_practices' => 'integer',
        'seo' => 'integer',
        'scored_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(Monitor::class);
    }
}
