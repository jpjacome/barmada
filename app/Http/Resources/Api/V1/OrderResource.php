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
        $total = \App\Support\Money::sum($items->pluck('price'));
        $paid = \App\Support\Money::sum($items->where('is_paid', true)->pluck('price'));

        return [
            'id' => $this->id,
            'table_id' => $this->table_id,
            'table_number' => $this->table?->table_number,
            'table_session_id' => $this->table_session_id,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'total' => $total,
            'paid' => $paid,
            'left' => \App\Support\Money::subtract($total, $paid),
            'subtotal' => (float) ($this->subtotal ?? $total),
            'tax_total' => (float) ($this->tax_total ?? 0),
            'service_charge' => (float) ($this->service_charge ?? 0),
            'grand_total' => (float) ($this->grand_total ?? $total),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'item_index' => $item->item_index,
                'price' => (float) $item->price,
                'net_price' => $item->net_price !== null ? (float) $item->net_price : null,
                'tax_amount' => $item->tax_amount !== null ? (float) $item->tax_amount : null,
                'tax_code' => $item->tax_code,
                'is_paid' => (bool) $item->is_paid,
                'paid_at' => $item->paid_at?->toIso8601String(),
                'payment_method' => $item->payment_method,
            ])->values(),
        ];
    }
}
