<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Model;

class StockItem extends Model
{
    use BelongsToEditor;

    public const UNITS = ['unit', 'ml', 'cl', 'l', 'g', 'kg'];

    protected $fillable = ['editor_id', 'name', 'sku', 'unit', 'quantity_on_hand', 'reorder_level', 'unit_cost', 'is_active'];

    protected $casts = [
        'quantity_on_hand' => 'decimal:3',
        'reorder_level' => 'decimal:3',
        'unit_cost' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function components()
    {
        return $this->hasMany(ProductComponent::class);
    }

    public function isLow(): bool
    {
        return $this->reorder_level !== null
            && (float) $this->quantity_on_hand <= (float) $this->reorder_level;
    }
}
