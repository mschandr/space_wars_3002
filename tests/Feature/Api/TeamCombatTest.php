<?php

namespace Tests\Feature\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\PvPChallenge;
use App\Models\PvPTeamInvitation;
use App\Models\Ship;
use App\Models\TradingHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamCombatTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    private User $user2;

    private User $user3;

    private User $user4;

    private Player $player1;

    private Player $player2;

    private Player $player3;

    private Player $player4;

    private PlayerShip $ship1;

    private PlayerShip $ship2;

    private PlayerShip $ship3;

    private PlayerShip $ship4;

    private PointOfInterest $location;

    private Galaxy $galaxy;

    private PvPChallenge $challenge;

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

        // Create ship blueprint
        $shipBlueprint = Ship::factory()->create([
            'name' => 'Scout',
            'hull_strength' => 100,
            'weapon_slots' => 2,
        ]);

        // Create 4 users and players
        for ($i = 1; $i <= 4; $i++) {
            $user = User::factory()->create();
            $this->{"user{$i}"} = $user;

            $player = Player::factory()->create([
                'user_id' => $user->id,
                'galaxy_id' => $this->galaxy->id,
                'current_poi_id' => $this->location->id,
                'credits' => 10000,
                'call_sign' => "Player{$i}",
            ]);
            $this->{"player{$i}"} = $player;

            $ship = PlayerShip::factory()->create([
                'player_id' => $player->id,
                'ship_id' => $shipBlueprint->id,
                'name' => "Ship{$i}",
                'hull' => 100,
                'weapons' => 20,
                'is_active' => true,
            ]);
            $this->{"ship{$i}"} = $ship;
        }

        // Create a team challenge (2v2)
        $this->challenge = PvPChallenge::create([
            'challenger_id' => $this->player1->id,
            'target_id' => $this->player2->id,
            'status' => 'pending',
            'wager_credits' => 100,
            'max_team_size' => 2,
            'challenge_poi_id' => $this->location->id,
        ]);
    }

    public function test_it_can_invite_ally_to_attacker_side()
    {
        $response = $this->actingAs($this->user1)
            ->postJson("/api/players/{$this->player1->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player3->uuid,
                'side' => 'attacker',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.invitation.side', 'attacker');

        $this->assertDatabaseHas('pvp_team_invitations', [
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'pending',
        ]);
    }

    public function test_it_can_invite_ally_to_defender_side()
    {
        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player4->uuid,
                'side' => 'defender',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.invitation.side', 'defender');

        $this->assertDatabaseHas('pvp_team_invitations', [
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'pending',
        ]);
    }

    public function test_challenger_cannot_invite_to_defender_side()
    {
        $response = $this->actingAs($this->user1)
            ->postJson("/api/players/{$this->player1->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player3->uuid,
                'side' => 'defender',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'Challenger can only invite attackers');
    }

    public function test_target_cannot_invite_to_attacker_side()
    {
        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player3->uuid,
                'side' => 'attacker',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'Target can only invite defenders');
    }

    public function test_it_prevents_duplicate_invitations()
    {
        // Send first invitation
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
        ]);

        // Try to send duplicate
        $response = $this->actingAs($this->user1)
            ->postJson("/api/players/{$this->player1->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player3->uuid,
                'side' => 'attacker',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'Player has already been invited');
    }

    public function test_it_prevents_inviting_beyond_team_size()
    {
        // Already have 1 player (challenger), max is 2
        // Accept one invitation
        $invitation1 = PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        // Try to invite another (would be 3rd player)
        $response = $this->actingAs($this->user1)
            ->postJson("/api/players/{$this->player1->uuid}/pvp/challenge/{$this->challenge->uuid}/invite", [
                'invitee_uuid' => $this->player4->uuid,
                'side' => 'attacker',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'Team is full');
    }

    public function test_it_can_list_team_invitations()
    {
        // Create invitation
        $invitation = PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
        ]);

        $response = $this->actingAs($this->user3)
            ->getJson("/api/players/{$this->player3->uuid}/team-invitations");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.invitations.0.side', 'attacker');
    }

    public function test_it_can_accept_team_invitation()
    {
        $invitation = PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
        ]);

        $response = $this->actingAs($this->user3)
            ->postJson("/api/players/{$this->player3->uuid}/team-invitations/{$invitation->id}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('pvp_team_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);
    }

    public function test_it_can_decline_team_invitation()
    {
        $invitation = PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
        ]);

        $response = $this->actingAs($this->user3)
            ->postJson("/api/players/{$this->player3->uuid}/team-invitations/{$invitation->id}/decline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('pvp_team_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
    }

    public function test_it_prevents_accepting_invitation_not_for_you()
    {
        $invitation = PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
        ]);

        // Player 4 tries to accept invitation meant for Player 3
        $response = $this->actingAs($this->user4)
            ->postJson("/api/players/{$this->player4->uuid}/team-invitations/{$invitation->id}/accept");

        $response->assertStatus(400);
        $response->assertJsonPath('error.message', 'This invitation is not for you');
    }

    public function test_it_can_view_team_composition()
    {
        // Accept allies
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user1)
            ->getJson("/api/pvp/challenge/{$this->challenge->uuid}/teams");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.attackers_count', 2);
        $response->assertJsonPath('data.defenders_count', 2);
    }

    public function test_it_can_accept_team_challenge_and_start_combat()
    {
        // Set up 2v2 teams
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/accept-team");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'combat_session' => ['uuid'],
                'result' => [
                    'victor_team',
                    'victors',
                    'losers',
                    'rounds',
                    'combat_log',
                    'death_results',
                ],
            ],
        ]);

        // Verify combat session was created
        $this->assertDatabaseHas('combat_sessions', [
            'pvp_challenge_id' => $this->challenge->id,
            'status' => 'completed',
        ]);

        // Verify 4 participants
        $this->assertDatabaseCount('combat_participants', 4);

        // Verify victors and losers
        $victors = $response->json('data.result.victors');
        $losers = $response->json('data.result.losers');

        $this->assertGreaterThan(0, count($victors));
        $this->assertGreaterThan(0, count($losers));
    }

    public function test_team_combat_awards_shared_rewards()
    {
        // Set up 2v2
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'accepted',
        ]);

        $initialCredits = [
            $this->player1->id => $this->player1->credits,
            $this->player2->id => $this->player2->credits,
            $this->player3->id => $this->player3->credits,
            $this->player4->id => $this->player4->credits,
        ];

        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/accept-team");

        $response->assertStatus(200);

        // Refresh all players
        $this->player1->refresh();
        $this->player2->refresh();
        $this->player3->refresh();
        $this->player4->refresh();

        // Get victors from response
        $victors = collect($response->json('data.result.victors'));

        // Each victor should have earned credits (shared pot divided among victors)
        foreach ($victors as $victor) {
            $victorPlayer = Player::where('uuid', $victor['player']['uuid'])->first();
            $creditsEarned = $victor['credits_earned'];

            // Wager was 100 per player, 4 players = 400 total pot
            // Victors should share this pot
            $this->assertGreaterThan(0, $creditsEarned);
        }
    }

    public function test_team_combat_handles_all_deaths()
    {
        // Create trading hub for respawn
        $tradingHub = TradingHub::factory()->create([
            'poi_id' => $this->location->id,
        ]);

        \DB::table('trading_hub_ships')->insert([
            'trading_hub_id' => $tradingHub->id,
            'ship_id' => $this->ship1->ship_id,
            'galaxy_id' => $this->galaxy->id,
            'current_price' => 5000,
            'quantity' => 10,
        ]);

        // Set up 2v2
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/accept-team");

        $response->assertStatus(200);

        $losers = $response->json('data.result.losers');
        $deathResults = $response->json('data.result.death_results');

        // Verify death results exist for all losers
        $this->assertEquals(count($losers), count($deathResults));

        foreach ($deathResults as $deathResult) {
            $this->assertArrayHasKey('respawn_location', $deathResult);
        }
    }

    public function test_it_creates_team_challenge_with_max_size()
    {
        // Challenge player3 instead (no existing challenge)
        $response = $this->actingAs($this->user1)
            ->postJson("/api/players/{$this->player1->uuid}/pvp/challenge", [
                'target_player_uuid' => $this->player3->uuid,
                'max_team_size' => 3,
                'wager_credits' => 500,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('pvp_challenges', [
            'challenger_id' => $this->player1->id,
            'target_id' => $this->player3->id,
            'max_team_size' => 3,
            'wager_credits' => 500,
        ]);
    }

    public function test_combat_log_shows_all_team_members()
    {
        // Set up 2v2
        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player3->id,
            'invited_by_player_id' => $this->player1->id,
            'side' => 'attacker',
            'status' => 'accepted',
        ]);

        PvPTeamInvitation::create([
            'pvp_challenge_id' => $this->challenge->id,
            'invited_player_id' => $this->player4->id,
            'invited_by_player_id' => $this->player2->id,
            'side' => 'defender',
            'status' => 'accepted',
        ]);

        $response = $this->actingAs($this->user2)
            ->postJson("/api/players/{$this->player2->uuid}/pvp/challenge/{$this->challenge->uuid}/accept-team");

        $response->assertStatus(200);

        $combatLog = $response->json('data.result.combat_log');

        // Check that combat log contains all 4 players
        $logContent = json_encode($combatLog);
        $this->assertStringContainsString('Player1', $logContent);
        $this->assertStringContainsString('Player2', $logContent);
        $this->assertStringContainsString('Player3', $logContent);
        $this->assertStringContainsString('Player4', $logContent);
    }
}
