<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToEditor;

    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SALE = 'sale';
    public const TYPE_WASTE = 'waste';
    public const TYPE_COUNT = 'count';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSAL = 'reversal';

    protected $fillable = ['editor_id', 'stock_item_id', 'type', 'quantity', 'unit_cost', 'reference_type', 'reference_id', 'user_id', 'note', 'occurred_at'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    public function stockItem()
    {
        return $this->belongsTo(StockItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
