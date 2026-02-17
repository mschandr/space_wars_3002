<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipyardInventory;
use App\Services\ShipyardInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipyardInventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShipyardInventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShipyardInventoryService::class);

        // Create some ship blueprints
        Ship::factory()->count(3)->create(['is_available' => true]);
    }

    public function test_generate_inventory_creates_ships(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'standard'],
        ]);

        $count = $this->service->generateInventory($poi);

        $this->assertGreaterThanOrEqual(2, $count);
        $this->assertLessThanOrEqual(4, $count);
        $this->assertEquals($count, ShipyardInventory::where('poi_id', $poi->id)->count());
    }

    public function test_inventory_size_matches_shipyard_class(): void
    {
        $galaxy = Galaxy::factory()->create();

        // Capital shipyard: 4-8 ships
        $capitalPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'capital'],
        ]);
        $count = $this->service->generateInventory($capitalPoi);
        $this->assertGreaterThanOrEqual(4, $count);
        $this->assertLessThanOrEqual(8, $count);

        // Light shipyard: 1-3 ships
        $lightPoi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'light'],
        ]);
        $count = $this->service->generateInventory($lightPoi);
        $this->assertGreaterThanOrEqual(1, $count);
        $this->assertLessThanOrEqual(3, $count);
    }

    public function test_ensure_inventory_is_idempotent(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'standard'],
        ]);

        $this->service->ensureInventory($poi);
        $firstCount = ShipyardInventory::where('poi_id', $poi->id)->count();

        // Call again — should not generate more
        $this->service->ensureInventory($poi);
        $secondCount = ShipyardInventory::where('poi_id', $poi->id)->count();

        $this->assertEquals($firstCount, $secondCount);
    }

    public function test_every_ship_has_unique_name(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'capital'],
        ]);

        $this->service->generateInventory($poi);

        $names = ShipyardInventory::where('poi_id', $poi->id)->pluck('name');
        $this->assertEquals($names->count(), $names->unique()->count());
    }

    public function test_every_ship_has_valid_rarity(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'capital'],
        ]);

        $this->service->generateInventory($poi);

        $ships = ShipyardInventory::where('poi_id', $poi->id)->get();
        $validRarities = ['common', 'uncommon', 'rare', 'epic', 'unique', 'exotic'];

        foreach ($ships as $ship) {
            $this->assertContains($ship->getRawOriginal('rarity'), $validRarities);
        }
    }

    public function test_get_available_ships_excludes_sold(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        ShipyardInventory::factory()->create(['poi_id' => $poi->id, 'is_sold' => false]);
        ShipyardInventory::factory()->create(['poi_id' => $poi->id, 'is_sold' => true]);

        $available = $this->service->getAvailableShips($poi);

        $this->assertEquals(1, $available->count());
    }

    public function test_generate_marks_poi_as_generated(): void
    {
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'attributes' => ['shipyard_class' => 'standard'],
        ]);

        $this->assertNull($poi->inventory_generated_at);

        $this->service->generateInventory($poi);

        $poi->refresh();
        $this->assertNotNull($poi->inventory_generated_at);
    }
}
