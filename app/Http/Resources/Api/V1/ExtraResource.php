<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExtraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->extra_id,
            'extra_id' => $this->extra_id,
            'branch_id' => $this->branch_id,
            'xetux_product_id' => $this->xetux_product_id,
            'xetux_item_id' => $this->xetux_item_id,
            'xetux_family_id' => $this->xetux_family_id,
            'title' => $this->title,
            'name' => $this->title, // Alias para compatibilidad con el frontend
            'description' => $this->description,
            'image_url' => PublicStorageUrl::normalize($this->image_url),
            'price_eur' => (float) $this->price_eur,
            'quantity' => (int) $this->quantity,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
