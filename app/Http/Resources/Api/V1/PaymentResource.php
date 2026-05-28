<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->payment_id,
            'order_id' => $this->order_id,
            'order' => $this->whenLoaded('order', function () {
                return new OrderResource($this->order);
            }),
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_date' => $this->payment_date,
            'proof_image_url' => $this->proof_image_url,
            'reference_number' => $this->reference_number,
            'payment_reference_number' => $this->payment_reference_number,
            'reported_amount' => $this->reported_amount,
            'verifications' => $this->whenLoaded('verifications', function () {
                return PaymentVerificationResource::collection($this->verifications);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
