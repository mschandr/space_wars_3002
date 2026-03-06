<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Player $player;
    private PointOfInterest $hub_poi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->player = Player::factory()->create(['user_id' => $this->user->id]);
        $this->hub_poi = PointOfInterest::factory()->create();
    }

    /** @test */
    public function listContracts_returns_contracts_at_location(): void
    {
        Contract::factory()->create([
            'bar_location_id' => $this->hub_poi->id,
            'status' => 'POSTED',
        ]);

        $response = $this->getJson("/api/trading-hubs/{$this->hub_poi->uuid}/contracts");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function listContracts_filters_by_type(): void
    {
        Contract::factory()->create([
            'bar_location_id' => $this->hub_poi->id,
            'type' => 'TRANSPORT',
            'status' => 'POSTED',
        ]);

        Contract::factory()->create([
            'bar_location_id' => $this->hub_poi->id,
            'type' => 'SUPPLY',
            'status' => 'POSTED',
        ]);

        $response = $this->getJson("/api/trading-hubs/{$this->hub_poi->uuid}/contracts?type=TRANSPORT");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.type', 'TRANSPORT');
    }

    /** @test */
    public function listContracts_filters_by_min_reward(): void
    {
        Contract::factory()->create([
            'bar_location_id' => $this->hub_poi->id,
            'reward_credits' => 5000,
            'status' => 'POSTED',
        ]);

        Contract::factory()->create([
            'bar_location_id' => $this->hub_poi->id,
            'reward_credits' => 15000,
            'status' => 'POSTED',
        ]);

        $response = $this->getJson("/api/trading-hubs/{$this->hub_poi->uuid}/contracts?min_reward=10000");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.reward_credits', 15000);
    }

    /** @test */
    public function acceptContract_requires_authentication(): void
    {
        $contract = Contract::factory()->create(['status' => 'POSTED']);

        $response = $this->postJson("/api/contracts/{$contract->uuid}/accept");

        $response->assertStatus(401);
    }

    /** @test */
    public function acceptContract_accepts_valid_contract(): void
    {
        $contract = Contract::factory()->create(['status' => 'POSTED']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/accept");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'ACCEPTED');
        $response->assertJsonPath('data.accepted_by_player_id', $this->player->id);

        $contract->refresh();
        $this->assertEquals('ACCEPTED', $contract->status);
    }

    /** @test */
    public function acceptContract_rejects_already_accepted(): void
    {
        $other_player = Player::factory()->create();
        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $other_player->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/accept");

        $response->assertStatus(422);
    }

    /** @test */
    public function acceptContract_rejects_low_reputation(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'POSTED',
            'reputation_min' => 100,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/accept");

        $response->assertStatus(422);
        $response->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Reputation'));
    }

    /** @test */
    public function deliverCargo_requires_authentication(): void
    {
        $contract = Contract::factory()->create();

        $response = $this->postJson("/api/contracts/{$contract->uuid}/deliver", [
            'cargo' => [1 => 100],
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function deliverCargo_requires_at_destination(): void
    {
        $destination = PointOfInterest::factory()->create();
        $this->player->update(['current_poi_id' => PointOfInterest::factory()->create()->id]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $this->player->id,
            'destination_location_id' => $destination->id,
            'cargo_manifest' => [['commodity_id' => 1, 'quantity' => 100]],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/deliver", [
                'cargo' => [1 => 100],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', fn ($msg) => str_contains($msg, 'not at the contract destination'));
    }

    /** @test */
    public function deliverCargo_requires_exact_manifest(): void
    {
        $destination = PointOfInterest::factory()->create();
        $this->player->update(['current_poi_id' => $destination->id]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $this->player->id,
            'destination_location_id' => $destination->id,
            'cargo_manifest' => [['commodity_id' => 1, 'quantity' => 100]],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/deliver", [
                'cargo' => [1 => 50], // Wrong quantity
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function deliverCargo_completes_contract_and_pays_reward(): void
    {
        $destination = PointOfInterest::factory()->create();
        $this->player->update([
            'current_poi_id' => $destination->id,
            'credits' => 1000,
        ]);

        $contract = Contract::factory()->create([
            'status' => 'ACCEPTED',
            'accepted_by_player_id' => $this->player->id,
            'destination_location_id' => $destination->id,
            'reward_credits' => 5000,
            'cargo_manifest' => [['commodity_id' => 1, 'quantity' => 100]],
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/contracts/{$contract->uuid}/deliver", [
                'cargo' => [1 => 100],
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'COMPLETED');

        $this->player->refresh();
        $this->assertEquals(6000, $this->player->credits);
    }

    /** @test */
    public function getMyContracts_returns_player_contracts(): void
    {
        Contract::factory()->create([
            'accepted_by_player_id' => $this->player->id,
            'status' => 'ACCEPTED',
        ]);

        Contract::factory()->create([
            'status' => 'ACCEPTED',
        ]); // Different player

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/contracts");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    /** @test */
    public function getMyContracts_filters_by_status(): void
    {
        Contract::factory()->create([
            'accepted_by_player_id' => $this->player->id,
            'status' => 'ACCEPTED',
        ]);

        Contract::factory()->create([
            'accepted_by_player_id' => $this->player->id,
            'status' => 'COMPLETED',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/contracts?status=COMPLETED");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'COMPLETED');
    }

    /** @test */
    public function getReputation_returns_player_reputation(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/reputation");

        $response->assertStatus(200);
        $response->assertJsonPath('data.reliability_score', 50); // Default
        $response->assertJsonPath('data.status_tier', 'NEUTRAL');
    }

    /** @test */
    public function getReputation_tracks_completed_count(): void
    {
        $destination = PointOfInterest::factory()->create();
        $this->player->update(['current_poi_id' => $destination->id]);

        // Complete 2 contracts
        for ($i = 0; $i < 2; $i++) {
            $contract = Contract::factory()->create([
                'status' => 'ACCEPTED',
                'accepted_by_player_id' => $this->player->id,
                'destination_location_id' => $destination->id,
                'cargo_manifest' => [['commodity_id' => 1, 'quantity' => 100]],
            ]);

            $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/contracts/{$contract->uuid}/deliver", [
                    'cargo' => [1 => 100],
                ]);
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/players/{$this->player->uuid}/reputation");

        $response->assertStatus(200);
        $response->assertJsonPath('data.completed_count', 2);
    }
}
