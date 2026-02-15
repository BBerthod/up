<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = ['name'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function monitors(): HasMany
    {
        return $this->hasMany(Monitor::class);
    }

    public function notificationChannels(): HasMany
    {
        return $this->hasMany(NotificationChannel::class);
    }

    public function statusPages(): HasMany
    {
        return $this->hasMany(StatusPage::class);
    }
}
