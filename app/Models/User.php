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
    /*
     * Privilege and role flags (is_admin, is_editor, is_staff) are deliberately
     * NOT mass-assignable: they must never be settable from request input. The
     * trusted code paths that create privileged/staff accounts set them
     * explicitly via forceFill(). editor_id remains fillable because tenant
     * assignment is performed by trusted code and the BelongsToEditor trait.
     */
    protected $fillable = [
        'username',
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'preferences',
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

    /**
     * The tenant (editor) this user operates under.
     *
     * Editors own their tenant, staff belong to their editor's tenant,
     * admins and tenant-less users return null.
     */
    public function effectiveEditorId(): ?int
    {
        if ($this->is_editor) {
            return $this->id;
        }

        if ($this->is_staff) {
            return $this->editor_id;
        }

        return null;
    }

    /**
     * Currency symbol shown anywhere money is rendered for this tenant.
     */
    public function currencySymbol(): string
    {
        return $this->currency_symbol ?: '$';
    }

    /**
     * Locale used on the guest-facing (QR ordering) pages of this tenant.
     */
    public function guestLocale(): string
    {
        return $this->locale ?: 'es';
    }

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
