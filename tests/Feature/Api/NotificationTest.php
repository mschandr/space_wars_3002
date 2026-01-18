<?php

namespace Tests\Feature\Api;

use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        $galaxy = Galaxy::factory()->create();
        $this->user = User::factory()->create();
        $this->player = Player::factory()->create([
            'user_id' => $this->user->id,
            'galaxy_id' => $galaxy->id,
        ]);
    }

    public function test_it_can_list_player_notifications()
    {
        PlayerNotification::factory()->count(5)->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/notifications");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_notifications', 5);
        $response->assertJsonPath('data.unread_count', 5);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player' => ['uuid', 'call_sign'],
                'total_notifications',
                'unread_count',
                'notifications' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'message',
                        'data',
                        'is_read',
                        'priority',
                        'created_at',
                        'read_at',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_can_filter_by_read_status()
    {
        PlayerNotification::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        PlayerNotification::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/notifications?read=false");

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data.notifications');
    }

    public function test_it_can_filter_by_type()
    {
        PlayerNotification::factory()->create([
            'player_id' => $this->player->id,
            'type' => 'combat',
        ]);

        PlayerNotification::factory()->create([
            'player_id' => $this->player->id,
            'type' => 'trade',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/notifications?type=combat");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.notifications');
        $response->assertJsonPath('data.notifications.0.type', 'combat');
    }

    public function test_it_can_limit_results()
    {
        PlayerNotification::factory()->count(100)->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/notifications?limit=10");

        $response->assertStatus(200);
        $response->assertJsonCount(10, 'data.notifications');
    }

    public function test_it_can_get_unread_count()
    {
        PlayerNotification::factory()->count(7)->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        PlayerNotification::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/players/{$this->player->uuid}/notifications/unread");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.total_unread', 7);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'player',
                'total_unread',
                'unread_by_type',
            ],
        ]);
    }

    public function test_it_can_mark_notification_as_read()
    {
        $notification = PlayerNotification::factory()->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('player_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_marking_already_read_notification_is_idempotent()
    {
        $notification = PlayerNotification::factory()->create([
            'player_id' => $this->player->id,
            'is_read' => true,
            'read_at' => now()->subHour(),
        ]);

        $originalReadAt = $notification->read_at;

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/{$notification->id}/read");

        $response->assertStatus(200);

        $notification->refresh();
        $this->assertEquals($originalReadAt->timestamp, $notification->read_at->timestamp);
    }

    public function test_it_can_delete_notification()
    {
        $notification = PlayerNotification::factory()->create([
            'player_id' => $this->player->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/players/{$this->player->uuid}/notifications/{$notification->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $this->assertDatabaseMissing('player_notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_it_can_mark_all_notifications_as_read()
    {
        PlayerNotification::factory()->count(5)->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/mark-all-read");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.notifications_marked', 5);

        $this->assertEquals(0, PlayerNotification::where('player_id', $this->player->id)
            ->where('is_read', false)
            ->count());
    }

    public function test_it_can_clear_read_notifications()
    {
        PlayerNotification::factory()->count(3)->create([
            'player_id' => $this->player->id,
            'is_read' => true,
        ]);

        PlayerNotification::factory()->count(2)->create([
            'player_id' => $this->player->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/clear-read");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.notifications_cleared', 3);

        // Unread notifications should remain
        $this->assertEquals(2, PlayerNotification::where('player_id', $this->player->id)->count());
    }

    public function test_it_prevents_accessing_other_players_notifications()
    {
        $otherUser = User::factory()->create();
        $otherPlayer = Player::factory()->create([
            'user_id' => $otherUser->id,
            'galaxy_id' => $this->player->galaxy_id,
        ]);

        $notification = PlayerNotification::factory()->create([
            'player_id' => $otherPlayer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/{$notification->id}/read");

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_player()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/players/nonexistent-uuid/notifications');

        $response->assertStatus(404);
    }

    public function test_it_returns_404_for_nonexistent_notification()
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/players/{$this->player->uuid}/notifications/99999/read");

        $response->assertStatus(404);
    }
}
