<?php

namespace App\Http\Requests\Api;

use App\Enums\Galaxy\GalaxySizeTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGalaxyRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100', 'unique:galaxies,name'],
            'width' => ['required_without:size_tier', 'integer', 'min:100', 'max:3000'],
            'height' => ['required_without:size_tier', 'integer', 'min:100', 'max:3000'],
            'stars' => ['required_without:size_tier', 'integer', 'min:50', 'max:10000'],
            'grid_size' => ['nullable', 'integer', 'min:5', 'max:50'],
            'game_mode' => ['required', Rule::in(['multiplayer', 'single_player', 'mixed'])],
            'npc_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'npc_difficulty' => ['nullable', Rule::in(['easy', 'medium', 'hard', 'expert'])],
            'skip_mirror' => ['nullable', 'boolean'],
            'skip_pirates' => ['nullable', 'boolean'],
            'skip_precursors' => ['nullable', 'boolean'],
            'async' => ['nullable', 'boolean'],
            'size_tier' => ['nullable', Rule::in(array_column(GalaxySizeTier::cases(), 'value'))],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'width.min' => 'Galaxy width must be at least 100 units.',
            'width.max' => 'Galaxy width cannot exceed 3000 units.',
            'width.required_without' => 'Galaxy width is required when not using a size tier.',
            'height.min' => 'Galaxy height must be at least 100 units.',
            'height.max' => 'Galaxy height cannot exceed 3000 units.',
            'height.required_without' => 'Galaxy height is required when not using a size tier.',
            'stars.min' => 'Galaxy must have at least 50 stars.',
            'stars.max' => 'Galaxy cannot have more than 10000 stars.',
            'stars.required_without' => 'Star count is required when not using a size tier.',
            'game_mode.in' => 'Game mode must be one of: multiplayer, single_player, mixed.',
            'npc_difficulty.in' => 'NPC difficulty must be one of: easy, medium, hard, expert.',
            'size_tier.in' => 'Size tier must be one of: small, medium, large.',
        ];
    }

    /**
     * Get validated data with defaults applied
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'grid_size' => 10,
            'npc_count' => 0,
            'npc_difficulty' => 'medium',
            'skip_mirror' => false,
            'skip_pirates' => false,
            'skip_precursors' => false,
            'async' => false,
            'size_tier' => null,
        ], $validated);
    }

    /**
     * Check if this request is for a tiered galaxy.
     */
    public function isTieredGalaxy(): bool
    {
        return $this->filled('size_tier');
    }

    /**
     * Get the size tier enum if specified.
     */
    public function getSizeTier(): ?GalaxySizeTier
    {
        if (! $this->filled('size_tier')) {
            return null;
        }

        return GalaxySizeTier::from($this->input('size_tier'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'grid_size' => $this->grid_size ?? 10,
            'npc_count' => $this->npc_count ?? 0,
            'npc_difficulty' => $this->npc_difficulty ?? 'medium',
            'skip_mirror' => $this->skip_mirror ?? false,
            'skip_pirates' => $this->skip_pirates ?? false,
            'skip_precursors' => $this->skip_precursors ?? false,
        ]);

        // Auto-set npc_count for single_player if not specified
        if ($this->game_mode === 'single_player' && ($this->npc_count ?? 0) < 1) {
            $this->merge(['npc_count' => 5]);
        }
    }
}
