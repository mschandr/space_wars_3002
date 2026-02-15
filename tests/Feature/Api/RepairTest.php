<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Player $player;

    private PlayerShip $playerShip;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and authenticate
        $this->user = User::factory()->create();

        // Create galaxy and location
        $galaxy = Galaxy::factory()->create();
        $star = PointOfInterest::factory()->create(['galaxy_id' => $galaxy->id]);

        // Create player
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
            'current_poi_id' => $star->id,
            'credits' => 10000,
        ]);

        // Create player ship with damage
        $ship = Ship::factory()->create([
            'hull_strength' => 100,
            'cargo_capacity' => 100,
            'attributes' => [
                'starting_weapons' => 50,
                'starting_sensors' => 3,
                'starting_warp_drive' => 2,
                'max_fuel' => 100,
            ],
        ]);
        $this->playerShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $ship->id,
            'is_active' => true,
            'hull' => 60, // Damaged
            'max_hull' => 100,
            'weapons' => 50,
            'sensors' => 3,
            'warp_drive' => 2,
            'cargo_hold' => 100,
            'max_fuel' => 100,
        ]);
    }

    public function test_it_gets_repair_estimate()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$this->playerShip->uuid}/repair-estimate");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'hull_damage',
                    'hull_repair_cost',
                    'needs_hull_repair',
                    'downgraded_components',
                    'component_repair_cost',
                    'needs_component_repair',
                    'total_repair_cost',
                    'hull_percentage',
                ],
            ]);

        // Verify calculations
        $this->assertEquals(40, $response->json('data.hull_damage')); // 100 - 60
        $this->assertEquals(400, $response->json('data.hull_repair_cost')); // 40 * 10
        $this->assertTrue($response->json('data.needs_hull_repair'));
        $this->assertEquals(60.0, $response->json('data.hull_percentage'));
    }

    public function test_it_repairs_hull()
    {
        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/hull");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'hull_repaired',
                    'cost',
                    'current_hull',
                    'max_hull',
                    'remaining_credits',
                ],
            ]);

        $this->assertEquals(40, $response->json('data.hull_repaired'));
        $this->assertEquals(400, $response->json('data.cost'));
        $this->assertEquals(100, $response->json('data.current_hull'));
        $this->assertEquals($oldCredits - 400, $response->json('data.remaining_credits'));

        // Verify database
        $this->playerShip->refresh();
        $this->assertEquals(100, $this->playerShip->hull);

        $this->player->refresh();
        $this->assertEquals($oldCredits - 400, $this->player->credits);
    }

    public function test_it_fails_hull_repair_with_insufficient_credits()
    {
        // Set credits too low
        $this->player->update(['credits' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/hull");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Insufficient credits for hull repair',
                ],
            ]);

        // Verify ship not repaired
        $this->playerShip->refresh();
        $this->assertEquals(60, $this->playerShip->hull);
    }

    public function test_it_repairs_downgraded_components()
    {
        // Damage components below base values
        $baseWeapons = $this->playerShip->ship->attributes['starting_weapons'] ?? 10;
        $this->playerShip->update(['weapons' => $baseWeapons - 5]); // Downgrade

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/components");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'components_repaired',
                    'cost',
                    'remaining_credits',
                ],
            ]);

        // Verify component restored
        $this->playerShip->refresh();
        $this->assertEquals($baseWeapons, $this->playerShip->weapons);
    }

    public function test_it_returns_error_when_no_components_need_repair()
    {
        // Components are at base values, no downgrade
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/components");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'No components need repair',
                ],
            ]);
    }

    public function test_it_repairs_everything_at_once()
    {
        // Damage hull and components
        $baseWeapons = $this->playerShip->ship->attributes['starting_weapons'] ?? 10;
        $this->playerShip->update([
            'hull' => 50,
            'weapons' => $baseWeapons - 3,
        ]);

        $oldCredits = $this->player->credits;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/all");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'cost',
                    'current_hull',
                    'max_hull',
                    'remaining_credits',
                ],
            ]);

        // Verify everything repaired
        $this->playerShip->refresh();
        $this->assertEquals(100, $this->playerShip->hull);
        $this->assertEquals($baseWeapons, $this->playerShip->weapons);

        // Verify credits deducted
        $this->player->refresh();
        $this->assertLessThan($oldCredits, $this->player->credits);
    }

    public function test_it_returns_error_when_ship_is_perfect()
    {
        // Repair ship fully first
        $this->playerShip->update(['hull' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/all");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Ship is already in perfect condition',
                ],
            ]);
    }

    public function test_it_gets_maintenance_status()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$this->playerShip->uuid}/maintenance");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'hull_percentage',
                    'current_hull',
                    'max_hull',
                    'damage',
                    'needs_repair',
                    'estimated_repair_cost',
                    'is_operational',
                ],
            ]);

        // Verify status (60% hull = fair)
        $this->assertEquals('fair', $response->json('data.status'));
        $this->assertEquals(60.0, $response->json('data.hull_percentage'));
        $this->assertTrue($response->json('data.needs_repair'));
    }

    public function test_it_shows_excellent_status_for_healthy_ship()
    {
        $this->playerShip->update(['hull' => 100]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$this->playerShip->uuid}/maintenance");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'excellent',
                    'hull_percentage' => 100.0,
                    'needs_repair' => false,
                ],
            ]);
    }

    public function test_it_shows_critical_status_for_damaged_ship()
    {
        $this->playerShip->update(['hull' => 20]); // 20%

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$this->playerShip->uuid}/maintenance");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'critical',
                    'needs_repair' => true,
                ],
            ]);
    }

    public function test_it_requires_authentication()
    {
        $response = $this->getJson("/api/ships/{$this->playerShip->uuid}/repair-estimate");

        $response->assertUnauthorized();
    }

    public function test_it_authorizes_ship_owner()
    {
        // Create another user
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/ships/{$this->playerShip->uuid}/repair/hull");

        $response->assertStatus(403);
    }

    public function test_it_calculates_repair_cost_correctly()
    {
        // 40 hull points damaged * 10 credits per point = 400 total
        $this->playerShip->update(['hull' => 60]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$this->playerShip->uuid}/repair-estimate");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'hull_damage' => 40,
                    'hull_repair_cost' => 400,
                    'total_repair_cost' => 400,
                ],
            ]);
    }

    public function test_it_returns_404_for_nonexistent_ship()
    {
        $fakeUuid = \Illuminate\Support\Str::uuid();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/ships/{$fakeUuid}/repair-estimate");

        $response->assertNotFound();
    }
}
