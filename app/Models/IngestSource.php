<?php

namespace App\Models;

use App\Models\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class IngestSource extends Model
{
    use BelongsToTeam;

    protected $fillable = [
        'team_id',
        'name',
        'slug',
        'token',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(IngestEvent::class, 'source_id');
    }

    public function notificationChannels(): BelongsToMany
    {
        return $this->belongsToMany(NotificationChannel::class, 'ingest_source_notification_channel');
    }

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public static function generateSlug(string $name): string
    {
        return Str::slug($name);
    }
}
