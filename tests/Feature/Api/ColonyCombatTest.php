<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Colony;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\Ship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColonyCombatTest extends TestCase
{
    use RefreshDatabase;

    private User $attacker1User, $defender1User;
    private Player $attacker1, $defender1;
    private PlayerShip $attackerShip, $defenderShip;
    private PointOfInterest $planet;
    private Galaxy $galaxy;
    private Colony $colony;

    protected function setUp(): void
    {
        parent::setUp();

        // Create galaxy
        $this->galaxy = Galaxy::factory()->create();

        // Create planet location
        $this->planet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::PLANET,
            'x' => 100,
            'y' => 100,
        ]);

        // Create ship blueprint
        $shipBlueprint = Ship::factory()->create([
            'name' => 'Battleship',
            'hull_strength' => 150,
            'weapon_slots' => 3,
        ]);

        // Create attacker
        $this->attacker1User = User::factory()->create();
        $this->attacker1 = Player::factory()->create([
            'user_id' => $this->attacker1User->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->planet->id,
            'credits' => 50000,
            'call_sign' => 'Attacker1',
        ]);

        $this->attackerShip = PlayerShip::factory()->create([
            'player_id' => $this->attacker1->id,
            'ship_id' => $shipBlueprint->id,
            'name' => 'Warhammer',
            'hull' => 150,
            'weapons' => 30,
            'is_active' => true,
        ]);

        // Create defender with colony
        $this->defender1User = User::factory()->create();
        $this->defender1 = Player::factory()->create([
            'user_id' => $this->defender1User->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->planet->id,
            'credits' => 50000,
            'call_sign' => 'Defender1',
        ]);

        $this->defenderShip = PlayerShip::factory()->create([
            'player_id' => $this->defender1->id,
            'ship_id' => $shipBlueprint->id,
            'name' => 'Guardian',
            'hull' => 150,
            'weapons' => 30,
            'is_active' => true,
        ]);

        // Create colony owned by defender
        $this->colony = Colony::factory()->create([
            'player_id' => $this->defender1->id,
            'poi_id' => $this->planet->id,
            'name' => 'New Terra',
            'population' => 5000,
            'development_level' => 3,
            'defense_rating' => 50,
            'garrison_strength' => 100,
        ]);
    }

    public function test_it_can_view_colony_defenses()
    {
        $response = $this->actingAs($this->attacker1User)
            ->getJson("/api/colonies/{$this->colony->uuid}/defenses");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.colony.name', 'New Terra');
        $response->assertJsonPath('data.colony.development_level', 3);
        $response->assertJsonPath('data.colony.defense_rating', 50);
        $response->assertJsonPath('data.colony.garrison_strength', 100);
    }

    public function test_it_can_attack_colony_with_defenses()
    {
        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'combat_session' => ['uuid'],
                'result' => [
                    'victor',
                    'rounds',
                    'attackers_survived',
                    'colony_captured',
                    'buildings_damaged',
                    'combat_log',
                ],
            ],
        ]);

        // Verify combat session was created
        $this->assertDatabaseHas('combat_sessions', [
            'combat_type' => 'colony_attack',
            'target_colony_id' => $this->colony->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_captures_undefended_colony_instantly()
    {
        // Remove all defenses
        $this->colony->update([
            'defense_rating' => 0,
            'garrison_strength' => 0,
            'development_level' => 0,
        ]);

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.instant_capture', true);

        // Verify colony ownership transferred
        $this->colony->refresh();
        $this->assertEquals($this->attacker1->id, $this->colony->player_id);
    }

    public function test_it_prevents_attacking_own_colony()
    {
        $response = $this->actingAs($this->defender1User)
            ->postJson("/api/players/{$this->defender1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'You cannot attack your own colony');
    }

    public function test_it_prevents_attacking_from_wrong_location()
    {
        // Move attacker to different location
        $otherPlanet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::PLANET,
            'x' => 500,
            'y' => 500,
        ]);

        $this->attacker1->update(['current_poi_id' => $otherPlanet->id]);

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'You must be at the colony location to attack');
    }

    public function test_it_prevents_attacking_without_active_ship()
    {
        $this->attackerShip->update(['is_active' => false]);

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'You need an active ship to attack a colony');
    }

    public function test_it_can_fortify_colony()
    {
        $initialDefense = $this->colony->defense_rating;
        $initialGarrison = $this->colony->garrison_strength;
        $initialCredits = $this->defender1->credits;

        $response = $this->actingAs($this->defender1User)
            ->postJson("/api/colonies/{$this->colony->uuid}/fortify", [
                'credits' => 10000,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->colony->refresh();
        $this->defender1->refresh();

        // Verify defenses increased
        $this->assertGreaterThan($initialDefense, $this->colony->defense_rating);
        $this->assertGreaterThan($initialGarrison, $this->colony->garrison_strength);

        // Verify credits deducted
        $this->assertEquals($initialCredits - 10000, $this->defender1->credits);
    }

    public function test_it_prevents_fortifying_without_credits()
    {
        $this->defender1->update(['credits' => 500]);

        $response = $this->actingAs($this->defender1User)
            ->postJson("/api/colonies/{$this->colony->uuid}/fortify", [
                'credits' => 10000,
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'Insufficient credits');
    }

    public function test_colony_attack_damages_buildings()
    {
        // Create some buildings
        for ($i = 0; $i < 5; $i++) {
            \App\Models\ColonyBuilding::factory()->create([
                'colony_id' => $this->colony->id,
                'building_type' => 'mining_facility',
                'status' => 'operational',
            ]);
        }

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);

        $victor = $response->json('data.result.victor');

        // If attackers won, some buildings should be damaged
        if ($victor === 'attackers') {
            $damagedBuildings = \App\Models\ColonyBuilding::where('colony_id', $this->colony->id)
                ->where('status', 'damaged')
                ->count();

            $this->assertGreaterThan(0, $damagedBuildings);
        }
    }

    public function test_colony_attack_reduces_population()
    {
        $initialPopulation = $this->colony->population;

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);

        $victor = $response->json('data.result.victor');

        // If attackers won, population should be reduced
        if ($victor === 'attackers') {
            $this->colony->refresh();
            $this->assertLessThan($initialPopulation, $this->colony->population);
        }
    }

    public function test_colony_ownership_transfers_on_capture()
    {
        // Create a different planet for the weak colony
        $weakPlanet = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::PLANET,
            'x' => 200,
            'y' => 200,
        ]);

        // Move attacker to that planet
        $this->attacker1->update(['current_poi_id' => $weakPlanet->id]);

        // Create a weak colony on that planet
        $weakColony = Colony::factory()->create([
            'player_id' => $this->defender1->id,
            'poi_id' => $weakPlanet->id,
            'name' => 'Weak Outpost',
            'population' => 300, // Low population makes capture easier
            'development_level' => 1,
            'defense_rating' => 0,
            'garrison_strength' => 0,
        ]);

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$weakColony->uuid}");

        $response->assertStatus(200);

        // Should be instant capture or successful attack
        $instantCapture = $response->json('data.instant_capture');
        $colonyCapture = $response->json('data.result.colony_captured');

        if ($instantCapture || $colonyCapture) {
            $weakColony->refresh();
            $this->assertEquals($this->attacker1->id, $weakColony->player_id);
        }
    }

    public function test_combat_log_includes_npc_defenders()
    {
        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);

        $combatLog = $response->json('data.result.combat_log');

        // Check that combat log mentions defenders
        $logContent = json_encode($combatLog);
        $this->assertStringContainsString('Defense Drone', $logContent);
    }

    public function test_last_attacked_at_is_updated()
    {
        $this->assertNull($this->colony->last_attacked_at);

        $response = $this->actingAs($this->attacker1User)
            ->postJson("/api/players/{$this->attacker1->uuid}/attack-colony/{$this->colony->uuid}");

        $response->assertStatus(200);

        $this->colony->refresh();
        $this->assertNotNull($this->colony->last_attacked_at);
    }
}
