<?php

namespace App\Http\Resources\Api\V1;

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
            'is_active' => (bool) $this->is_active,
            'image_url' => $this->image_url
                ? (function () {
                    if (str_starts_with($this->image_url, 'http')) {
                        // If it's already a full URL, check if it needs to be updated to new format
                        if (str_contains($this->image_url, '/storage/') && !str_contains($this->image_url, '/api/storage/app/public/')) {
                            // Convert old format to new format
                            return str_replace('/storage/', '/api/storage/app/public/', $this->image_url);
                        }
                        return $this->image_url;
                    }
                    // Build full URL with new format
                    return rtrim(config('app.url'), '/') . '/api/storage/app/public/' . ltrim($this->image_url, '/');
                })()
                : null,
            'branch_id' => $this->branch_id,
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
