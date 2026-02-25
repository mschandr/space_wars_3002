<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipyardInventory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipyardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $poi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();
        $this->poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'attributes' => ['shipyard_class' => 'standard'],
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->poi->id,
            'credits' => 500000,
        ]);

        // Give player a starter ship
        $starterBlueprint = Ship::factory()->starter()->create();
        PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $starterBlueprint->id,
            'is_active' => true,
        ]);

        // Create ship blueprints for the shipyard
        Ship::factory()->count(3)->create(['is_available' => true]);
    }

    public function test_get_shipyard_triggers_lazy_generation(): void
    {
        $this->assertNull($this->poi->inventory_generated_at);

        $response = $this->actingAs($this->user)
            ->getJson("/api/systems/{$this->poi->uuid}/shipyard");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system',
                    'ships',
                ],
            ]);

        $this->poi->refresh();
        $this->assertNotNull($this->poi->inventory_generated_at);

        // Should have generated ships
        $ships = $response->json('data.ships');
        $this->assertNotEmpty($ships);
    }

    public function test_second_get_does_not_regenerate(): void
    {
        $this->actingAs($this->user)
            ->getJson("/api/systems/{$this->poi->uuid}/shipyard");

        $firstCount = ShipyardInventory::where('poi_id', $this->poi->id)->count();

        $this->actingAs($this->user)
            ->getJson("/api/systems/{$this->poi->uuid}/shipyard");

        $secondCount = ShipyardInventory::where('poi_id', $this->poi->id)->count();

        $this->assertEquals($firstCount, $secondCount);
    }

    public function test_purchase_creates_player_ship_with_matching_stats(): void
    {
        $blueprint = Ship::factory()->create([
            'is_available' => true,
            'base_price' => 10000,
        ]);

        $item = ShipyardInventory::factory()->create([
            'poi_id' => $this->poi->id,
            'ship_id' => $blueprint->id,
            'price' => 20000,
            'hull_strength' => 150,
            'shield_strength' => 75,
            'cargo_capacity' => 50,
            'speed' => 100,
            'weapon_slots' => 4,
            'utility_slots' => 2,
            'max_fuel' => 120,
            'sensors' => 2,
            'warp_drive' => 1,
            'weapons' => 15,
            'is_sold' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/shipyard/purchase", [
                'inventory_uuid' => $item->uuid,
                'custom_name' => 'My Custom Ship',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ship.name', 'My Custom Ship')
            ->assertJsonPath('data.ship.hull', 150)
            ->assertJsonPath('data.ship.max_hull', 150)
            ->assertJsonPath('data.ship.cargo_hold', 50)
            ->assertJsonPath('data.ship.sensors', 2)
            ->assertJsonPath('data.ship.warp_drive', 1);
    }

    public function test_purchase_marks_item_sold(): void
    {
        $blueprint = Ship::factory()->create(['is_available' => true, 'base_price' => 5000]);
        $item = ShipyardInventory::factory()->create([
            'poi_id' => $this->poi->id,
            'ship_id' => $blueprint->id,
            'price' => 5000,
            'is_sold' => false,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/shipyard/purchase", [
                'inventory_uuid' => $item->uuid,
            ]);

        $item->refresh();
        $this->assertTrue($item->is_sold);
    }

    public function test_purchase_fails_with_insufficient_credits(): void
    {
        $this->player->update(['credits' => 100]);

        $blueprint = Ship::factory()->create(['is_available' => true]);
        $item = ShipyardInventory::factory()->create([
            'poi_id' => $this->poi->id,
            'ship_id' => $blueprint->id,
            'price' => 50000,
            'is_sold' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/shipyard/purchase", [
                'inventory_uuid' => $item->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_purchase_fails_for_already_sold_ship(): void
    {
        $blueprint = Ship::factory()->create(['is_available' => true]);
        $item = ShipyardInventory::factory()->create([
            'poi_id' => $this->poi->id,
            'ship_id' => $blueprint->id,
            'price' => 5000,
            'is_sold' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/shipyard/purchase", [
                'inventory_uuid' => $item->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_shipyard_inventory_detail_endpoint(): void
    {
        $blueprint = Ship::factory()->create(['is_available' => true]);
        $item = ShipyardInventory::factory()->create([
            'poi_id' => $this->poi->id,
            'ship_id' => $blueprint->id,
            'rarity' => 'rare',
            'is_sold' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/shipyard-inventory/{$item->uuid}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.uuid', (string) $item->uuid)
            ->assertJsonPath('data.rarity', 'rare')
            ->assertJsonPath('data.rarity_label', 'Rare')
            ->assertJsonPath('data.rarity_color', 'blue');
    }

    public function test_system_not_found_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/systems/00000000-0000-0000-0000-000000000000/shipyard');

        $response->assertNotFound();
    }
}
