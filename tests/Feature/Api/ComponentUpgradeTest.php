<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private PlayerShip $ship;

    private ShipComponent $component;

    private PlayerShipComponent $installed;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 500000,
        ]);

        $blueprint = Ship::factory()->create(['weapon_slots' => 3]);

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $blueprint->id,
            'weapon_slots' => 3,
            'is_active' => true,
        ]);

        $this->component = ShipComponent::create([
            'name' => 'Upgradeable Laser',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'Test weapon',
            'slots_required' => 1,
            'base_price' => 10000,
            'rarity' => 'common',
            'effects' => ['damage' => 50, 'accuracy' => 0.90],
            'is_available' => true,
            'max_upgrade_level' => 6,
            'upgrade_cost_base' => 3000,
        ]);

        $this->installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->component->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);
    }

    public function test_get_upgrade_info(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade-info");

        $response->assertOk()
            ->assertJsonPath('data.can_upgrade', true)
            ->assertJsonPath('data.current_level', 0)
            ->assertJsonPath('data.max_level', 6)
            ->assertJsonPath('data.upgrade_cost', 3000)
            ->assertJsonPath('data.can_afford', true);
    }

    public function test_upgrade_component_success(): void
    {
        $initialCredits = $this->player->credits;

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade");

        $response->assertOk()
            ->assertJsonPath('data.new_level', 1)
            ->assertJsonPath('data.cost', 3000);

        $this->player->refresh();
        $this->assertEquals($initialCredits - 3000, $this->player->credits);

        $this->installed->refresh();
        $this->assertEquals(1, $this->installed->upgrade_level);
    }

    public function test_upgrade_requires_authentication(): void
    {
        $response = $this->postJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade");
        $response->assertUnauthorized();
    }

    public function test_upgrade_fails_for_max_level_component(): void
    {
        $this->installed->upgrade_level = 6;
        $this->installed->save();

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade");

        $response->assertJsonPath('success', false);
    }

    public function test_upgrade_info_shows_not_affordable(): void
    {
        $this->player->credits = 100;
        $this->player->save();

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade-info");

        $response->assertOk()
            ->assertJsonPath('data.can_upgrade', true)
            ->assertJsonPath('data.can_afford', false);
    }

    public function test_multiple_upgrades_increase_cost(): void
    {
        // First upgrade: 3000
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade");
        $response->assertOk();
        $this->assertEquals(3000, $response->json('data.cost'));

        // Second upgrade info should show higher cost
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/components/{$this->installed->id}/upgrade-info");
        $response->assertOk();
        // Level 1: 3000 * (1 + 1 * 0.5) = 4500
        $this->assertEquals(4500, $response->json('data.upgrade_cost'));
    }
}
