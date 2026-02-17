<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Models\Ship;
use App\Models\ShipComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalvageYardShipSellingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $poi;

    private Ship $blueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();
        $this->poi = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
        ]);

        $this->blueprint = Ship::factory()->create([
            'base_price' => 100000,
            'is_available' => true,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->poi->id,
            'credits' => 50000,
        ]);

        // Active ship
        PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->blueprint->id,
            'is_active' => true,
            'hull' => 100,
            'max_hull' => 100,
        ]);
    }

    public function test_sell_ship_credits_player_correct_amount(): void
    {
        // Create a second ship (inactive) to sell
        $shipToSell = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->blueprint->id,
            'is_active' => false,
            'hull' => 80,
            'max_hull' => 100,
        ]);

        $initialCredits = $this->player->credits;

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/salvage-yard/sell-ship", [
                'ship_uuid' => $shipToSell->uuid,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->player->refresh();
        $expectedCredits = (int) round(100000 * 0.35 * 0.8); // base_price * sell_pct * condition
        $this->assertEquals($initialCredits + $expectedCredits, $this->player->credits);
    }

    public function test_sell_ship_creates_component_entries_in_salvage_yard(): void
    {
        $component = ShipComponent::factory()->create([
            'is_available' => true,
            'base_price' => 5000,
        ]);

        $shipToSell = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->blueprint->id,
            'is_active' => false,
            'hull' => 100,
            'max_hull' => 100,
        ]);

        PlayerShipComponent::create([
            'player_ship_id' => $shipToSell->id,
            'ship_component_id' => $component->id,
            'slot_type' => 'weapon_slot',
            'slot_index' => 1,
            'condition' => 85,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/salvage-yard/sell-ship", [
                'ship_uuid' => $shipToSell->uuid,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.components_salvaged', 1);

        // Component should appear in salvage yard inventory at this POI
        $salvageItem = SalvageYardInventory::where('poi_id', $this->poi->id)
            ->where('ship_component_id', $component->id)
            ->first();

        $this->assertNotNull($salvageItem);
        $this->assertEquals(85, $salvageItem->condition);
        $this->assertEquals('salvage', $salvageItem->source);
    }

    public function test_cannot_sell_only_ship(): void
    {
        // Player only has one ship (the active one)
        // Try selling the active ship
        $activeShip = PlayerShip::where('player_id', $this->player->id)->first();

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/salvage-yard/sell-ship", [
                'ship_uuid' => $activeShip->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_cannot_sell_active_ship(): void
    {
        // Create second ship so we have 2
        PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->blueprint->id,
            'is_active' => false,
        ]);

        $activeShip = PlayerShip::where('player_id', $this->player->id)
            ->where('is_active', true)
            ->first();

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/salvage-yard/sell-ship", [
                'ship_uuid' => $activeShip->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_sold_ship_is_deleted(): void
    {
        $shipToSell = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->blueprint->id,
            'is_active' => false,
        ]);

        $shipId = $shipToSell->id;

        $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/salvage-yard/sell-ship", [
                'ship_uuid' => $shipToSell->uuid,
            ]);

        $this->assertNull(PlayerShip::find($shipId));
    }

    public function test_browse_salvage_yard_by_system_triggers_lazy_gen(): void
    {
        ShipComponent::factory()->count(3)->create(['is_available' => true]);

        $this->assertNull($this->poi->inventory_generated_at);

        $response = $this->actingAs($this->user)
            ->getJson("/api/systems/{$this->poi->uuid}/salvage-yard");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->poi->refresh();
        $this->assertNotNull($this->poi->inventory_generated_at);
    }
}
