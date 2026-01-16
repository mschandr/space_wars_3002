<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use App\Models\WarpGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MirrorUniverseTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;
    private User $user;
    private Player $player;
    private PlayerShip $ship;
    private WarpGate $mirrorGate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galaxy = Galaxy::factory()->create();

        $poi1 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $poi2 = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);

        $this->mirrorGate = WarpGate::factory()->mirrorEntry()->create([
            'galaxy_id' => $this->galaxy->id,
            'source_poi_id' => $poi1->id,
            'destination_poi_id' => $poi2->id,
        ]);

        $this->user = User::factory()->create();
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $poi1->id,
        ]);

        $shipBlueprint = Ship::factory()->create();
        $this->ship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'ship_id' => $shipBlueprint->id,
            'sensors' => 5,
            'is_active' => true,
        ]);
    }

    public function test_it_can_check_mirror_universe_access()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign'],
                'access' => [
                    'has_sufficient_sensors',
                    'required_sensor_level',
                    'current_sensor_level',
                    'can_travel',
                    'cooldown_remaining_hours',
                    'next_available_at',
                ],
                'mirror_gate' => [
                    'uuid',
                    'location' => ['poi_uuid', 'name', 'x', 'y'],
                    'is_at_gate',
                ],
                'mirror_modifiers',
            ],
        ]);
    }

    public function test_player_with_sufficient_sensors_can_access()
    {
        $this->ship->update(['sensors' => 5]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(200);
        $response->assertJsonPath('data.access.has_sufficient_sensors', true);
        $response->assertJsonPath('data.access.can_travel', true);
    }

    public function test_player_with_insufficient_sensors_cannot_access()
    {
        $this->ship->update(['sensors' => 3]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(200);
        $response->assertJsonPath('data.access.has_sufficient_sensors', false);
        $response->assertJsonPath('data.access.can_travel', false);
    }

    public function test_it_shows_cooldown_status()
    {
        $this->player->update(['last_mirror_travel_at' => now()->subHours(12)]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(200);
        $response->assertJsonPath('data.access.cooldown_remaining_hours', 12);
        $response->assertJsonPath('data.access.can_travel', false);
    }

    public function test_it_shows_player_is_at_gate()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(200);
        $response->assertJsonPath('data.mirror_gate.is_at_gate', true);
    }

    public function test_it_can_get_mirror_gate_location()
    {
        $response = $this->actingAs($this->user)
            ->getJson("/api/galaxies/{$this->galaxy->uuid}/mirror-gate");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'mirror_gate' => [
                    'uuid',
                    'location' => ['poi_uuid', 'name', 'coordinates'],
                    'destination',
                ],
                'requirements' => [
                    'sensor_level',
                    'cooldown_hours',
                ],
                'warnings',
            ],
        ]);
    }

    public function test_it_returns_404_when_no_mirror_gate_exists()
    {
        $this->mirrorGate->delete();

        $response = $this->actingAs($this->user)
            ->getJson("/api/galaxies/{$this->galaxy->uuid}/mirror-gate");

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_it_can_enter_mirror_universe()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/mirror/enter");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'travel_result',
                'message',
                'warnings' => [
                    'doubled_pirate_difficulty',
                    'doubled_resources',
                    'return_cooldown_active',
                    'next_available_return',
                ],
            ],
        ]);

        // Verify cooldown was set
        $this->player->refresh();
        $this->assertNotNull($this->player->last_mirror_travel_at);
    }

    public function test_it_prevents_entry_without_sufficient_sensors()
    {
        $this->ship->update(['sensors' => 3]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/mirror/enter");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_it_prevents_entry_during_cooldown()
    {
        $this->player->update(['last_mirror_travel_at' => now()->subHours(12)]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/mirror/enter");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_it_prevents_entry_when_not_at_gate()
    {
        $otherPoi = PointOfInterest::factory()->create(['galaxy_id' => $this->galaxy->id]);
        $this->player->update(['current_poi_id' => $otherPoi->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/mirror/enter");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_it_returns_404_for_player_without_ship()
    {
        $this->ship->delete();

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/mirror-access");

        $response->assertStatus(400);
    }

    public function test_it_returns_404_for_nonexistent_player()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/players/nonexistent-uuid/mirror-access');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_galaxy()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/galaxies/nonexistent-uuid/mirror-gate');

        $response->assertStatus(404);
    }
}
