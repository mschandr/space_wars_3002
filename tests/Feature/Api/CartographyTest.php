<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\StellarCartographer;
use App\Models\TradingHub;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartographyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private Galaxy $galaxy;

    private PointOfInterest $poi1;

    private PointOfInterest $poi2;

    private PointOfInterest $poi3;

    private StellarCartographer $cartographer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->create();

        // Create three connected systems
        $this->poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $this->poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $this->poi3 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        // Connect with warp gates
        WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->poi1->id,
            'destination_poi_id' => $this->poi2->id,
            'status' => 'active',
            'is_hidden' => false,
        ]);

        WarpGate::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $this->poi2->id,
            'destination_poi_id' => $this->poi3->id,
            'status' => 'active',
            'is_hidden' => false,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->poi1->id,
            'credits' => 50000,
        ]);

        // Create trading hub with cartographer
        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $this->poi1->id,
        ]);

        $this->cartographer = StellarCartographer::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'poi_id' => $this->poi1->id,
            'name' => 'Star Charts Inc.',
            'markup_multiplier' => 1.0,
        ]);
    }

    public function test_it_checks_for_cartographer_at_trading_hub()
    {
        $tradingHub = TradingHub::where('poi_id', $this->poi1->id)->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$tradingHub->uuid}/cartographer");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_cartographer' => true,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'has_cartographer',
                    'cartographer' => [
                        'shop_name',
                        'markup_multiplier',
                        'location',
                    ],
                ],
            ]);
    }

    public function test_it_returns_false_when_no_cartographer()
    {
        // Create hub without cartographer
        $poi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $tradingHub = TradingHub::factory()->create(['poi_id' => $poi->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/trading-hubs/{$tradingHub->uuid}/cartographer");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_cartographer' => false,
                ],
            ]);
    }

    public function test_it_previews_chart_coverage()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/preview?player_uuid={$this->player->uuid}&center_poi_uuid={$this->poi1->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'center',
                    'coverage',
                    'total_systems',
                    'known_systems',
                    'unknown_systems',
                    'price',
                    'can_afford',
                ],
            ]);

        // Should cover at least 2 systems (poi1 and poi2 within 2 hops)
        $this->assertGreaterThanOrEqual(2, $response->json('data.total_systems'));
    }

    public function test_it_calculates_pricing_dynamically()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/pricing?player_uuid={$this->player->uuid}&center_poi_uuid={$this->poi1->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'price',
                    'unknown_systems_count',
                    'base_price',
                    'multiplier',
                    'markup',
                    'formula',
                    'can_afford',
                    'player_credits',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.price'));
    }

    public function test_it_purchases_star_chart_successfully()
    {
        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'systems_revealed',
                    'total_systems',
                    'price_paid',
                    'credits_remaining',
                ],
            ]);

        // Verify credits deducted
        $this->player->refresh();
        $this->assertLessThan($oldCredits, $this->player->credits);

        // Verify charts were created
        $chartsCount = DB::table('player_star_charts')
            ->where('player_id', $this->player->id)
            ->count();

        $this->assertGreaterThan(0, $chartsCount);
    }

    public function test_it_fails_purchase_with_insufficient_credits()
    {
        $this->player->update(['credits' => 10]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits',
                ],
            ]);
    }

    public function test_it_returns_zero_price_for_already_owned_charts()
    {
        // Purchase chart once
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        // Try to purchase again
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'You already have charts for all systems in this region',
            ]);
    }

    public function test_it_gets_player_revealed_charts()
    {
        // Purchase some charts first
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/star-charts");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'revealed_systems',
                    'total_charts',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('data.total_charts'));
    }

    public function test_it_gets_system_info_when_player_has_chart()
    {
        // Purchase chart
        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/players/{$this->player->uuid}/star-charts/purchase", [
                'cartographer_poi_uuid' => $this->poi1->uuid,
            ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/system/{$this->poi1->uuid}?player_uuid={$this->player->uuid}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'system' => [
                        'name',
                        'coordinates',
                        'type',
                        'is_inhabited',
                        'pirate_warning',
                        'connections',
                    ],
                    'poi_uuid',
                ],
            ]);
    }

    public function test_it_fails_system_info_when_no_chart()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/system/{$this->poi2->uuid}?player_uuid={$this->player->uuid}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'You do not have a star chart for this system',
                ],
            ]);
    }

    public function test_it_applies_markup_in_pricing()
    {
        // Update cartographer markup
        $this->cartographer->update(['markup_multiplier' => 2.0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/pricing?player_uuid={$this->player->uuid}&center_poi_uuid={$this->poi1->uuid}&cartographer_poi_uuid={$this->poi1->uuid}");

        $response->assertOk();

        $this->assertEquals(2.0, $response->json('data.markup'));
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/players/{$this->player->uuid}/star-charts");

        $response->assertUnauthorized();
    }

    public function test_it_validates_required_fields_for_preview()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/star-charts/preview');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['player_uuid', 'center_poi_uuid']);
    }

    public function test_it_shows_can_afford_flag_correctly()
    {
        // Player with lots of credits
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/preview?player_uuid={$this->player->uuid}&center_poi_uuid={$this->poi1->uuid}");

        $response->assertOk();
        $this->assertTrue($response->json('data.can_afford'));

        // Player with no credits
        $this->player->update(['credits' => 0]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/star-charts/preview?player_uuid={$this->player->uuid}&center_poi_uuid={$this->poi1->uuid}");

        $response->assertOk();
        $this->assertFalse($response->json('data.can_afford'));
    }
}
