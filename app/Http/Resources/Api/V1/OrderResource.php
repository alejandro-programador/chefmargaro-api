<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->order_id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', function () {
                return new CustomerResource($this->customer);
            }),
            'order_date' => $this->order_date,
            'total_amount' => (float) $this->total_amount,
            'payment_status' => $this->payment_status,
            'delivery_type' => $this->delivery_type,
            'order_status' => $this->order_status,
            'tracking_token' => $this->tracking_token,
            'tracking_link' => $this->tracking_token 
                ? (rtrim(config('app.frontend_url', 'http://localhost:3000'), '/') . '/order-tracking/' . $this->tracking_token)
                : null,
            'order_items' => $this->whenLoaded('orderItems', function () {
                return OrderItemResource::collection($this->orderItems);
            }),
            'payments' => $this->whenLoaded('payments', function () {
                return PaymentResource::collection($this->payments);
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
