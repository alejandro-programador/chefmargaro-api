<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_item_id' => $this->order_item_id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', function () {
                return new ProductResource($this->product);
            }),
            'combo_id' => $this->combo_id,
            'combo' => $this->whenLoaded('combo', function () {
                return new ComboResource($this->combo);
            }),
            'extra_id' => $this->extra_id,
            'extra' => $this->whenLoaded('extra', function () {
                return new ExtraResource($this->extra);
            }),
            'quantity' => (int) $this->quantity,
            'combinaciones' => $this->combinaciones,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

