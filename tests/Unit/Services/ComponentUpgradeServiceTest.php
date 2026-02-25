<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\ShipComponent;
use App\Models\User;
use App\Services\ComponentUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentUpgradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ComponentUpgradeService $service;

    private Player $player;

    private PlayerShip $ship;

    private ShipComponent $commonComponent;

    private ShipComponent $exoticComponent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ComponentUpgradeService::class);

        $galaxy = Galaxy::factory()->create();
        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 500000,
        ]);

        $blueprint = Ship::factory()->create([
            'class' => 'starter',
            'weapon_slots' => 2,
        ]);

        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $blueprint->id,
            'weapon_slots' => 2,
            'is_active' => true,
        ]);

        $this->commonComponent = ShipComponent::create([
            'name' => 'Test Common Weapon',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'A common weapon for testing',
            'slots_required' => 1,
            'base_price' => 5000,
            'rarity' => 'common',
            'effects' => ['damage' => 25, 'accuracy' => 0.85],
            'is_available' => true,
            'max_upgrade_level' => 6,
            'upgrade_cost_base' => 1500,
        ]);

        $this->exoticComponent = ShipComponent::create([
            'name' => 'Test Exotic Weapon',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'An exotic weapon',
            'slots_required' => 1,
            'base_price' => 250000,
            'rarity' => 'exotic',
            'effects' => ['damage' => 350],
            'is_available' => true,
            'max_upgrade_level' => 0,
            'upgrade_cost_base' => 0,
        ]);
    }

    public function test_cost_formula_at_level_zero(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        // Level 0: cost = 1500 * (1 + 0 * 0.5) = 1500
        $cost = $this->service->calculateUpgradeCost($installed);
        $this->assertEquals(1500, $cost);
    }

    public function test_cost_scales_with_level(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 5,
        ]);

        // Level 5: cost = 1500 * (1 + 5 * 0.5) = 1500 * 3.5 = 5250
        $cost = $this->service->calculateUpgradeCost($installed);
        $this->assertEquals(5250, $cost);
    }

    public function test_can_upgrade_common_component(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        $this->assertTrue($this->service->canUpgrade($installed));
    }

    public function test_cannot_upgrade_at_max_level(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 6, // max for common
        ]);

        $this->assertFalse($this->service->canUpgrade($installed));
    }

    public function test_cannot_upgrade_exotic(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->exoticComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        $this->assertFalse($this->service->canUpgrade($installed));
    }

    public function test_cannot_upgrade_on_precursor_ship(): void
    {
        $precursorBlueprint = Ship::factory()->create([
            'class' => 'precursor',
            'weapon_slots' => 100,
            'attributes' => ['is_precursor' => true],
        ]);

        $precursorShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $precursorBlueprint->id,
            'weapon_slots' => 100,
            'is_active' => false,
        ]);

        $installed = PlayerShipComponent::create([
            'player_ship_id' => $precursorShip->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        $this->assertFalse($this->service->canUpgrade($installed));
    }

    public function test_upgrade_deducts_credits(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        $initialCredits = $this->player->credits;

        $result = $this->service->upgradeComponent($this->player, $installed);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['new_level']);
        $this->assertEquals(1500, $result['cost']);

        $this->player->refresh();
        $this->assertEquals($initialCredits - 1500, $this->player->credits);
    }

    public function test_upgrade_increments_level(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 3,
        ]);

        $result = $this->service->upgradeComponent($this->player, $installed);

        $this->assertTrue($result['success']);
        $this->assertEquals(4, $result['new_level']);

        $installed->refresh();
        $this->assertEquals(4, $installed->upgrade_level);
    }

    public function test_upgrade_fails_with_insufficient_credits(): void
    {
        $this->player->credits = 100;
        $this->player->save();

        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 0,
        ]);

        $result = $this->service->upgradeComponent($this->player, $installed);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient credits', $result['message']);
    }

    public function test_upgrade_info_shows_effect_scaling(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 100,
            'is_active' => true,
            'upgrade_level' => 2,
        ]);

        $info = $this->service->getUpgradeInfo($installed);

        // Level 2: damage = 25 * (1 + 2 * 0.15) = 25 * 1.3 = 32.5
        $this->assertEquals(32.5, $info['current_effects']['damage']);

        // Level 3: damage = 25 * (1 + 3 * 0.15) = 25 * 1.45 = 36.25
        $this->assertEquals(36.25, $info['next_effects']['damage']);
    }

    public function test_effective_effect_factors_in_upgrade_and_condition(): void
    {
        $installed = PlayerShipComponent::create([
            'player_ship_id' => $this->ship->id,
            'ship_component_id' => $this->commonComponent->id,
            'slot_type' => 'weapon',
            'slot_index' => 1,
            'condition' => 80, // 80% condition
            'is_active' => true,
            'upgrade_level' => 2,
        ]);

        // Base damage = 25
        // Upgrade multiplier = 1 + 2 * 0.15 = 1.3
        // Condition multiplier = 0.8
        // Mechanic bonus = 0 (default)
        // Effective = 25 * 1.3 * 0.8 * 1.0 = 26.0
        $effective = $installed->getEffectiveEffect('damage');
        $this->assertEquals(26.0, $effective);
    }
}
