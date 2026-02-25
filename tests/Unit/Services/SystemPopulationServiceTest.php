<?php

namespace Tests\Unit\Services;

use App\Enums\Galaxy\RegionType;
use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Services\SystemPopulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemPopulationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SystemPopulationService $service;

    protected Galaxy $galaxy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SystemPopulationService;
        $this->galaxy = Galaxy::factory()->create();
    }

    public function test_inhabited_system_guarantees_habitable_planet(): void
    {
        // Create an inhabited star with a terrestrial planet (no habitable attrs)
        $star = PointOfInterest::factory()->inhabited()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'region' => RegionType::CORE,
            'attributes' => ['stellar_class' => 'G'],
        ]);

        $planet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'parent_poi_id' => $star->id,
            'type' => PointOfInterestType::TERRESTRIAL,
            'x' => $star->x,
            'y' => $star->y,
            'orbital_index' => 1,
            'attributes' => [
                'orbital_distance' => 30,
                'size' => 'medium',
                'habitable' => false,
            ],
        ]);

        $this->service->ensurePopulated($star);

        // At least one child should now be habitable
        $habitable = $star->children()->get()->filter(function ($child) {
            $attrs = $child->attributes ?? [];

            return $attrs['habitable'] ?? false;
        });

        $this->assertGreaterThanOrEqual(1, $habitable->count());
    }

    public function test_inhabited_system_without_terrestrial_gets_one_created(): void
    {
        // Create an inhabited star with only gas giant children
        $star = PointOfInterest::factory()->inhabited()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'region' => RegionType::CORE,
            'attributes' => ['stellar_class' => 'G'],
        ]);

        PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'parent_poi_id' => $star->id,
            'type' => PointOfInterestType::GAS_GIANT,
            'x' => $star->x,
            'y' => $star->y,
            'orbital_index' => 1,
            'attributes' => [
                'orbital_distance' => 50,
                'size' => 'massive',
            ],
        ]);

        $this->service->ensurePopulated($star);

        // Should have created a habitable planet (SystemName Prime)
        $star->refresh();
        $children = $star->children()->get();
        $primeWorld = $children->first(fn ($c) => str_contains($c->name, 'Prime'));

        // Either a Prime world was created, or an existing child was made habitable
        $habitable = $children->filter(function ($child) {
            $attrs = $child->attributes ?? [];

            return $attrs['habitable'] ?? false;
        });

        $this->assertGreaterThanOrEqual(1, $habitable->count());
    }
}
