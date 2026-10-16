<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboIncludedGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'max_quantity' => $this->max_quantity,
            'sort_order' => $this->sort_order,
            'products' => $this->when(
                $this->relationLoaded('products'),
                fn () => ComboIncludedProductResource::collection($this->products)
            ),
        ];
    }
}
