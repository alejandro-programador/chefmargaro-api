<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'name' => $this->name,
            'price_eur' => (float) $this->price_eur,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'branch_id' => $this->branch_id,
            'imageUrl' => $this->image_url ? asset($this->image_url) : null,
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
