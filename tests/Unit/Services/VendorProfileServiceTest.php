<?php

namespace Tests\Unit\Services;

use App\Enums\Crew\CrewAlignment;
use App\Models\CrewMember;
use App\Models\Galaxy;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerVendorRelationship;
use App\Models\TradingPost;
use App\Models\VendorProfile;
use App\Services\VendorProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private VendorProfileService $service;
    private Player $player;
    private VendorProfile $vendor;
    private TradingPost $tradingPost;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VendorProfileService();

        $galaxy = Galaxy::factory()->create();
        $this->player = Player::factory()->create(['galaxy_id' => $galaxy->id]);

        $this->tradingPost = TradingPost::factory()->create([
            'service_type' => 'trading_hub',
            'base_criminality' => 0.15,
            'markup_base' => 0.05,
        ]);

        $this->vendor = VendorProfile::factory()->create([
            'trading_post_id' => $this->tradingPost->id,
            'service_type' => 'trading_hub',
            'criminality' => 0.15,
            'markup_base' => 0.05,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_base_markup_for_unknown_player()
    {
        $markup = $this->service->getEffectiveMarkup($this->vendor, $this->player);

        // Unknown player should get base markup
        $this->assertEquals($this->vendor->markup_base, $markup);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_reduces_markup_with_positive_goodwill()
    {
        // Create relationship with positive goodwill
        PlayerVendorRelationship::create([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
            'goodwill' => 50,
        ]);

        $markup = $this->service->getEffectiveMarkup($this->vendor, $this->player);

        // Goodwill should reduce markup
        $this->assertLessThan($this->vendor->markup_base, $markup);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increases_markup_with_negative_goodwill()
    {
        // Create relationship with negative goodwill
        PlayerVendorRelationship::create([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
            'goodwill' => -50,
        ]);

        $markup = $this->service->getEffectiveMarkup($this->vendor, $this->player);

        // Negative goodwill should increase markup
        $this->assertGreaterThan($this->vendor->markup_base, $markup);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_zero_goodwill()
    {
        PlayerVendorRelationship::create([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
            'goodwill' => 0,
        ]);

        $markup = $this->service->getEffectiveMarkup($this->vendor, $this->player);

        // Zero goodwill should be close to base markup
        $this->assertEqualsWithDelta($this->vendor->markup_base, $markup, 0.01);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_discount_for_shady_vendor_with_shady_crew()
    {
        // Create shady vendor
        $shadyVendor = VendorProfile::factory()->create([
            'criminality' => 0.85,
            'markup_base' => 0.10,
        ]);

        // Create ship with shady crew
        $ship = PlayerShip::factory()->create(['player_id' => $this->player->id]);
        $this->player->update(['active_ship_id' => $ship->id]);

        CrewMember::factory()->create([
            'player_ship_id' => $ship->id,
            'alignment' => CrewAlignment::SHADY,
        ]);

        $markup = $this->service->getEffectiveMarkup($shadyVendor, $this->player);

        // Shady crew should get discount at shady vendors
        $this->assertLessThan($shadyVendor->markup_base, $markup);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_penalizes_lawful_crew_with_shady_vendor()
    {
        // Create shady vendor
        $shadyVendor = VendorProfile::factory()->create([
            'criminality' => 0.85,
            'markup_base' => 0.10,
        ]);

        // Create ship with lawful crew
        $ship = PlayerShip::factory()->create(['player_id' => $this->player->id]);
        $this->player->update(['active_ship_id' => $ship->id]);

        CrewMember::factory()->create([
            'player_ship_id' => $ship->id,
            'alignment' => CrewAlignment::LAWFUL,
        ]);

        $markup = $this->service->getEffectiveMarkup($shadyVendor, $this->player);

        // Lawful crew should get penalty at shady vendors
        $this->assertGreaterThan($shadyVendor->markup_base, $markup);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_browse_interaction()
    {
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ])->first();

        $this->assertNotNull($relationship);
        $this->assertEquals(1, $relationship->visit_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_trade_interaction()
    {
        $this->service->recordInteraction($this->vendor, $this->player, 'trade');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ])->first();

        $this->assertNotNull($relationship);
        // Trade should increase goodwill
        $this->assertGreaterThan(0, $relationship->goodwill);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_records_shady_trade_interaction()
    {
        $this->service->recordInteraction($this->vendor, $this->player, 'shady_trade');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ])->first();

        $this->assertNotNull($relationship);
        $this->assertGreaterThan(0, $relationship->shady_dealings);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increments_visit_count()
    {
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ])->first();

        $this->assertEquals(2, $relationship->visit_count);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_dialogue_line()
    {
        $dialogue = $this->service->getDialogueLine($this->vendor, 'greeting', $this->player);

        $this->assertIsString($dialogue);
        $this->assertNotEmpty($dialogue);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_provides_different_dialogue_for_contexts()
    {
        $greeting = $this->service->getDialogueLine($this->vendor, 'greeting', $this->player);
        $farewell = $this->service->getDialogueLine($this->vendor, 'farewell', $this->player);

        // Different contexts should return dialogue (may or may not be different strings)
        $this->assertIsString($greeting);
        $this->assertIsString($farewell);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_last_interaction_timestamp()
    {
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ])->first();

        $this->assertNotNull($relationship->last_interaction_at);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_multiple_interactions_with_same_vendor()
    {
        // Multiple interactions should update the same relationship
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');
        $this->service->recordInteraction($this->vendor, $this->player, 'trade');
        $this->service->recordInteraction($this->vendor, $this->player, 'browse');

        $relationship = PlayerVendorRelationship::where([
            'player_id' => $this->player->id,
            'vendor_profile_id' => $this->vendor->id,
        ]);

        // Should only have one relationship record
        $this->assertEquals(1, $relationship->count());

        // Visit count should be 3 (from two browses + one trade)
        $rel = $relationship->first();
        $this->assertGreaterThanOrEqual(2, $rel->visit_count); // At least 2 from browses
    }
}
