<?php

namespace Tests\Feature;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $player;
    private User $user;
    private PlayerShip $flagship;
    private PlayerShip $memberShip;
    private PointOfInterest $poi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->player = Player::factory()->create(['user_id' => $this->user->id]);
        $this->poi = PointOfInterest::factory()->create();

        $this->flagship = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->poi->id,
        ]);
        $this->memberShip = PlayerShip::factory()->create([
            'player_id' => $this->player->id,
            'current_poi_id' => $this->poi->id,
        ]);
    }

    /** @test */
    public function testCreateFlotilla_creates_new_flotilla(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla",
            [
                'flagship_ship_id' => $this->flagship->uuid,
                'name' => 'Test Flotilla',
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Flotilla created successfully');
        $response->assertJsonPath('flotilla.name', 'Test Flotilla');
    }

    /** @test */
    public function testCreateFlotilla_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla",
            []
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function testCreateFlotilla_rejects_if_player_already_has_flotilla(): void
    {
        Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Existing Flotilla',
        ]);

        $newShip = PlayerShip::factory()->create(['player_id' => $this->player->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla",
            [
                'flagship_ship_id' => $newShip->uuid,
                'name' => 'New Flotilla',
            ]
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function testGetFlotilla_returns_flotilla_status(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('name', 'Test Flotilla');
        $response->assertJsonPath('formation_stats.ship_count', 1);
    }

    /** @test */
    public function testGetFlotilla_returns_404_if_no_flotilla(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function testAddShip_adds_ship_to_flotilla(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla/add-ship",
            ['ship_id' => $this->memberShip->uuid]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('flotilla.formation_stats.ship_count', 2);
    }

    /** @test */
    public function testAddShip_validates_ship_id(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla/add-ship",
            ['ship_id' => 'invalid-uuid']
        );

        $response->assertStatus(422);
    }

    /** @test */
    public function testRemoveShip_removes_ship_from_flotilla(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);
        $this->memberShip->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla/remove-ship",
            ['ship_id' => $this->memberShip->uuid]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('flotilla.formation_stats.ship_count', 1);
    }

    /** @test */
    public function testRemoveShip_prevents_removing_flagship(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla/remove-ship",
            ['ship_id' => $this->flagship->uuid]
        );

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Cannot remove the flagship. Designate a new flagship first');
    }

    /** @test */
    public function testSetFlagship_changes_flagship(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);
        $this->memberShip->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->postJson(
            "/api/players/{$this->player->uuid}/flotilla/set-flagship",
            ['ship_id' => $this->memberShip->uuid]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('flotilla.flagship.id', $this->memberShip->id);
    }

    /** @test */
    public function testDissolveFlotilla_dissolves_flotilla(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);
        $this->memberShip->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(200);
        $response->assertJsonPath('message', "Flotilla 'Test Flotilla' dissolved. All ships are now independent.");

        $this->assertNull($this->flagship->refresh()->flotilla_id);
        $this->assertNull($this->memberShip->refresh()->flotilla_id);
        $this->assertFalse(Flotilla::where('id', $flotilla->id)->exists());
    }

    /** @test */
    public function testDissolveFlotilla_returns_404_if_no_flotilla(): void
    {
        $response = $this->actingAs($this->user)->deleteJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(404);
    }

    /** @test */
    public function testUnauthorized_user_cannot_modify_other_player_flotilla(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->getJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(403);
    }

    /** @test */
    public function testFlotilla_status_includes_all_required_fields(): void
    {
        $flotilla = Flotilla::create([
            'player_id' => $this->player->id,
            'flagship_ship_id' => $this->flagship->id,
            'name' => 'Test Flotilla',
        ]);
        $this->flagship->update(['flotilla_id' => $flotilla->id]);
        $this->memberShip->update(['flotilla_id' => $flotilla->id]);

        $response = $this->actingAs($this->user)->getJson(
            "/api/players/{$this->player->uuid}/flotilla"
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'uuid',
            'name',
            'flagship',
            'ships',
            'formation_stats',
            'location',
        ]);
    }
}
