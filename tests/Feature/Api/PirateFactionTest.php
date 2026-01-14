<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\PirateCaptain;
use App\Models\PirateFaction;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PirateFactionTest extends TestCase
{
    use RefreshDatabase;

    private Galaxy $galaxy;
    private PirateFaction $faction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->galaxy = Galaxy::factory()->create();
        $this->faction = PirateFaction::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'name' => 'Crimson Raiders',
        ]);
    }

    public function test_it_can_list_pirate_factions()
    {
        // Create additional factions
        PirateFaction::factory()->count(2)->create([
            'galaxy_id' => $this->galaxy->id,
        ]);

        $response = $this->getJson("/api/galaxies/{$this->galaxy->uuid}/pirate-factions");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_factions', 3);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'galaxy' => ['uuid', 'name'],
                'total_factions',
                'factions' => [
                    '*' => [
                        'uuid',
                        'name',
                        'ideology',
                        'strength',
                        'territory_control',
                        'statistics' => [
                            'total_captains',
                            'total_fleets',
                        ],
                        'description',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_get_faction_details()
    {
        // Create captains for the faction
        PirateCaptain::factory()->count(3)->create([
            'pirate_faction_id' => $this->faction->id,
        ]);

        $response = $this->getJson("/api/pirate-factions/{$this->faction->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.uuid', $this->faction->uuid);
        $response->assertJsonPath('data.name', 'Crimson Raiders');
        $response->assertJsonStructure([
            'success',
            'data' => [
                'uuid',
                'name',
                'ideology',
                'galaxy' => ['uuid', 'name'],
                'statistics' => [
                    'strength',
                    'territory_control',
                    'total_captains',
                    'total_fleets',
                ],
                'description',
                'notable_captains' => [
                    '*' => ['uuid', 'name', 'reputation'],
                ],
            ],
        ]);
    }

    public function test_it_limits_notable_captains_to_5()
    {
        // Create 10 captains
        PirateCaptain::factory()->count(10)->create([
            'pirate_faction_id' => $this->faction->id,
        ]);

        $response = $this->getJson("/api/pirate-factions/{$this->faction->uuid}");

        $response->assertStatus(200);
        $response->assertJsonCount(5, 'data.notable_captains');
    }

    public function test_it_can_get_player_reputation()
    {
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/players/{$player->uuid}/pirate-reputation");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign'],
                'galaxy' => ['uuid', 'name'],
                'faction_reputations' => [
                    '*' => [
                        'faction' => ['uuid', 'name'],
                        'reputation',
                        'standing',
                        'effects' => [
                            'description',
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_neutral_reputation_by_default()
    {
        $user = User::factory()->create();
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'galaxy_id' => $this->galaxy->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/players/{$player->uuid}/pirate-reputation");

        $response->assertStatus(200);
        $response->assertJsonPath('data.faction_reputations.0.reputation', 0);
        $response->assertJsonPath('data.faction_reputations.0.standing', 'Neutral');
    }

    public function test_it_can_list_faction_captains()
    {
        PirateCaptain::factory()->count(5)->create([
            'pirate_faction_id' => $this->faction->id,
        ]);

        $response = $this->getJson("/api/pirate-factions/{$this->faction->uuid}/captains");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_captains', 5);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'faction' => ['uuid', 'name'],
                'total_captains',
                'captains' => [
                    '*' => [
                        'uuid',
                        'name',
                        'reputation',
                        'bounty',
                        'rank',
                        'fleet_count',
                        'status',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_returns_404_for_nonexistent_galaxy()
    {
        $response = $this->getJson('/api/galaxies/nonexistent-uuid/pirate-factions');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_faction()
    {
        $response = $this->getJson('/api/pirate-factions/nonexistent-uuid');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_player()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/players/nonexistent-uuid/pirate-reputation');

        $response->assertStatus(404);
    }
}
