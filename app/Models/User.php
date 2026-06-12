<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
        'business_name',
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
     * Abilities stamped on API tokens, derived from the role flags at
     * login time. Enforcement rides on the same policies as the web —
     * these markers exist so future endpoints (and the mobile app's UI)
     * can gate on them without re-deriving roles.
     *
     * @return list<string>
     */
    public function apiTokenAbilities(): array
    {
        if ($this->is_admin) {
            return ['role:admin', 'role:editor', 'role:staff'];
        }

        if ($this->is_editor) {
            return ['role:editor', 'role:staff'];
        }

        if ($this->is_staff) {
            return ['role:staff'];
        }

        return [];
    }

    /**
     * The user whose business settings (currency, locale, timezone) apply
     * to this account: editors themselves, the owning editor for staff,
     * self for admins and tenant-less users.
     */
    public function venueSettingsUser(): User
    {
        $tenantId = $this->effectiveEditorId();

        if ($tenantId !== null && $tenantId !== $this->id) {
            return User::find($tenantId) ?? $this;
        }

        return $this;
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

    /**
     * IANA timezone the venue operates in (analytics bucketing).
     */
    public function businessTimezone(): string
    {
        return $this->business_timezone ?: 'UTC';
    }

    /**
     * Hour (0-12, venue-local) at which the venue's business day starts —
     * a bar's Friday includes the small hours of Saturday.
     */
    public function dayCutoffHour(): int
    {
        return max(0, min(12, (int) ($this->day_cutoff_hour ?? 0)));
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
