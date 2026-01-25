<?php

namespace App\Http\Requests\Api;

use App\Models\Npc;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddNpcsRequest extends FormRequest
{
    /**
     * Allow all requests to proceed to validation.
     *
     * @return bool `true` to allow the request, `false` to deny it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Define validation rules for adding NPCs, covering count, difficulty, and optional per-archetype distribution.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string> Validation rules keyed by request field.
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
     * Provide custom validation messages for this request's rules.
     *
     * @return array<string,string> Mapping of validation rule keys to error messages.
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
     * Ensure default request values are present before validation.
     *
     * Sets the request's `difficulty` to `"medium"` when not provided.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'difficulty' => $this->difficulty ?? 'medium',
        ]);
    }

    /**
     * Retrieve the validated archetype distribution from the request.
     *
     * @return array|null Associative array mapping recognized archetype keys to their numeric values, or `null` if no valid distribution is present or all values sum to zero.
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