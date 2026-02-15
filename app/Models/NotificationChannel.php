<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificationChannel extends Model
{
    use BelongsToTeam, HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'type',
        'settings',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'monitor_notification_channel');
    }
}
