<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetFlagshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'ship_id' => ['required', 'string', 'uuid'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'ship_id.required' => 'Ship ID is required',
            'ship_id.uuid' => 'Ship ID must be a valid UUID',
        ];
    }
}
