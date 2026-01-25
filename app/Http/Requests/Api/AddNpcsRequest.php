<?php

namespace App\Http\Requests\Api;

use App\Models\Npc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddNpcsRequest extends FormRequest
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
            'count' => ['required', 'integer', 'min:1', 'max:50'],
            'difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard', 'expert'])],
            'archetype_distribution' => ['nullable', 'array'],
            'archetype_distribution.trader' => ['nullable', 'integer', 'min:0', 'max:100'],
            'archetype_distribution.merchant' => ['nullable', 'integer', 'min:0', 'max:100'],
            'archetype_distribution.explorer' => ['nullable', 'integer', 'min:0', 'max:100'],
            'archetype_distribution.miner' => ['nullable', 'integer', 'min:0', 'max:100'],
            'archetype_distribution.pirate_hunter' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'count.min' => 'At least 1 NPC must be created.',
            'count.max' => 'Cannot create more than 50 NPCs at once.',
            'difficulty.in' => 'Difficulty must be one of: easy, medium, hard, expert.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'difficulty' => $this->difficulty ?? 'medium',
        ]);
    }

    /**
     * Get the archetype distribution, validated
     */
    public function getArchetypeDistribution(): ?array
    {
        $distribution = $this->input('archetype_distribution');

        if (! $distribution) {
            return null;
        }

        // Filter to only valid archetypes
        $validArchetypes = array_keys(Npc::ARCHETYPES);
        $filtered = array_filter(
            $distribution,
            fn ($key) => in_array($key, $validArchetypes),
            ARRAY_FILTER_USE_KEY
        );

        // Ensure at least some distribution
        if (empty($filtered) || array_sum($filtered) === 0) {
            return null;
        }

        return $filtered;
    }
}
