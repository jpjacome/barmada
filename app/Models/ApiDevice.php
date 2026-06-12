<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A mobile device registered by a staff-app user — the push-notification
 * registry. One row per (user, device); the FCM token is refreshed in
 * place when the app re-registers. Belongs to the USER (not the tenant):
 * a device follows its account.
 */
class ApiDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'name',
        'platform',
        'fcm_token',
        'app_version',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
