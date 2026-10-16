<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboIncludedProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'xetux_product_id' => $this->xetux_product_id,
            'xetux_item_id' => $this->xetux_item_id,
            'xetux_family_id' => $this->xetux_family_id,
            'product_name' => $this->product_name,
            'sort_order' => $this->sort_order,
        ];
    }
}
