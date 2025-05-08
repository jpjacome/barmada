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
        'editor_id',
        'table_number', // Added for per-editor numbering
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
     * Get the sessions for the table.
     */
    public function sessions()
    {
        return $this->hasMany(TableSession::class);
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

    /**
     * Get the editor for the table.
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    /**
     * Get the editor name attribute.
     */
    public function getEditorNameAttribute()
    {
        return $this->editor ? $this->editor->name : null;
    }

    /**
     * Handle model events for Table.
     */
    protected static function booted()
    {
        static::updating(function (Table $table) {
            $original = $table->getOriginal('status');
            $new = $table->status;
            // If status changes from closed or pending_approval to open, create a new TableSession
            if (in_array($original, ['closed', 'pending_approval']) && $new === 'open') {
                $today = now()->toDateString();
                $maxSessionNumber = $table->sessions()->where('date', $today)->max('session_number');
                $sessionNumber = $maxSessionNumber ? $maxSessionNumber + 1 : 1;
                $token = (string) Str::uuid();
                $table->unique_token = $token;
                $table->saveQuietly(); // Avoid recursion
                \App\Models\TableSession::create([
                    'table_id' => $table->id,
                    'session_number' => $sessionNumber,
                    'date' => $today,
                    'unique_token' => $token,
                    'status' => 'open',
                    'opened_at' => now(),
                    'opened_by' => auth()->id() ?? 1, // fallback to 1 if not authenticated
                    'editor_id' => $table->editor_id,
                ]);
            }
            // If status changes from open to closed, close the current TableSession
            if ($original === 'open' && $new === 'closed') {
                $openSession = $table->sessions()->where('status', 'open')->latest('opened_at')->first();
                if ($openSession) {
                    $openSession->status = 'closed';
                    $openSession->closed_at = now();
                    $openSession->closed_by = auth()->id() ?? 1;
                    $openSession->save();
                }
                $table->unique_token = null;
                $table->saveQuietly();
            }
        });
    }
}