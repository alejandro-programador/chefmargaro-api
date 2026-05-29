<?php

namespace App\Http\Resources\Api\V1;

use App\Support\PublicStorageUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComboResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->combo_id,
            'combo_id' => $this->combo_id,
            'name' => $this->name,
            'price_eur' => (float) $this->price_eur,
            'description' => $this->description,
            'category' => $this->category,
            'rolls_count' => $this->rolls_count,
            'includes_drink' => (bool) $this->includes_drink,
            'has_topping' => (bool) ($this->has_topping ?? false),
            'is_active' => (bool) $this->is_active,
            'image_url' => PublicStorageUrl::normalize($this->image_url),
            'branch_id' => $this->branch_id,
            'xetux_product_id' => $this->xetux_product_id,
            'xetux_item_id' => $this->xetux_item_id,
            'xetux_family_id' => $this->xetux_family_id,
            'branch' => $this->whenLoaded('branch', function () {
                return new BranchResource($this->branch);
            }),
            'extras' => $this->whenLoaded('extras', function () {
                return ExtraResource::collection($this->extras);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
