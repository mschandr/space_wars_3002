<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use App\Services\ShipPurchaseService;
use App\Services\ShipVariationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipServicesTest extends TestCase
{
    use RefreshDatabase;

    private ShipVariationService $variationService;

    private ShipPurchaseService $purchaseService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->variationService = app(ShipVariationService::class);
        $this->purchaseService = app(ShipPurchaseService::class);
    }

    // =========================================================================
    // Ship Variation Service Tests
    // =========================================================================

    public function test_generates_variation_for_standard_quality(): void
    {
        $ship = Ship::factory()->create();

        $variation = $this->variationService->generateVariation($ship, 'standard');

        $this->assertArrayHasKey('traits', $variation);
        $this->assertArrayHasKey('modifiers', $variation);
        $this->assertArrayHasKey('fuel_regen_modifier', $variation['modifiers']);
        $this->assertArrayHasKey('fuel_consumption_modifier', $variation['modifiers']);
        $this->assertArrayHasKey('speed_modifier', $variation['modifiers']);
    }

    public function test_premium_quality_has_more_traits(): void
    {
        $ship = Ship::factory()->create();

        // Run multiple times to account for randomness
        $standardTraitCounts = [];
        $premiumTraitCounts = [];

        for ($i = 0; $i < 20; $i++) {
            $standardTraitCounts[] = count($this->variationService->generateVariation($ship, 'standard')['traits']);
            $premiumTraitCounts[] = count($this->variationService->generateVariation($ship, 'premium')['traits']);
        }

        // Premium should have more traits on average
        $this->assertGreaterThanOrEqual(
            array_sum($standardTraitCounts) / count($standardTraitCounts),
            array_sum($premiumTraitCounts) / count($premiumTraitCounts)
        );
    }

    public function test_modifiers_are_within_bounds(): void
    {
        $ship = Ship::factory()->create();

        for ($i = 0; $i < 50; $i++) {
            $variation = $this->variationService->generateVariation($ship, 'legendary');
            $modifiers = $variation['modifiers'];

            $this->assertGreaterThanOrEqual(0.7, $modifiers['fuel_regen_modifier']);
            $this->assertLessThanOrEqual(1.5, $modifiers['fuel_regen_modifier']);

            $this->assertGreaterThanOrEqual(0.7, $modifiers['fuel_consumption_modifier']);
            $this->assertLessThanOrEqual(1.5, $modifiers['fuel_consumption_modifier']);

            $this->assertGreaterThanOrEqual(0.8, $modifiers['speed_modifier']);
            $this->assertLessThanOrEqual(1.3, $modifiers['speed_modifier']);
        }
    }

    public function test_applies_variation_to_player_ship(): void
    {
        $ship = Ship::factory()->create([
            'hull_strength' => 100,
            'cargo_capacity' => 50,
        ]);

        $playerShip = new PlayerShip([
            'max_hull' => 100,
            'hull' => 100,
            'cargo_hold' => 50,
            'sensors' => 1,
            'fuel_regen_modifier' => 1.0,
            'fuel_consumption_modifier' => 1.0,
            'speed_modifier' => 1.0,
        ]);

        $variation = [
            'traits' => [
                'reinforced_plating' => ['name' => 'Reinforced Plating', 'description' => 'Test'],
            ],
            'modifiers' => [
                'fuel_regen_modifier' => 1.15,
                'fuel_consumption_modifier' => 1.10,
                'speed_modifier' => 0.95,
                'hull_bonus' => 10,
                'cargo_bonus' => 5,
                'sensor_bonus' => 0,
            ],
        ];

        $this->variationService->applyVariation($playerShip, $variation);

        $this->assertEquals(1.15, $playerShip->fuel_regen_modifier);
        $this->assertEquals(1.10, $playerShip->fuel_consumption_modifier);
        $this->assertEquals(0.95, $playerShip->speed_modifier);
        $this->assertEquals(110, $playerShip->max_hull); // 100 + 10 bonus
        $this->assertEquals(55, $playerShip->cargo_hold); // 50 + 5 bonus
        $this->assertNotEmpty($playerShip->variation_traits);
    }

    // =========================================================================
    // Ship Purchase Service Tests
    // =========================================================================

    public function test_can_purchase_ship_with_sufficient_credits(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 100000,
            'level' => 10,
        ]);

        $ship = Ship::factory()->create([
            'name' => 'Test Ship',
            'base_price' => 50000,
            'requirements' => null,
        ]);

        $result = $this->purchaseService->purchaseShip($player, $ship, 'My Ship');

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(PlayerShip::class, $result['ship']);
        $this->assertEquals('My Ship', $result['ship']->name);
        $this->assertEquals(50000, $player->fresh()->credits); // 100000 - 50000
    }

    public function test_cannot_purchase_ship_without_sufficient_credits(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 1000,
            'level' => 10,
        ]);

        $ship = Ship::factory()->create([
            'base_price' => 50000,
            'requirements' => null,
        ]);

        $result = $this->purchaseService->purchaseShip($player, $ship);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient credits', $result['error']);
    }

    public function test_cannot_purchase_ship_without_meeting_requirements(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 100000,
            'level' => 5,
        ]);

        $ship = Ship::factory()->create([
            'base_price' => 50000,
            'requirements' => ['level' => 10],
        ]);

        $result = $this->purchaseService->purchaseShip($player, $ship);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('requirements', $result['error']);
    }

    public function test_purchased_ship_has_variation_traits(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 100000,
            'level' => 20,
        ]);

        $ship = Ship::factory()->create([
            'base_price' => 50000,
            'requirements' => null,
        ]);

        // Premium quality should have traits
        $result = $this->purchaseService->purchaseShip($player, $ship, 'Test Ship', 'premium');

        $this->assertTrue($result['success']);
        // Modifiers should be set (even if 1.0)
        $this->assertNotNull($result['ship']->fuel_regen_modifier);
        $this->assertNotNull($result['ship']->fuel_consumption_modifier);
        $this->assertNotNull($result['ship']->speed_modifier);
    }

    public function test_create_starter_ship(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        Ship::factory()->create([
            'class' => 'starter',
            'attributes' => ['is_starter' => true, 'max_fuel' => 100],
        ]);

        $ship = $this->purchaseService->createStarterShip($player, 'My First Ship');

        $this->assertInstanceOf(PlayerShip::class, $ship);
        $this->assertEquals('My First Ship', $ship->name);
        $this->assertTrue($ship->is_active);
    }

    // =========================================================================
    // Player Ship Functionality Tests
    // =========================================================================

    public function test_hidden_cargo_for_smuggler_ship(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'hidden_hold_capacity' => 80,
            'hidden_cargo' => 0,
        ]);

        $this->assertTrue($playerShip->hasHiddenHold());
        $this->assertTrue($playerShip->canAddHiddenCargo(50));
        $this->assertTrue($playerShip->addHiddenCargo(50));
        $this->assertEquals(50, $playerShip->hidden_cargo);
        $this->assertFalse($playerShip->canAddHiddenCargo(50)); // Would exceed 80
        $this->assertTrue($playerShip->removeHiddenCargo(30));
        $this->assertEquals(20, $playerShip->hidden_cargo);
    }

    public function test_colonist_capacity_for_colony_ship(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $ship = Ship::factory()->create();
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'colonist_capacity' => 10000,
            'current_colonists' => 10000, // Pre-loaded
        ]);

        $this->assertTrue($playerShip->isColonyShip());
        $this->assertFalse($playerShip->canBoardColonists(100)); // Already full
        $this->assertTrue($playerShip->disembarkColonists(5000));
        $this->assertEquals(5000, $playerShip->current_colonists);
        $this->assertTrue($playerShip->canBoardColonists(5000));

        $remaining = $playerShip->disembarkAllColonists();
        $this->assertEquals(5000, $remaining);
        $this->assertEquals(0, $playerShip->current_colonists);
    }

    public function test_fuel_regeneration_with_modifier(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $ship = Ship::factory()->create();

        // Ship with faster regen (1.5x = 50% faster)
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 50,
            'max_fuel' => 100,
            'fuel_regen_modifier' => 1.5,
            'fuel_last_updated_at' => now()->subMinutes(10),
        ]);

        $playerShip->regenerateFuel();

        // With 1.5x modifier, should regen faster
        // Base rate is 30 sec per unit, with 1.5x modifier = 20 sec per unit
        // 10 minutes = 600 seconds / 20 = 30 units
        $this->assertGreaterThan(50, $playerShip->current_fuel);
    }

    public function test_fuel_consumption_with_modifier(): void
    {
        $user = User::factory()->create();
        $galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $poi->id,
        ]);

        $ship = Ship::factory()->create();

        // Ship with higher consumption (1.4x = 40% more)
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $player->id,
            'ship_id' => $ship->id,
            'current_fuel' => 100,
            'max_fuel' => 100,
            'fuel_consumption_modifier' => 1.4,
            'warp_drive' => 1,
            'fuel_last_updated_at' => now(),
        ]);

        // Test effective consumption calculation
        $effective = $playerShip->getEffectiveFuelConsumption(10);

        // Base 10 * 1.4 modifier = 14, /1 warp drive = 14
        $this->assertEquals(14, $effective);
    }
}
