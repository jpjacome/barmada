<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Table extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'orders',
        'status',
        'reference',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_occupied' => 'boolean',
    ];

    /**
     * Get the orders for the table.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the current active order for this table, if any.
     */
    public function activeOrder()
    {
        return $this->orders()
            ->where('status', 'open')
            ->orWhere('status', 'in_progress')
            ->latest()
            ->first();
    }

    /**
     * Generate a unique token for the table.
     */
    public function generateUniqueToken(): void
    {
        $this->unique_token = Str::uuid();
        $this->save();
    }

    /**
     * Clear the unique token for the table.
     */
    public function clearUniqueToken(): void
    {
        $this->unique_token = null;
        $this->save();
    }

    /**
     * Set the status attribute and handle unique token logic.
     */
    public function setStatusAttribute($value)
    {
        $oldStatus = $this->attributes['status'] ?? null;
        $this->attributes['status'] = $value;

        // Only run logic if status is actually changing
        if ($oldStatus !== $value) {
            if ($oldStatus === 'closed' && $value === 'open' && empty($this->unique_token)) {
                $this->attributes['unique_token'] = (string) Str::uuid();
            }
            if ($oldStatus === 'open' && $value === 'closed') {
                $this->attributes['unique_token'] = null;
            }
        }
    }
}