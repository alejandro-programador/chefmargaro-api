<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'verification_id' => $this->verification_id,
            'payment_id' => $this->payment_id,
            'payment' => $this->whenLoaded('payment', function () {
                return new PaymentResource($this->payment);
            }),
            'verifier_id' => $this->verifier_id,
            'verifier' => $this->whenLoaded('verifier', function () {
                return new UserResource($this->verifier);
            }),
            'verification_status' => $this->verification_status,
            'verification_date' => $this->verification_date,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
