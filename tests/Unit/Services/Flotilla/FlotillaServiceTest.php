<?php

namespace Tests\Unit\Services\Flotilla;

use App\Models\Flotilla;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PointOfInterest;
use App\Services\Flotilla\FlotillaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlotillaServiceTest extends TestCase
{
    use RefreshDatabase;

    private FlotillaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FlotillaService::class);
    }

    /** @test */
    public function testCreateFlotilla_creates_new_flotilla_with_flagship(): void
    {
        $player = Player::factory()->create();
        $ship = PlayerShip::factory()->create(['player_id' => $player->id]);

        $flotilla = $this->service->createFlotilla($player, $ship, 'Test Flotilla');

        $this->assertNotNull($flotilla);
        $this->assertEquals('Test Flotilla', $flotilla->name);
        $this->assertEquals($player->id, $flotilla->player_id);
        $this->assertEquals($ship->id, $flotilla->flagship_ship_id);
        $this->assertTrue($ship->refresh()->isInFlotilla());
    }

    /** @test */
    public function testCreateFlotilla_throws_exception_if_ship_not_owned_by_player(): void
    {
        $player1 = Player::factory()->create();
        $player2 = Player::factory()->create();
        $ship = PlayerShip::factory()->create(['player_id' => $player2->id]);

        $this->expectException(\Exception::class);
        $this->service->createFlotilla($player1, $ship);
    }

    /** @test */
    public function testAddShipToFlotilla_adds_ship_to_existing_flotilla(): void
    {
        $player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create(['player_id' => $player->id]);
        $newShip = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $flagship->current_poi_id]);

        $flotilla = $this->service->createFlotilla($player, $flagship);
        $this->service->addShipToFlotilla($flotilla, $newShip);

        $this->assertTrue($newShip->refresh()->isInFlotilla());
        $this->assertEquals($flotilla->id, $newShip->flotilla_id);
        $this->assertEquals(2, $flotilla->refresh()->shipCount());
    }

    /** @test */
    public function testAddShipToFlotilla_throws_exception_if_flotilla_full(): void
    {
        $player = Player::factory()->create();
        $ships = PlayerShip::factory(4)->create(['player_id' => $player->id]);

        $flotilla = $this->service->createFlotilla($player, $ships[0]);
        for ($i = 1; $i < 4; $i++) {
            $ships[$i]->update(['current_poi_id' => $ships[0]->current_poi_id]);
            $this->service->addShipToFlotilla($flotilla, $ships[$i]);
        }

        $extraShip = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $ships[0]->current_poi_id]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('maximum capacity');
        $this->service->addShipToFlotilla($flotilla, $extraShip);
    }

    /** @test */
    public function testAddShipToFlotilla_throws_exception_if_ship_not_at_same_location(): void
    {
        $player = Player::factory()->create();
        $poi1 = PointOfInterest::factory()->create();
        $poi2 = PointOfInterest::factory()->create();

        $flagship = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $poi1->id]);
        $otherShip = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $poi2->id]); // Different location

        $flotilla = $this->service->createFlotilla($player, $flagship);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('same location');
        $this->service->addShipToFlotilla($flotilla, $otherShip);
    }

    /** @test */
    public function testRemoveShipFromFlotilla_removes_ship_from_flotilla(): void
    {
        $player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create(['player_id' => $player->id]);
        $memberShip = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $flagship->current_poi_id]);

        $flotilla = $this->service->createFlotilla($player, $flagship);
        $this->service->addShipToFlotilla($flotilla, $memberShip);

        $this->service->removeShipFromFlotilla($flotilla, $memberShip);

        $this->assertNull($memberShip->refresh()->flotilla_id);
        $this->assertEquals(1, $flotilla->refresh()->shipCount());
    }

    /** @test */
    public function testRemoveShipFromFlotilla_throws_exception_if_removing_flagship(): void
    {
        $player = Player::factory()->create();
        $flagship = PlayerShip::factory()->create(['player_id' => $player->id]);

        $flotilla = $this->service->createFlotilla($player, $flagship);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot remove the flagship');
        $this->service->removeShipFromFlotilla($flotilla, $flagship);
    }

    /** @test */
    public function testSetFlagship_changes_flagship_designation(): void
    {
        $player = Player::factory()->create();
        $oldFlagship = PlayerShip::factory()->create(['player_id' => $player->id]);
        $newFlagship = PlayerShip::factory()->create(['player_id' => $player->id, 'current_poi_id' => $oldFlagship->current_poi_id]);

        $flotilla = $this->service->createFlotilla($player, $oldFlagship);
        $this->service->addShipToFlotilla($flotilla, $newFlagship);
        $this->service->setFlagship($flotilla, $newFlagship);

        $this->assertEquals($newFlagship->id, $flotilla->refresh()->flagship_ship_id);
    }
}
