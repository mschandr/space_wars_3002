<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Plan;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\TradingHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlansShopTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private TradingHub $tradingHub;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi->id,
            'credits' => 50000,
            'level' => 5,
        ]);

        // Create trading hub with plans shop
        $this->tradingHub = TradingHub::factory()->create([
            'poi_id' => $poi->id,
            'has_plans' => true,
        ]);

        // Create upgrade plan
        $this->plan = Plan::factory()->create([
            'name' => 'Advanced Weapons',
            'component' => 'weapons',
            'description' => 'Upgrade weapons beyond normal limits',
            'additional_levels' => 5,
            'price' => 5000,
            'rarity' => 'rare',
            'requirements' => ['min_level' => 3],
        ]);

        // Attach plan to trading hub
        $this->tradingHub->plans()->attach($this->plan->id);
    }

    public function test_it_gets_plans_shop_with_available_plans()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/plans-shop");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_plans_shop' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'has_plans_shop',
                    'trading_hub_name',
                    'available_plans' => [
                        '*' => [
                            'plan',
                            'owned_count',
                            'current_bonus',
                            'projected_bonus',
                        ],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.available_plans'));
    }

    public function test_it_returns_false_when_hub_has_no_plans_shop()
    {
        $this->tradingHub->update(['has_plans' => false]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/plans-shop");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_plans_shop' => false,
                    'available_plans' => [],
                ],
            ]);
    }

    public function test_it_shows_owned_count_and_bonus_calculations()
    {
        // Give player 2 copies of the plan
        $this->player->plans()->attach($this->plan->id);
        $this->player->plans()->attach($this->plan->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/plans-shop");

        $response->assertOk();

        $planData = $response->json('data.available_plans.0');
        $this->assertEquals(2, $planData['owned_count']);
        $this->assertEquals(10, $planData['current_bonus']); // 2 × 5
        $this->assertEquals(15, $planData['projected_bonus']); // 3 × 5
    }

    public function test_it_gets_plans_catalog()
    {
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/plans/catalog');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plans',
                    'total_count',
                ],
            ]);

        $this->assertGreaterThanOrEqual(4, $response->json('data.total_count'));
    }

    public function test_it_filters_plans_catalog_by_component()
    {
        Plan::factory()->create(['component' => 'weapons']);
        Plan::factory()->create(['component' => 'sensors']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/plans/catalog?component=weapons');

        $response->assertOk();

        // All returned plans should be for weapons
        foreach ($response->json('data.plans') as $plan) {
            $this->assertEquals('weapons', $plan['component']);
        }
    }

    public function test_it_purchases_plan_successfully()
    {
        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $this->plan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plan',
                    'cost_paid',
                    'remaining_credits',
                    'owned_count',
                    'total_bonus',
                ],
            ]);

        // Verify credits deducted
        $this->player->refresh();
        $this->assertEquals($oldCredits - 5000, $this->player->credits);

        // Verify player owns plan
        $this->assertTrue($this->player->plans->contains($this->plan->id));
    }

    public function test_it_allows_purchasing_same_plan_multiple_times()
    {
        // Purchase once
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $this->plan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        // Purchase again
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $this->plan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertOk();

        // Verify player has 2 copies
        $this->assertEquals(2, $response->json('data.owned_count'));
        $this->assertEquals(10, $response->json('data.total_bonus')); // 2 × 5
    }

    public function test_it_fails_purchase_with_insufficient_credits()
    {
        $this->player->update(['credits' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $this->plan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits',
                ],
            ]);
    }

    public function test_it_fails_purchase_when_level_requirement_not_met()
    {
        $this->player->update(['level' => 1]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $this->plan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'You need to be level 3 to purchase this plan',
            ]);
    }

    public function test_it_fails_purchase_when_plan_not_at_hub()
    {
        $unavailablePlan = Plan::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", [
                'plan_id' => $unavailablePlan->id,
                'trading_hub_uuid' => $this->tradingHub->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'This plan is not available at this trading hub',
                ],
            ]);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/trading-hubs/{$this->tradingHub->uuid}/plans-shop");

        $response->assertUnauthorized();
    }

    public function test_it_validates_required_fields_for_purchase()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/plans/purchase", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id', 'trading_hub_uuid']);
    }

    public function test_it_returns_plan_resource_structure()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/plans/catalog');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'plans' => [
                        '*' => [
                            'id',
                            'name',
                            'full_name',
                            'component',
                            'component_display_name',
                            'description',
                            'additional_levels',
                            'price',
                            'rarity',
                            'requirements',
                        ],
                    ],
                ],
            ]);
    }
}
