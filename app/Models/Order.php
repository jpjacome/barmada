<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToEditor, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'table_id',
        'table_session_id',
        'status',
        'note',
        'created_by',
        'total_amount',
        'amount_paid',
        'amount_left',
        'subtotal',
        'tax_total',
        'service_charge_rate_bp',
        'service_charge',
        'grand_total',
        'is_grouped',
        'editor_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_left' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'is_grouped' => 'boolean',
    ];

    /**
     * Get the table that owns the order.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }
    
    /**
     * Get the total number of items in the order.
     */
    public function getTotalItemsAttribute()
    {
        return $this->items()->sum('quantity');
    }

    /**
     * Orders that count toward revenue and operations metrics —
     * everything except cancelled ones. [#12]
     */
    public function scopeCountable($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function getEditorNameAttribute()
    {
        return $this->editor ? $this->editor->name : null;
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }
}