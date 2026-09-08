<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEditor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToEditor, HasFactory;
    
    protected $fillable = [
        'name', 'price', 'is_available', 'icon_type', 'icon_value', 'category_id', 'editor_id', 'photo', 'description'
    ];

    protected $casts = [
        'is_available' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Whether this product appears on any order.
     *
     * order_items.product_id is a plain foreign key with no cascade, so
     * deleting a product that has ever been sold raised a raw database
     * error (a 500 on the API, an unhandled exception in Livewire).
     * Sold products are retired with the 86 toggle instead — the history
     * has to survive for the bill and for reporting.
     */
    public function hasOrderHistory(): bool
    {
        return $this->orderItems()->exists();
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function getEditorNameAttribute()
    {
        return $this->editor ? $this->editor->name : null;
    }
}