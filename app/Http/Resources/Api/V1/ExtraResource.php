<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ExtraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $normalizedImageUrl = null;
        if (! empty($this->image_url)) {
            $normalizedImageUrl = (string) $this->image_url;

            // Normalize legacy values that may contain duplicated segment: /storage/app/public/app/public/...
            $normalizedImageUrl = str_replace('/storage/app/public/app/public/', '/storage/app/public/', $normalizedImageUrl);

            // Normalize old format /storage/extras/... to /storage/app/public/extras/...
            if (Str::startsWith($normalizedImageUrl, '/storage/') && ! Str::startsWith($normalizedImageUrl, '/storage/app/public/')) {
                $normalizedImageUrl = str_replace('/storage/', '/storage/app/public/', $normalizedImageUrl);
            }
        }

        return [
            'id' => $this->extra_id,
            'extra_id' => $this->extra_id,
            'branch_id' => $this->branch_id,
            'title' => $this->title,
            'name' => $this->title, // Alias para compatibilidad con el frontend
            'description' => $this->description,
            'image_url' => $normalizedImageUrl,
            'price_eur' => (float) $this->price_eur,
            'quantity' => (int) $this->quantity,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
