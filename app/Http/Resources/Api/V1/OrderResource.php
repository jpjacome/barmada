<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The order as the staff app sees it: header, note, per-item paid state
 * and the money summary computed from items (the source of truth for
 * amounts — denormalized order columns are legacy).
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->items;
        $total = $items->sum(fn ($item) => $item->price);
        $paid = $items->where('is_paid', true)->sum(fn ($item) => $item->price);

        return [
            'id' => $this->id,
            'table_id' => $this->table_id,
            'table_number' => $this->table?->table_number,
            'table_session_id' => $this->table_session_id,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'total' => round((float) $total, 2),
            'paid' => round((float) $paid, 2),
            'left' => round((float) ($total - $paid), 2),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'item_index' => $item->item_index,
                'price' => (float) $item->price,
                'is_paid' => (bool) $item->is_paid,
            ])->values(),
        ];
    }
}
