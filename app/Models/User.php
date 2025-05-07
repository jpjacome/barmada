<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'is_admin',
        'preferences',
        'is_editor',
        'is_staff',
        'editor_metadata',
        'editor_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
        'editor_metadata' => 'array',
    ];

    public function tables()
    {
        return $this->hasMany(Table::class, 'editor_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'editor_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'editor_id');
    }

    public function categories()
    {
        return $this->hasMany(Category::class, 'editor_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class, 'editor_id');
    }
}
