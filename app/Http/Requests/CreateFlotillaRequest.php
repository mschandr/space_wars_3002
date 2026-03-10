<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateFlotillaRequest extends FormRequest
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
            'flagship_ship_id' => ['required', 'string', 'uuid'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'flagship_ship_id.required' => 'Flagship ship ID is required',
            'flagship_ship_id.uuid' => 'Flagship ship ID must be a valid UUID',
            'name.max' => 'Flotilla name cannot exceed 255 characters',
        ];
    }
}
