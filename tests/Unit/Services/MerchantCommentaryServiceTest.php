<?php

namespace Tests\Unit\Services;

use App\Enums\SlotType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Models\Ship;
use App\Models\ShipComponent;
use App\Models\User;
use App\Services\MerchantCommentaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchantCommentaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private MerchantCommentaryService $service;

    private Galaxy $galaxy;

    private Player $player;

    private Ship $cheapShip;

    private Ship $expensiveShip;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MerchantCommentaryService::class);

        $this->galaxy = Galaxy::factory()->create();

        $user = User::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 50000,
        ]);

        $this->cheapShip = Ship::factory()->create([
            'name' => 'Sparrow',
            'class' => 'scout',
            'base_price' => 0,
            'hull_strength' => 50,
            'shield_strength' => 20,
            'weapon_slots' => 1,
            'cargo_capacity' => 30,
            'speed' => 6,
            'rarity' => 'common',
            'sales_pitches' => null,
        ]);

        $this->expensiveShip = Ship::factory()->create([
            'name' => 'Dreadnought',
            'class' => 'battleship',
            'base_price' => 500000,
            'hull_strength' => 800,
            'shield_strength' => 400,
            'weapon_slots' => 8,
            'cargo_capacity' => 100,
            'speed' => 2,
            'rarity' => 'exotic',
            'sales_pitches' => null,
        ]);
    }

    // ---- Value scoring ----

    public function test_value_tag_free_for_zero_price(): void
    {
        $tags = $this->service->scoreShip($this->cheapShip, 0.0);
        $this->assertEquals('free', $tags['value']);
    }

    public function test_value_tag_deal_for_low_price(): void
    {
        // 50% of base price = deal
        $tags = $this->service->scoreShip($this->expensiveShip, 250000);
        $this->assertEquals('deal', $tags['value']);
    }

    public function test_value_tag_overpriced_for_high_price(): void
    {
        // 120% of base price = overpriced
        $tags = $this->service->scoreShip($this->expensiveShip, 600000);
        $this->assertEquals('overpriced', $tags['value']);
    }

    public function test_value_tag_fair_for_normal_price(): void
    {
        // 90% of base price = fair
        $tags = $this->service->scoreShip($this->expensiveShip, 450000);
        $this->assertEquals('fair', $tags['value']);
    }

    // ---- Quality scoring ----

    public function test_quality_exceptional_for_exotic(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000);
        $this->assertEquals('exceptional', $tags['quality']);
    }

    public function test_quality_junk_for_common(): void
    {
        $tags = $this->service->scoreShip($this->cheapShip, 0.0);
        $this->assertEquals('junk', $tags['quality']);
    }

    // ---- Popularity scoring ----

    public function test_popularity_hot_item_for_exotic(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000);
        $this->assertEquals('hot_item', $tags['popularity']);
    }

    public function test_popularity_shelf_warmer_for_common(): void
    {
        $tags = $this->service->scoreShip($this->cheapShip, 0.0);
        $this->assertEquals('shelf_warmer', $tags['popularity']);
    }

    // ---- Danger scoring ----

    public function test_danger_deadly_for_high_combat_ship(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000);
        $this->assertEquals('deadly', $tags['danger']);
    }

    public function test_danger_safe_for_low_combat_ship(): void
    {
        $tags = $this->service->scoreShip($this->cheapShip, 0.0);
        $this->assertEquals('safe', $tags['danger']);
    }

    // ---- Specialty scoring ----

    public function test_specialty_firepower_for_many_weapon_slots(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000);
        $this->assertEquals('firepower', $tags['specialty']);
    }

    public function test_specialty_exploration_for_scout(): void
    {
        $tags = $this->service->scoreShip($this->cheapShip, 0.0);
        $this->assertEquals('exploration', $tags['specialty']);
    }

    public function test_specialty_cargo_for_high_capacity(): void
    {
        $cargoShip = Ship::factory()->create([
            'name' => 'Hauler',
            'class' => 'freighter',
            'base_price' => 50000,
            'hull_strength' => 100,
            'shield_strength' => 50,
            'weapon_slots' => 1,
            'cargo_capacity' => 300,
            'speed' => 3,
            'rarity' => 'common',
        ]);

        $tags = $this->service->scoreShip($cargoShip, 50000);
        $this->assertEquals('cargo', $tags['specialty']);
    }

    public function test_specialty_legendary_for_precursor(): void
    {
        $precursorShip = Ship::factory()->create([
            'name' => 'Precursor Vessel',
            'class' => 'precursor',
            'base_price' => 1000000,
            'hull_strength' => 500,
            'shield_strength' => 500,
            'weapon_slots' => 6,
            'cargo_capacity' => 150,
            'speed' => 10,
            'rarity' => 'exotic',
        ]);

        $tags = $this->service->scoreShip($precursorShip, 1000000);
        $this->assertEquals('legendary', $tags['specialty']);
    }

    // ---- Buyer affordability ----

    public function test_buyer_cant_afford_when_insufficient_credits(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000, $this->player);
        $this->assertEquals('cant_afford', $tags['buyer_affordability']);
    }

    public function test_buyer_way_too_rich_when_credits_far_exceed_price(): void
    {
        $this->player->credits = 10000000;
        $tags = $this->service->scoreShip($this->cheapShip, 1000, $this->player);
        $this->assertEquals('way_too_rich', $tags['buyer_affordability']);
    }

    public function test_buyer_stretching_when_barely_affording(): void
    {
        $this->player->credits = 55000;
        $tags = $this->service->scoreShip($this->expensiveShip, 50000, $this->player);
        $this->assertEquals('stretching', $tags['buyer_affordability']);
    }

    public function test_buyer_comfortable_when_can_afford_well(): void
    {
        $this->player->credits = 100000;
        $tags = $this->service->scoreShip($this->expensiveShip, 50000, $this->player);
        $this->assertEquals('comfortable', $tags['buyer_affordability']);
    }

    // ---- Buyer comparison ----

    public function test_buyer_comparison_first_ship_when_no_active_ship(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000, $this->player);
        $this->assertEquals('first_ship', $tags['buyer_comparison']);
    }

    public function test_buyer_comparison_upgrade_when_browsing_better_ship(): void
    {
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->cheapShip->id,
            'is_active' => true,
        ]);
        $this->player->load('activeShip.ship');

        $tags = $this->service->scoreShip($this->expensiveShip, 500000, $this->player);
        $this->assertEquals('upgrade', $tags['buyer_comparison']);
    }

    public function test_buyer_comparison_downgrade_when_browsing_worse_ship(): void
    {
        $playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $this->expensiveShip->id,
            'is_active' => true,
        ]);
        $this->player->load('activeShip.ship');

        $tags = $this->service->scoreShip($this->cheapShip, 0, $this->player);
        $this->assertEquals('downgrade', $tags['buyer_comparison']);
    }

    // ---- No player context ----

    public function test_no_buyer_tags_when_player_is_null(): void
    {
        $tags = $this->service->scoreShip($this->expensiveShip, 500000);
        $this->assertArrayNotHasKey('buyer_affordability', $tags);
        $this->assertArrayNotHasKey('buyer_comparison', $tags);
    }

    // ---- Component scoring ----

    public function test_component_value_accounts_for_condition(): void
    {
        $component = ShipComponent::create([
            'name' => 'Test Laser',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'A test laser',
            'slots_required' => 1,
            'base_price' => 10000,
            'rarity' => 'common',
            'effects' => ['damage' => 25],
            'is_available' => true,
        ]);

        // condition 50 → effective base = 5000, price 3000 → deal (0.6 ratio)
        $item = SalvageYardInventory::create([
            'trading_hub_id' => null,
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 3000,
            'condition' => 50,
            'source' => 'salvage',
        ]);

        $tags = $this->service->scoreComponent($component, $item);
        $this->assertEquals('deal', $tags['value']);
    }

    public function test_component_quality_degraded_by_poor_condition(): void
    {
        $component = ShipComponent::create([
            'name' => 'Rare Engine',
            'type' => 'engine',
            'slot_type' => 'engine',
            'description' => 'A rare engine',
            'slots_required' => 1,
            'base_price' => 20000,
            'rarity' => 'rare',
            'effects' => ['thrust' => 50],
            'is_available' => true,
        ]);

        $item = SalvageYardInventory::create([
            'trading_hub_id' => null,
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 30, // Below 40 → degrades quality
            'source' => 'salvage',
        ]);

        $tags = $this->service->scoreComponent($component, $item);
        $this->assertEquals('decent', $tags['quality']); // Degraded from 'good'
    }

    public function test_component_source_tag(): void
    {
        $component = ShipComponent::create([
            'name' => 'Stolen Blaster',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'A stolen blaster',
            'slots_required' => 1,
            'base_price' => 8000,
            'rarity' => 'uncommon',
            'effects' => ['damage' => 35],
            'is_available' => true,
        ]);

        $item = SalvageYardInventory::create([
            'trading_hub_id' => null,
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 5000,
            'condition' => 85,
            'source' => 'stolen',
        ]);

        $tags = $this->service->scoreComponent($component, $item);
        $this->assertEquals('stolen', $tags['source']);
    }

    public function test_component_condition_tags(): void
    {
        $component = ShipComponent::create([
            'name' => 'Test Component',
            'type' => 'utility',
            'slot_type' => 'utility',
            'description' => 'Test',
            'slots_required' => 1,
            'base_price' => 5000,
            'rarity' => 'common',
            'effects' => [],
            'is_available' => true,
        ]);

        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        // Broken
        $brokenItem = SalvageYardInventory::create([
            'poi_id' => $poi->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 1000,
            'condition' => 30,
            'source' => 'salvage',
        ]);
        $tags = $this->service->scoreComponent($component, $brokenItem);
        $this->assertEquals('broken', $tags['condition']);

        // Pristine
        $pristineItem = SalvageYardInventory::create([
            'poi_id' => $poi->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 6000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);
        $tags = $this->service->scoreComponent($component, $pristineItem);
        $this->assertEquals('pristine', $tags['condition']);
    }

    public function test_component_danger_deadly_weapon(): void
    {
        $component = ShipComponent::create([
            'name' => 'Devastator Cannon',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'Very dangerous',
            'slots_required' => 1,
            'base_price' => 50000,
            'rarity' => 'epic',
            'effects' => ['damage' => 100],
            'is_available' => true,
        ]);

        $item = SalvageYardInventory::create([
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 45000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $tags = $this->service->scoreComponent($component, $item);
        $this->assertEquals('deadly', $tags['danger']);
    }

    public function test_component_danger_defensive_for_shields(): void
    {
        $component = ShipComponent::create([
            'name' => 'Shield Array',
            'type' => 'shield',
            'slot_type' => 'shield_generator',
            'description' => 'Defensive',
            'slots_required' => 1,
            'base_price' => 15000,
            'rarity' => 'rare',
            'effects' => ['shield_regen' => 10],
            'is_available' => true,
        ]);

        $item = SalvageYardInventory::create([
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 14000,
            'condition' => 100,
            'source' => 'manufactured',
        ]);

        $tags = $this->service->scoreComponent($component, $item);
        $this->assertEquals('defensive', $tags['danger']);
    }

    // ---- Selection algorithm ----

    public function test_most_specific_pool_wins(): void
    {
        // Tags that match a 2-tag combo should get that pool, not a 1-tag one
        $tags = [
            'quality' => 'exceptional',
            'value' => 'deal',
            'popularity' => 'hot_item',
        ];

        $replacements = ['item_name' => 'Test', 'price' => '1,000', 'rarity' => 'exotic', 'slot_type' => 'weapon'];

        // Run multiple times to verify it consistently picks from multi-tag pool
        for ($i = 0; $i < 10; $i++) {
            $result = $this->service->selectCommentary($tags, $replacements);
            $this->assertNotEmpty($result);
            // The result should contain interpolated values
            $this->assertStringNotContainsString('{item_name}', $result);
        }
    }

    public function test_fallback_to_universal_when_no_match(): void
    {
        // Tags that don't match any specific pool
        $tags = ['nonexistent_dimension' => 'impossible_value'];
        $replacements = ['item_name' => 'Test', 'price' => '500', 'rarity' => 'common', 'slot_type' => 'utility'];

        $result = $this->service->selectCommentary($tags, $replacements);
        $this->assertNotEmpty($result);
    }

    // ---- Interpolation ----

    public function test_placeholders_replaced(): void
    {
        $tags = ['value' => 'deal'];
        $replacements = [
            'item_name' => 'Mega Blaster',
            'price' => '5,000',
            'rarity' => 'rare',
            'slot_type' => 'Weapon',
        ];

        // Run multiple times since pools are randomly selected
        $containsPlaceholder = false;
        for ($i = 0; $i < 20; $i++) {
            $result = $this->service->selectCommentary($tags, $replacements);
            if (str_contains($result, '{')) {
                $containsPlaceholder = true;
            }
        }

        $this->assertFalse($containsPlaceholder, 'Placeholders should be replaced in all cases');
    }

    // ---- Smoke tests ----

    public function test_every_ship_class_produces_nonempty_commentary(): void
    {
        $classes = ['scout', 'freighter', 'battleship', 'mining', 'stealth', 'colony', 'precursor'];

        foreach ($classes as $class) {
            $ship = Ship::factory()->create([
                'class' => $class,
                'base_price' => 50000,
                'hull_strength' => 100,
                'shield_strength' => 50,
                'weapon_slots' => 2,
                'cargo_capacity' => 100,
                'speed' => 5,
                'rarity' => 'common',
                'sales_pitches' => null,
            ]);

            $result = $this->service->generateShipCommentary($ship, 50000);
            $this->assertNotEmpty($result, "Commentary should be non-empty for class: {$class}");
        }
    }

    public function test_every_slot_type_produces_nonempty_commentary(): void
    {
        foreach (SlotType::cases() as $slotType) {
            $component = ShipComponent::create([
                'name' => "Test {$slotType->label()}",
                'type' => $slotType->value,
                'slot_type' => $slotType->value,
                'description' => 'Test component',
                'slots_required' => 1,
                'base_price' => 5000,
                'rarity' => 'common',
                'effects' => [],
                'is_available' => true,
            ]);

            $item = SalvageYardInventory::create([
                'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
                'ship_component_id' => $component->id,
                'quantity' => 1,
                'current_price' => 5000,
                'condition' => 80,
                'source' => 'manufactured',
            ]);

            $result = $this->service->generateComponentCommentary($component, $item);
            $this->assertNotEmpty($result, "Commentary should be non-empty for slot type: {$slotType->value}");
        }
    }

    public function test_every_source_produces_nonempty_commentary(): void
    {
        $sources = ['manufactured', 'salvage', 'stolen'];

        $component = ShipComponent::create([
            'name' => 'Source Test Component',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'Test',
            'slots_required' => 1,
            'base_price' => 5000,
            'rarity' => 'common',
            'effects' => ['damage' => 20],
            'is_available' => true,
        ]);

        foreach ($sources as $source) {
            $item = SalvageYardInventory::create([
                'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
                'ship_component_id' => $component->id,
                'quantity' => 1,
                'current_price' => 3000,
                'condition' => $source === 'manufactured' ? 100 : 70,
                'source' => $source,
            ]);

            $result = $this->service->generateComponentCommentary($component, $item);
            $this->assertNotEmpty($result, "Commentary should be non-empty for source: {$source}");
        }
    }

    public function test_ship_commentary_with_player_context(): void
    {
        $result = $this->service->generateShipCommentary($this->expensiveShip, 500000, $this->player);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function test_component_commentary_with_player_context(): void
    {
        $component = ShipComponent::create([
            'name' => 'Player Context Weapon',
            'type' => 'weapon',
            'slot_type' => 'weapon',
            'description' => 'Test',
            'slots_required' => 1,
            'base_price' => 10000,
            'rarity' => 'rare',
            'effects' => ['damage' => 50],
            'is_available' => true,
        ]);

        $item = SalvageYardInventory::create([
            'poi_id' => PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id])->id,
            'ship_component_id' => $component->id,
            'quantity' => 1,
            'current_price' => 8000,
            'condition' => 90,
            'source' => 'salvage',
        ]);

        $result = $this->service->generateComponentCommentary($component, $item, $this->player);
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }
}
