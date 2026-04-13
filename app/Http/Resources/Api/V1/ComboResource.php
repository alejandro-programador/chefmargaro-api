<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ComboResource extends JsonResource
{
    /**
     * Base URL for public files (Apache/XAMPP), without trailing /api.
     */
    protected static function publicAppBaseUrl(): string
    {
        $base = config('app.public_url');
        if (empty($base)) {
            $base = config('app.url');
        }
        $base = rtrim((string) $base, '/');
        if (str_ends_with($base, '/api')) {
            $base = substr($base, 0, -4);
        }

        return $base;
    }

    /**
     * Normalize combo image_url for clients (no /api in storage path).
     */
    protected static function normalizeComboImageUrl(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        // Fix stored URLs that incorrectly included /api before /storage/
        if (str_contains($trimmed, '/api/storage/app/public/')) {
            $trimmed = str_replace('/api/storage/app/public/', '/storage/app/public/', $trimmed);
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            // Legacy: /storage/combos/... (symlink style) -> /storage/app/public/combos/...
            if (str_contains($trimmed, '/storage/') && ! str_contains($trimmed, '/storage/app/public/')) {
                $trimmed = str_replace('/storage/', '/storage/app/public/', $trimmed);
            }

            return $trimmed;
        }

        $path = ltrim($trimmed, '/');
        if (str_starts_with($path, 'storage/app/public/')) {
            return static::publicAppBaseUrl().'/'.$path;
        }
        if (str_starts_with($path, 'storage/')) {
            return static::publicAppBaseUrl().'/storage/app/public/'.Str::after($path, 'storage/');
        }

        return static::publicAppBaseUrl().'/storage/app/public/'.$path;
    }

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
            'image_url' => static::normalizeComboImageUrl($this->image_url),
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
