<?php

namespace App\Http\Requests\Api;

use App\Enums\Galaxy\GalaxySizeTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGalaxyRequest extends FormRequest
{
    /**
     * Allow any user to make this request.
     *
     * @return bool `true` if the request is authorized, `false` otherwise.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
         * Define validation rules for an incoming galaxy creation request.
         *
         * @return array<string, ValidationRule|array<mixed>|string> An associative array mapping request field names to their validation rules.
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
     * Provide custom validation error messages for the request's validation rules.
     *
     * @return array<string,string> Mapping of validation rule keys (e.g., `field.rule`) to message strings.
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
     * Return validated request data merged with default values for optional galaxy fields.
     *
     * Defaults applied when not present in the validated data:
     * - `grid_size`: 10
     * - `npc_count`: 0
     * - `npc_difficulty`: "medium"
     * - `skip_mirror`: false
     * - `skip_pirates`: false
     * - `skip_precursors`: false
     * - `async`: false
     * - `size_tier`: null
     *
     * @return array The validated input merged with the defaults listed above.
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
     * Determines whether the request specifies a size tier for the galaxy.
     *
     * @return bool `true` if the request contains a non-empty `size_tier` value, `false` otherwise.
     */
    public function isTieredGalaxy(): bool
    {
        return $this->filled('size_tier');
    }

    /**
     * Get the GalaxySizeTier enum for the request's `size_tier` when present.
     *
     * @return GalaxySizeTier|null The enum instance for the requested size tier, or `null` if `size_tier` is not present.
     * @throws \ValueError If `size_tier` is present but does not correspond to a valid GalaxySizeTier value.
     */
    public function getSizeTier(): ?GalaxySizeTier
    {
        if (! $this->filled('size_tier')) {
            return null;
        }

        return GalaxySizeTier::from($this->input('size_tier'));
    }

    /**
     * Merge default request values before validation and adjust NPC count for single-player mode.
     *
     * Merges defaults into the request data when fields are not provided: grid_size = 10, npc_count = 0,
     * npc_difficulty = 'medium', skip_mirror = false, skip_pirates = false, skip_precursors = false.
     * If game_mode is 'single_player' and npc_count is less than 1, sets npc_count to 5.
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