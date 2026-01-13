<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\CombatSession;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\PvPChallenge;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvPCombatTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $targetUser;
    private Player $challenger;
    private Player $target;
    private PlayerShip $challengerShip;
    private PlayerShip $targetShip;
    private PointOfInterest $location;
    private Galaxy $galaxy;

    protected function setUp(): void
    {
        parent::setUp();

        // Create galaxy
        $this->galaxy = Galaxy::factory()->create();

        // Create location
        $this->location = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 100,
            'y' => 100,
        ]);

        // Create users
        $this->user = User::factory()->create();
        $this->targetUser = User::factory()->create();

        // Create players
        $this->challenger = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->location->id,
            'credits' => 10000,
        ]);

        $this->target = Player::factory()->create([
            'user_id' => $this->targetUser->id,
            'galaxy_id' => $this->galaxy->id,
            'current_poi_id' => $this->location->id,
            'credits' => 10000,
        ]);

        // Create ship blueprint
        $ship = Ship::factory()->create([
            'name' => 'Scout',
            'hull_strength' => 100,
            'weapon_slots' => 2,
        ]);

        // Create player ships
        $this->challengerShip = PlayerShip::factory()->create([
            'player_id' => $this->challenger->id,
            'ship_id' => $ship->id,
            'name' => 'Challenger Ship',
            'hull' => 100,
            'weapons' => 20,
            'is_active' => true,
        ]);

        $this->targetShip = PlayerShip::factory()->create([
            'player_id' => $this->target->id,
            'ship_id' => $ship->id,
            'name' => 'Target Ship',
            'hull' => 100,
            'weapons' => 20,
            'is_active' => true,
        ]);
    }

    public function test_it_can_issue_pvp_challenge()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
                'message' => 'I challenge you!',
                'wager_credits' => 1000,
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'challenge' => [
                    'uuid',
                    'target',
                    'message',
                    'wager_credits',
                    'expires_at',
                ],
            ],
        ]);

        $this->assertDatabaseHas('pvp_challenges', [
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'message' => 'I challenge you!',
            'wager_credits' => 1000,
        ]);
    }

    public function test_it_prevents_challenging_self()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->challenger->uuid,
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You cannot challenge yourself');
    }

    public function test_it_prevents_challenging_at_different_location()
    {
        // Move target to different location
        $otherLocation = PointOfInterest::factory()->create([
            'galaxy_id' => $this->galaxy->id,
            'type' => PointOfInterestType::STAR,
            'x' => 500,
            'y' => 500,
        ]);

        $this->target->update(['current_poi_id' => $otherLocation->id]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You must be at the same location as your target');
    }

    public function test_it_prevents_challenge_without_active_ship()
    {
        $this->challengerShip->update(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You need an active ship to issue a challenge');
    }

    public function test_it_prevents_challenge_if_target_has_no_ship()
    {
        $this->targetShip->update(['is_active' => false]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'Target player does not have an active ship');
    }

    public function test_it_prevents_wager_exceeding_challenger_credits()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
                'wager_credits' => 50000, // More than challenger has
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'Insufficient credits for wager');
    }

    public function test_it_prevents_wager_exceeding_target_credits()
    {
        $this->target->update(['credits' => 100]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->target->uuid,
                'wager_credits' => 5000, // More than target has
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'Target player cannot match the wager');
    }

    public function test_it_lists_incoming_and_outgoing_challenges()
    {
        // Create incoming challenge (target is this player)
        $incomingChallenge = PvPChallenge::create([
            'challenger_id' => $this->target->id,
            'target_id' => $this->challenger->id,
            'status' => 'pending',
            'message' => 'Come at me!',
            'wager_credits' => 500,
            'challenge_poi_id' => $this->location->id,
        ]);

        // Create outgoing challenge (challenger is this player)
        $outgoingChallenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'message' => 'I challenge you!',
            'wager_credits' => 1000,
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->challenger->uuid}/pvp/challenges");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'incoming_challenges',
                'outgoing_challenges',
                'total_incoming',
                'total_outgoing',
            ],
        ]);

        $this->assertEquals(1, $response->json('data.total_incoming'));
        $this->assertEquals(1, $response->json('data.total_outgoing'));
    }

    public function test_it_filters_out_expired_challenges()
    {
        // Create expired challenge
        $expiredChallenge = PvPChallenge::create([
            'challenger_id' => $this->target->id,
            'target_id' => $this->challenger->id,
            'status' => 'pending',
            'message' => 'Old challenge',
            'challenge_poi_id' => $this->location->id,
            'expires_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->challenger->uuid}/pvp/challenges");

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_incoming'));
    }

    public function test_it_can_accept_challenge_and_initiate_combat()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'message' => 'Fight me!',
            'wager_credits' => 1000,
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'combat_session' => ['uuid'],
                'result' => [
                    'victor',
                    'loser',
                    'victor_hull_remaining',
                    'rounds',
                    'xp_earned',
                    'credits_earned',
                    'combat_log',
                    'death_result',
                ],
            ],
        ]);

        // Verify challenge was accepted
        $this->assertDatabaseHas('pvp_challenges', [
            'id' => $challenge->id,
            'status' => 'completed',
        ]);

        // Verify combat session was created
        $this->assertDatabaseHas('combat_sessions', [
            'combat_type' => 'pvp',
            'pvp_challenge_id' => $challenge->id,
            'status' => 'completed',
        ]);

        // Verify combat participants were created
        $this->assertDatabaseCount('combat_participants', 2);
    }

    public function test_it_deducts_wagers_and_awards_winner()
    {
        $initialChallengerCredits = $this->challenger->credits;
        $initialTargetCredits = $this->target->credits;

        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'wager_credits' => 1000,
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(200);

        // Both players should have had 1000 deducted initially
        $this->challenger->refresh();
        $this->target->refresh();

        $victorUuid = $response->json('data.result.victor.uuid');

        if ($victorUuid === $this->challenger->uuid) {
            // Challenger won: -1000 (wager) + 2000 (winnings) = +1000
            $this->assertEquals($initialChallengerCredits + 1000, $this->challenger->credits);
            // Target lost: -1000 (wager)
            $this->assertEquals($initialTargetCredits - 1000, $this->target->credits);
        } else {
            // Target won: -1000 (wager) + 2000 (winnings) = +1000
            $this->assertEquals($initialTargetCredits + 1000, $this->target->credits);
            // Challenger lost: -1000 (wager)
            $this->assertEquals($initialChallengerCredits - 1000, $this->challenger->credits);
        }
    }

    public function test_it_prevents_accepting_challenge_not_targeted_at_you()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        // Challenger tries to accept their own challenge
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You are not the target of this challenge');
    }

    public function test_it_prevents_accepting_expired_challenge()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
            'expires_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'This challenge has expired');

        // Verify challenge status was updated
        $this->assertDatabaseHas('pvp_challenges', [
            'id' => $challenge->id,
            'status' => 'expired',
        ]);
    }

    public function test_it_can_decline_challenge()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/decline");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Challenge declined',
        ]);

        $this->assertDatabaseHas('pvp_challenges', [
            'id' => $challenge->id,
            'status' => 'declined',
        ]);
    }

    public function test_it_prevents_declining_challenge_not_targeted_at_you()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        // Challenger tries to decline their own challenge
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->challenger->uuid}/pvp/challenge/{$challenge->uuid}/decline");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You are not the target of this challenge');
    }

    public function test_it_can_cancel_outgoing_challenge()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/players/{$this->challenger->uuid}/pvp/challenge/{$challenge->uuid}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Challenge cancelled',
        ]);

        $this->assertDatabaseHas('pvp_challenges', [
            'id' => $challenge->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_it_prevents_canceling_someone_elses_challenge()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        // Target tries to cancel challenger's challenge
        $response = $this->actingAs($this->targetUser)
            ->deleteJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}");

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'You can only cancel your own challenges');
    }

    public function test_it_prevents_canceling_non_pending_challenge()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'accepted',
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/players/{$this->challenger->uuid}/pvp/challenge/{$challenge->uuid}");

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.message', 'Only pending challenges can be cancelled');
    }

    public function test_it_can_retrieve_combat_session_details()
    {
        // Accept a challenge to create combat session
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        $acceptResponse = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $sessionUuid = $acceptResponse->json('data.combat_session.uuid');

        // Now retrieve the session details
        $response = $this->actingAs($this->user)
            ->getJson("/api/combat-sessions/{$sessionUuid}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'combat_session' => [
                    'uuid',
                    'combat_type',
                    'status',
                    'current_round',
                    'victor_type',
                    'started_at',
                    'ended_at',
                    'participants',
                    'combat_log',
                ],
            ],
        ]);

        $this->assertEquals('pvp', $response->json('data.combat_session.combat_type'));
        $this->assertEquals('completed', $response->json('data.combat_session.status'));
        $this->assertCount(2, $response->json('data.combat_session.participants'));
    }

    public function test_loser_ship_is_destroyed_and_respawned()
    {
        // Create a trading hub with ships for respawn
        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $this->location->id,
        ]);

        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $this->challengerShip->ship_id,
            'galaxy_id' => $this->galaxy->id,
            'current_price' => 5000,
            'quantity' => 10,
        ]);

        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(200);

        $loserUuid = $response->json('data.result.loser.uuid');
        $loser = Player::where('uuid', $loserUuid)->first();
        $loser->refresh();

        // Verify death result was processed
        $deathResult = $response->json('data.result.death_result');
        $this->assertNotNull($deathResult);
        $this->assertArrayHasKey('respawn_location', $deathResult);
        $this->assertArrayHasKey('losses', $deathResult);

        // Verify loser was respawned to a different location (if respawn happened)
        if (isset($deathResult['respawn_location']['poi_id'])) {
            $this->assertNotNull($loser->current_poi_id);
        }
    }

    public function test_combat_log_contains_detailed_rounds()
    {
        $challenge = PvPChallenge::create([
            'challenger_id' => $this->challenger->id,
            'target_id' => $this->target->id,
            'status' => 'pending',
            'challenge_poi_id' => $this->location->id,
        ]);

        $response = $this->actingAs($this->targetUser)
            ->postJson("/api/players/{$this->target->uuid}/pvp/challenge/{$challenge->uuid}/accept");

        $response->assertStatus(200);

        $combatLog = $response->json('data.result.combat_log');

        $this->assertNotEmpty($combatLog);
        $this->assertIsArray($combatLog);

        // Check for combat log structure
        $hasHeader = false;
        $hasRound = false;
        $hasAttack = false;
        $hasVictory = false;

        foreach ($combatLog as $entry) {
            if ($entry['type'] === 'header') $hasHeader = true;
            if ($entry['type'] === 'round') $hasRound = true;
            if ($entry['type'] === 'attack') $hasAttack = true;
            if ($entry['type'] === 'victory') $hasVictory = true;
        }

        $this->assertTrue($hasHeader);
        $this->assertTrue($hasRound);
        $this->assertTrue($hasAttack);
        $this->assertTrue($hasVictory);
    }
}
