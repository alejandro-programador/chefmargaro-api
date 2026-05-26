<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckoutOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,branch_id'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['required', 'email', 'max:150'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.cedula' => ['nullable', 'string', 'max:50'],
            'delivery_type' => ['required', 'string', 'in:delivery,pickup'],
            'delivery_address' => ['nullable', 'string', 'max:500'],
            'reference_point' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'cart_lines' => ['required', 'array', 'min:1'],
            'cart_lines.*.type' => ['required', 'string', 'in:combo,extra,product'],
            'cart_lines.*.name' => ['required', 'string', 'max:200'],
            'cart_lines.*.quantity' => ['required', 'integer', 'min:1'],
            'cart_lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'cart_lines.*.combo_id' => ['nullable', 'integer', 'exists:combos,combo_id'],
            'cart_lines.*.extra_id' => ['nullable', 'integer', 'exists:extras,extra_id'],
            'cart_lines.*.product_id' => ['nullable', 'integer', 'exists:products,product_id'],
            'cart_lines.*.cart_key' => ['nullable', 'string', 'max:255'],
            'cart_lines.*.parent_combo_id' => ['nullable', 'integer'],
            'cart_lines.*.combinaciones' => ['nullable', 'array'],
            'cart_lines.*.rollsConSesamo' => ['nullable', 'boolean'],
            'cart_lines.*.rollsConQuesoCremaCebollin' => ['nullable', 'boolean'],
        ];
    }
}
