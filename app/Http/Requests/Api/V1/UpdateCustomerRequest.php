<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
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
        $customerId = $this->route('customer');
        
        return [
            'email' => ['sometimes', 'email', 'max:150', Rule::unique('customers', 'email')->ignore($customerId, 'customer_id')],
            'name' => ['sometimes', 'string', 'max:100'],
            'branch_id' => ['sometimes', 'integer', 'exists:branches,branch_id'],
            'signup_date' => ['sometimes', 'date'],
        ];
    }
}
