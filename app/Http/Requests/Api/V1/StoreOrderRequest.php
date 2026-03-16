<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
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
            'customer_id' => ['required', 'integer', 'exists:customers,customer_id'],
            'order_date' => ['required', 'date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_status' => ['nullable', 'string', 'max:20'],
            'delivery_type' => ['nullable', 'string', 'max:20'],
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.product_id' => [
                'nullable',
                'integer',
                'exists:products,product_id',
            ],
            'order_items.*.combo_id' => [
                'nullable',
                'integer',
                'exists:combos,combo_id',
            ],
            'order_items.*.quantity' => ['required', 'integer', 'min:1'],
            'order_items.*.combinaciones' => ['nullable', 'array'],
            'order_items.*.combinaciones.*.textura' => ['nullable', 'string'],
            'order_items.*.combinaciones.*.proteina' => ['nullable', 'string'],
            'order_items.*.combinaciones.*.complemento' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $orderItems = $this->input('order_items', []);
            
            foreach ($orderItems as $index => $item) {
                $hasProductId = isset($item['product_id']) && $item['product_id'] !== null && $item['product_id'] !== '';
                $hasComboId = isset($item['combo_id']) && $item['combo_id'] !== null && $item['combo_id'] !== '';
                
                if ($hasProductId && $hasComboId) {
                    $validator->errors()->add(
                        "order_items.{$index}.product_id",
                        'Un item de orden no puede tener tanto product_id como combo_id.'
                    );
                    $validator->errors()->add(
                        "order_items.{$index}.combo_id",
                        'Un item de orden no puede tener tanto product_id como combo_id.'
                    );
                }
                
                if (!$hasProductId && !$hasComboId) {
                    $validator->errors()->add(
                        "order_items.{$index}.product_id",
                        'Un item de orden debe tener product_id o combo_id.'
                    );
                }
            }
        });
    }
}
