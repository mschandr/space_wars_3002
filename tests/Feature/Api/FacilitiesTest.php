<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\StellarCartographer;
use App\Models\TradingHub;
use App\Models\TradingHubShip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacilitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Galaxy $galaxy;

    private Player $player;

    private PointOfInterest $star;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->galaxy = Galaxy::factory()->active()->create();

        $this->star = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Alpha Centauri',
            'x' => 100,
            'y' => 100,
            'is_inhabited' => true,
            'type' => PointOfInterestType::STAR,
        ]);

        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->star->id,
        ]);

        $ship = Ship::factory()->starter()->create();
        PlayerShip::factory()->active()->create([
            'player_id' => $this->player->id,
            'ship_id' => $ship->id,
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(401);
    }

    public function test_invalid_player_uuid_returns_404(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/players/00000000-0000-0000-0000-000000000000/facilities');

        $response->assertStatus(404);
    }

    public function test_system_with_trading_hub(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $response->assertJsonPath('data.facilities.trading_hubs.0.uuid', (string) $hub->uuid);
        $response->assertJsonPath('data.facilities.trading_hubs.0.actions.inventory', "/api/trading-hubs/{$hub->uuid}/inventory");
        $response->assertJsonPath('data.facilities.trading_hubs.0.actions.buy', "/api/trading-hubs/{$hub->uuid}/buy");
        $response->assertJsonPath('data.facilities.trading_hubs.0.actions.sell', "/api/trading-hubs/{$hub->uuid}/sell");
        $response->assertJsonPath('data.facilities.summary.has_trading', true);
    }

    public function test_cartographer_detected_via_stellar_cartographer_model(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        StellarCartographer::create([
            'poi_id' => $this->star->id,
            'name' => 'Galactic Survey Corps',
            'is_active' => true,
            'chart_base_price' => 1000.00,
            'markup_multiplier' => 1.00,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $response->assertJsonPath('data.facilities.trading_hubs.0.has_cartographer', true);
        $response->assertJsonCount(1, 'data.facilities.cartographers');
        $response->assertJsonPath('data.facilities.cartographers.0.name', 'Galactic Survey Corps');
        $response->assertJsonPath('data.facilities.cartographers.0.actions.browse', "/api/trading-hubs/{$hub->uuid}/cartographer");
        $response->assertJsonPath('data.facilities.cartographers.0.actions.purchase', "/api/players/{$this->player->uuid}/star-charts/purchase");
        $response->assertJsonPath('data.facilities.summary.has_cartography', true);
    }

    public function test_cartographer_absent_when_no_stellar_cartographer_record(): void
    {
        TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $response->assertJsonPath('data.facilities.trading_hubs.0.has_cartographer', false);
        $response->assertJsonCount(0, 'data.facilities.cartographers');
        $response->assertJsonPath('data.facilities.summary.has_cartography', false);
    }

    public function test_ship_shop_detected_via_trading_hub_ships(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        $ship = Ship::factory()->create();
        TradingHubShip::create([
            'trading_hub_id' => $hub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 3,
            'current_price' => 15000.00,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.facilities.ship_shops');
        $response->assertJsonPath('data.facilities.ship_shops.0.uuid', (string) $hub->uuid);
        $response->assertJsonPath('data.facilities.ship_shops.0.actions.browse', "/api/trading-hubs/{$hub->uuid}/ship-shop");
        $response->assertJsonPath('data.facilities.ship_shops.0.actions.purchase', "/api/players/{$this->player->uuid}/ships/purchase");
        $response->assertJsonPath('data.facilities.summary.has_ship_services', true);
    }

    public function test_salvage_yard_detected(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
            'has_salvage_yard' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.facilities.salvage_yards');
        $response->assertJsonPath('data.facilities.salvage_yards.0.actions.browse', "/api/players/{$this->player->uuid}/salvage-yard");
        $response->assertJsonPath('data.facilities.salvage_yards.0.actions.purchase', "/api/players/{$this->player->uuid}/salvage-yard/purchase");
        $response->assertJsonPath('data.facilities.salvage_yards.0.actions.sell_ship', "/api/players/{$this->player->uuid}/salvage-yard/sell-ship");
        $response->assertJsonPath('data.facilities.summary.has_salvage', true);
    }

    public function test_action_urls_are_valid_routes(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
            'has_salvage_yard' => true,
        ]);

        StellarCartographer::create([
            'poi_id' => $this->star->id,
            'name' => 'Survey Corps',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $data = $response->json('data.facilities');

        // Collect all action URLs from the response
        $urls = [];
        foreach (['trading_hubs', 'cartographers', 'salvage_yards', 'ship_shops', 'shipyards'] as $category) {
            foreach ($data[$category] ?? [] as $facility) {
                foreach ($facility['actions'] ?? [] as $url) {
                    if ($url) {
                        $urls[] = $url;
                    }
                }
            }
        }

        // Verify each URL matches a registered route
        $router = app('router');
        foreach ($urls as $url) {
            // Strip /api prefix and check if a route matches
            $path = ltrim($url, '/');
            $routes = collect($router->getRoutes()->getRoutes());
            $matched = $routes->contains(function ($route) use ($path) {
                $pattern = preg_replace('/\{[^}]+\}/', '[^/]+', $route->uri());

                return (bool) preg_match('#^'.$pattern.'$#', $path);
            });
            $this->assertTrue($matched, "URL '{$url}' does not match any registered route");
        }
    }

    public function test_bar_slug_format(): void
    {
        TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        $bars = $response->json('data.facilities.bars');

        $this->assertNotEmpty($bars);
        foreach ($bars as $bar) {
            $this->assertArrayHasKey('slug', $bar);
            $this->assertArrayNotHasKey('id', $bar);
            // Slug should be a valid URL slug (lowercase, hyphens, no spaces)
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $bar['slug']);
        }
    }

    public function test_summary_has_ship_services_true_with_ship_shops(): void
    {
        $hub = TradingHub::factory()->create([
            'poi_id' => $this->star->id,
            'is_active' => true,
        ]);

        $ship = Ship::factory()->create();
        TradingHubShip::create([
            'trading_hub_id' => $hub->id,
            'ship_id' => $ship->id,
            'galaxy_id' => $this->galaxy->id,
            'quantity' => 1,
            'current_price' => 10000.00,
            'demand_level' => 50,
            'supply_level' => 50,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/facilities");

        $response->assertStatus(200);
        // has_ship_services should be true even without orbital shipyard POIs
        $response->assertJsonPath('data.facilities.summary.has_ship_services', true);
        $response->assertJsonPath('data.facilities.summary.total_ship_shops', 1);
        $response->assertJsonPath('data.facilities.summary.total_shipyards', 0);
    }

    public function test_spawn_prefers_systems_with_trading_hub(): void
    {
        $galaxy = Galaxy::factory()->active()->create();

        // Create inhabited star WITHOUT trading hub
        PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'name' => 'No Hub Star',
            'x' => 50,
            'y' => 50,
        ]);

        // Create inhabited star WITH trading hub
        $starWithHub = PointOfInterest::factory()->create([
            'galaxy_id' => $galaxy->id,
            'type' => PointOfInterestType::STAR,
            'is_inhabited' => true,
            'name' => 'Hub Star',
            'x' => 200,
            'y' => 200,
        ]);

        TradingHub::factory()->create([
            'poi_id' => $starWithHub->id,
            'is_active' => true,
        ]);

        // Seed starter ship for join
        Ship::factory()->create(['class' => 'starter']);

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson("/api/galaxies/{$galaxy->uuid}/join", [
                'call_sign' => 'TestPilot',
            ]);

        $response->assertStatus(201);

        // Player should spawn at the star with a trading hub
        $player = Player::where('user_id', $user->id)
            ->where('galaxy_id', $galaxy->id)
            ->first();

        $this->assertNotNull($player);
        $this->assertEquals($starWithHub->id, $player->current_poi_id);
    }
}
