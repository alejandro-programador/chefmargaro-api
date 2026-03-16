<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,order_id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed', 'refunded'])],
            'payment_date' => ['nullable', 'date'],
            'proof_image_url' => ['nullable', 'string', 'max:255', 'url'],
            'proof_image' => ['nullable', 'image', 'max:5120'], // Max 5MB
            'reference_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
