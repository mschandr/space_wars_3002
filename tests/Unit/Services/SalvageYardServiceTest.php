<?php

namespace Tests\Unit\Services;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Models\Ship;
use App\Models\ShipComponent;
use App\Models\TradingHub;
use App\Models\User;
use App\Services\SalvageYardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalvageYardServiceTest extends TestCase
{
    use RefreshDatabase;

    private SalvageYardService $service;

    private Galaxy $galaxy;

    private Player $player;

    private TradingHub $hub;

    private ShipComponent $weapon;

    private ShipComponent $shield;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SalvageYardService::class);

        // Create galaxy
        $this->galaxy = Galaxy::factory()->create();

        // Create user and player
        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 100000,
        ]);

        // Create trading hub at player's location
        $this->hub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        // Create ship blueprint and player ship
        $shipBlueprint = Ship::factory()->create([
            'weapon_slots' => 3,
            'utility_slots' => 3,
        ]);

        $playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'weapon_slots' => 3,
            'utility_slots' => 3,
            'is_active' => true,
        ]);

        // Create components
        $this->weapon = ShipComponent::create([
            'name' => 'Test Laser',
            'type' => 'weapon',
            'slot_type' => 'weapon_slot',
            'description' => 'A test laser weapon',
            'slots_required' => 1,
            'base_price' => 5000,
            'rarity' => 'common',
            'effects' => ['damage' => 25],
            'is_available' => true,
        ]);

        $this->shield = ShipComponent::create([
            'name' => 'Test Shield Regenerator',
            'type' => 'shield',
            'slot_type' => 'utility_slot',
            'description' => 'A test shield regenerator',
            'slots_required' => 1,
            'base_price' => 8000,
            'rarity' => 'common',
            'effects' => ['shield_regen' => 5],
            'is_available' => true,
        ]);
    }

    public function test_get_inventory_returns_available_items(): void
    {
        // Add items to salvage yard
        SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 3,
            'current_price' => 5500,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->shield->id,
            'quantity' => 2,
            'current_price' => 6000,
            'condition' => 85,
            'source' => 'salvage',
        ]);

        $inventory = $this->service->getInventory($this->hub);

        $this->assertCount(2, $inventory);
        $this->assertEquals('Test Laser', $inventory[0]['component']['name']);
        $this->assertEquals(3, $inventory[0]['quantity']);
    }

    public function test_get_inventory_by_type_separates_weapons_and_utilities(): void
    {
        SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 2,
            'current_price' => 5500,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->shield->id,
            'quantity' => 1,
            'current_price' => 6000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $grouped = $this->service->getInventoryByType($this->hub);

        $this->assertCount(1, $grouped['weapons']);
        $this->assertCount(1, $grouped['utilities']);
        $this->assertEquals('weapon_slot', $grouped['weapons'][0]['component']['slot_type']);
        $this->assertEquals('utility_slot', $grouped['utilities'][0]['component']['slot_type']);
    }

    public function test_purchase_component_requires_player_at_hub(): void
    {
        // Create item at hub
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        // Create a different hub
        $differentPoi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $differentHub = TradingHub::factory()->create(['poi_id' => $differentPoi->id]);

        // Move player to different hub
        $this->player->current_poi_id = $differentPoi->id;
        $this->player->save();
        $this->player->refresh();

        $result = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('must be at this trading hub', $result['error']);
    }

    public function test_purchase_component_requires_sufficient_credits(): void
    {
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 500000, // More than player has
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $result = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Insufficient credits', $result['error']);
    }

    public function test_purchase_component_success(): void
    {
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 2,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $initialCredits = $this->player->credits;

        $result = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['component']);

        // Check credits deducted
        $this->player->refresh();
        $this->assertEquals($initialCredits - 5000, $this->player->credits);

        // Check inventory decremented
        $item->refresh();
        $this->assertEquals(1, $item->quantity);

        // Check component installed
        $this->player->activeShip->refresh();
        $this->assertEquals(1, $this->player->activeShip->components()->count());
    }

    public function test_purchase_component_fails_for_occupied_slot(): void
    {
        // First purchase
        $item1 = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 2,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item1,
            $this->player->activeShip,
            1
        );

        // Try to purchase another to same slot
        $item1->refresh();

        $result = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item1,
            $this->player->activeShip,
            1 // Same slot
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already occupied', $result['error']);
    }

    public function test_uninstall_component_success(): void
    {
        // Install a component first
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $purchaseResult = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $installedComponent = $purchaseResult['component'];

        // Now uninstall it
        $result = $this->service->uninstallComponent($this->player, $installedComponent, false);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('uninstalled', $result['message']);

        // Verify component is gone
        $this->assertEquals(0, $this->player->activeShip->components()->count());
    }

    public function test_uninstall_and_sell_component(): void
    {
        // Install a component
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $purchaseResult = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $this->player->refresh();
        $creditsAfterPurchase = $this->player->credits;

        $installedComponent = $purchaseResult['component'];

        // Sell the component
        $result = $this->service->uninstallComponent($this->player, $installedComponent, true);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('sold', $result['message']);
        $this->assertGreaterThan(0, $result['credits_received']);

        // Verify credits increased
        $this->player->refresh();
        $this->assertGreaterThan($creditsAfterPurchase, $this->player->credits);
    }

    public function test_get_installed_components(): void
    {
        // Install a weapon and a shield
        $weaponItem = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $shieldItem = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->shield->id,
            'quantity' => 1,
            'current_price' => 8000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $weaponItem,
            $this->player->activeShip,
            1
        );

        $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $shieldItem,
            $this->player->activeShip,
            1
        );

        $components = $this->service->getInstalledComponents($this->player->activeShip);

        $this->assertArrayHasKey('weapon_slots', $components);
        $this->assertArrayHasKey('utility_slots', $components);
        $this->assertArrayHasKey(1, $components['weapon_slots']);
        $this->assertArrayHasKey(1, $components['utility_slots']);
        $this->assertEquals('Test Laser', $components['weapon_slots'][1]['component']['name']);
        $this->assertEquals('Test Shield Regenerator', $components['utility_slots'][1]['component']['name']);
    }

    public function test_populate_salvage_yard(): void
    {
        // Ensure components exist
        $this->assertGreaterThan(0, ShipComponent::count());

        $created = $this->service->populateSalvageYard($this->hub, 5);

        $this->assertGreaterThan(0, $created);
        $this->assertGreaterThan(0, SalvageYardInventory::where('trading_hub_id', $this->hub->id)->count());
    }

    public function test_condition_affects_sell_price(): void
    {
        // Create a damaged component
        $item = SalvageYardInventory::create([
            'trading_hub_id' => $this->hub->id,
            'ship_component_id' => $this->weapon->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 50, // 50% condition
            'source' => 'salvage',
        ]);

        $purchaseResult = $this->service->purchaseComponent(
            $this->player,
            $this->hub,
            $item,
            $this->player->activeShip,
            1
        );

        $this->player->refresh();
        $creditsAfterPurchase = $this->player->credits;

        $installedComponent = $purchaseResult['component'];

        // Sell the component
        $result = $this->service->uninstallComponent($this->player, $installedComponent, true);

        // Base price is 5000, sell value is 50% = 2500, then * 50% condition = 1250
        $this->assertTrue($result['success']);
        $expectedSellValue = (int) (5000 * 0.5 * 0.5); // base * sell rate * condition
        $this->assertEquals($expectedSellValue, $result['credits_received']);
    }
}
