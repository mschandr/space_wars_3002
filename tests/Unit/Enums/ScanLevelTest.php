<?php

namespace Tests\Unit\Enums;

use App\Enums\Exploration\ScanLevel;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ScanLevel Enum
 */
class ScanLevelTest extends TestCase
{
    // =========================================================================
    // Basic Enum Tests
    // =========================================================================

    public function test_all_scan_levels_exist(): void
    {
        $this->assertEquals(0, ScanLevel::UNSCANNED->value);
        $this->assertEquals(1, ScanLevel::GEOGRAPHY->value);
        $this->assertEquals(2, ScanLevel::GATES->value);
        $this->assertEquals(3, ScanLevel::BASIC_RESOURCES->value);
        $this->assertEquals(4, ScanLevel::RARE_RESOURCES->value);
        $this->assertEquals(5, ScanLevel::HIDDEN_FEATURES->value);
        $this->assertEquals(6, ScanLevel::ANOMALIES->value);
        $this->assertEquals(7, ScanLevel::DEEP_SCAN->value);
        $this->assertEquals(8, ScanLevel::ADVANCED_INTEL->value);
        $this->assertEquals(9, ScanLevel::PRECURSOR_SECRETS->value);
    }

    public function test_precursor_level_constant(): void
    {
        $this->assertEquals(100, ScanLevel::PRECURSOR_LEVEL);
    }

    // =========================================================================
    // label() Tests
    // =========================================================================

    public function test_labels_are_human_readable(): void
    {
        $this->assertEquals('Unscanned', ScanLevel::UNSCANNED->label());
        $this->assertEquals('Basic Geography', ScanLevel::GEOGRAPHY->label());
        $this->assertEquals('Gate Detection', ScanLevel::GATES->label());
        $this->assertEquals('Basic Resources', ScanLevel::BASIC_RESOURCES->label());
        $this->assertEquals('Rare Resources', ScanLevel::RARE_RESOURCES->label());
        $this->assertEquals('Hidden Features', ScanLevel::HIDDEN_FEATURES->label());
        $this->assertEquals('Anomaly Detection', ScanLevel::ANOMALIES->label());
        $this->assertEquals('Deep Scan', ScanLevel::DEEP_SCAN->label());
        $this->assertEquals('Advanced Intel', ScanLevel::ADVANCED_INTEL->label());
        $this->assertEquals('Precursor Secrets', ScanLevel::PRECURSOR_SECRETS->label());
    }

    // =========================================================================
    // description() Tests
    // =========================================================================

    public function test_descriptions_are_informative(): void
    {
        $this->assertStringContainsString('Planet count', ScanLevel::GEOGRAPHY->description());
        $this->assertStringContainsString('Gate presence', ScanLevel::GATES->description());
        $this->assertStringContainsString('Mineral deposits', ScanLevel::BASIC_RESOURCES->description());
        $this->assertStringContainsString('Asteroid field', ScanLevel::RARE_RESOURCES->description());
        $this->assertStringContainsString('Habitable moons', ScanLevel::HIDDEN_FEATURES->description());
        $this->assertStringContainsString('Ancient ruins', ScanLevel::ANOMALIES->description());
        $this->assertStringContainsString('Subsurface', ScanLevel::DEEP_SCAN->description());
        $this->assertStringContainsString('Pirate hideouts', ScanLevel::ADVANCED_INTEL->description());
        $this->assertStringContainsString('precursor', ScanLevel::PRECURSOR_SECRETS->description());
    }

    // =========================================================================
    // reveals() Tests
    // =========================================================================

    public function test_reveals_returns_correct_categories(): void
    {
        $this->assertEmpty(ScanLevel::UNSCANNED->reveals());

        $geographyReveals = ScanLevel::GEOGRAPHY->reveals();
        $this->assertContains('geography', $geographyReveals);
        $this->assertContains('planet_count', $geographyReveals);
        $this->assertContains('planet_types', $geographyReveals);
        $this->assertContains('habitability_basic', $geographyReveals);

        $gateReveals = ScanLevel::GATES->reveals();
        $this->assertContains('gates_presence', $gateReveals);
        $this->assertContains('gate_status', $gateReveals);

        $precursorReveals = ScanLevel::PRECURSOR_SECRETS->reveals();
        $this->assertContains('precursor_gates', $precursorReveals);
        $this->assertContains('precursor_tech', $precursorReveals);
        $this->assertContains('ancient_secrets', $precursorReveals);
    }

    // =========================================================================
    // color() Tests
    // =========================================================================

    public function test_colors_are_valid_hex(): void
    {
        foreach (ScanLevel::cases() as $level) {
            $color = $level->color();
            $this->assertMatchesRegularExpression('/^#[0-9a-fA-F]{6}$/', $color);
        }
    }

    public function test_colors_progress_from_dark_to_bright(): void
    {
        // Unscanned should be darkest
        $this->assertEquals('#1a1a2e', ScanLevel::UNSCANNED->color());

        // Precursor should be brightest (orange)
        $this->assertEquals('#ff6600', ScanLevel::PRECURSOR_SECRETS->color());
    }

    // =========================================================================
    // opacity() Tests
    // =========================================================================

    public function test_opacities_are_in_valid_range(): void
    {
        foreach (ScanLevel::cases() as $level) {
            $opacity = $level->opacity();
            $this->assertGreaterThanOrEqual(0.0, $opacity);
            $this->assertLessThanOrEqual(1.0, $opacity);
        }
    }

    public function test_opacities_progress_from_low_to_high(): void
    {
        $this->assertEquals(0.2, ScanLevel::UNSCANNED->opacity());
        $this->assertEquals(1.0, ScanLevel::PRECURSOR_SECRETS->opacity());

        // Mid-levels should be in between
        $this->assertGreaterThan(0.2, ScanLevel::BASIC_RESOURCES->opacity());
        $this->assertLessThan(1.0, ScanLevel::BASIC_RESOURCES->opacity());
    }

    // =========================================================================
    // requiredSensorLevel() Tests
    // =========================================================================

    public function test_required_sensor_level_matches_value(): void
    {
        foreach (ScanLevel::cases() as $level) {
            $this->assertEquals($level->value, $level->requiredSensorLevel());
        }
    }

    // =========================================================================
    // canAchieveWith() Tests
    // =========================================================================

    public function test_can_achieve_with_exact_level(): void
    {
        $this->assertTrue(ScanLevel::GEOGRAPHY->canAchieveWith(1));
        $this->assertTrue(ScanLevel::BASIC_RESOURCES->canAchieveWith(3));
        $this->assertTrue(ScanLevel::PRECURSOR_SECRETS->canAchieveWith(9));
    }

    public function test_can_achieve_with_higher_level(): void
    {
        $this->assertTrue(ScanLevel::GEOGRAPHY->canAchieveWith(5));
        $this->assertTrue(ScanLevel::BASIC_RESOURCES->canAchieveWith(9));
    }

    public function test_cannot_achieve_with_lower_level(): void
    {
        $this->assertFalse(ScanLevel::BASIC_RESOURCES->canAchieveWith(2));
        $this->assertFalse(ScanLevel::PRECURSOR_SECRETS->canAchieveWith(8));
    }

    // =========================================================================
    // allRevealedCategories() Tests
    // =========================================================================

    public function test_all_revealed_categories_accumulates(): void
    {
        $level1 = ScanLevel::GEOGRAPHY->allRevealedCategories();
        $this->assertContains('geography', $level1);
        $this->assertNotContains('gates_presence', $level1);

        $level3 = ScanLevel::BASIC_RESOURCES->allRevealedCategories();
        $this->assertContains('geography', $level3);
        $this->assertContains('gates_presence', $level3);
        $this->assertContains('minerals_basic', $level3);
        $this->assertNotContains('minerals_rare', $level3);

        $level9 = ScanLevel::PRECURSOR_SECRETS->allRevealedCategories();
        $this->assertContains('geography', $level9);
        $this->assertContains('gates_presence', $level9);
        $this->assertContains('minerals_basic', $level9);
        $this->assertContains('minerals_rare', $level9);
        $this->assertContains('hidden_moons', $level9);
        $this->assertContains('anomalies', $level9);
        $this->assertContains('deep_scan', $level9);
        $this->assertContains('intel', $level9);
        $this->assertContains('precursor_gates', $level9);
    }

    public function test_all_revealed_categories_has_no_duplicates(): void
    {
        $categories = ScanLevel::PRECURSOR_SECRETS->allRevealedCategories();
        $unique = array_unique($categories);

        $this->assertCount(count($unique), $categories);
    }

    // =========================================================================
    // next() Tests
    // =========================================================================

    public function test_next_returns_correct_level(): void
    {
        $this->assertEquals(ScanLevel::GEOGRAPHY, ScanLevel::UNSCANNED->next());
        $this->assertEquals(ScanLevel::GATES, ScanLevel::GEOGRAPHY->next());
        $this->assertEquals(ScanLevel::BASIC_RESOURCES, ScanLevel::GATES->next());
        $this->assertEquals(ScanLevel::RARE_RESOURCES, ScanLevel::BASIC_RESOURCES->next());
        $this->assertEquals(ScanLevel::HIDDEN_FEATURES, ScanLevel::RARE_RESOURCES->next());
        $this->assertEquals(ScanLevel::ANOMALIES, ScanLevel::HIDDEN_FEATURES->next());
        $this->assertEquals(ScanLevel::DEEP_SCAN, ScanLevel::ANOMALIES->next());
        $this->assertEquals(ScanLevel::ADVANCED_INTEL, ScanLevel::DEEP_SCAN->next());
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::ADVANCED_INTEL->next());
    }

    public function test_next_returns_null_at_max(): void
    {
        $this->assertNull(ScanLevel::PRECURSOR_SECRETS->next());
    }

    // =========================================================================
    // canReveal() Tests
    // =========================================================================

    public function test_can_reveal_checks_accumulated_categories(): void
    {
        $this->assertTrue(ScanLevel::GEOGRAPHY->canReveal('geography'));
        $this->assertFalse(ScanLevel::GEOGRAPHY->canReveal('gates_presence'));

        $this->assertTrue(ScanLevel::BASIC_RESOURCES->canReveal('geography'));
        $this->assertTrue(ScanLevel::BASIC_RESOURCES->canReveal('gates_presence'));
        $this->assertTrue(ScanLevel::BASIC_RESOURCES->canReveal('minerals_basic'));
        $this->assertFalse(ScanLevel::BASIC_RESOURCES->canReveal('minerals_rare'));
    }

    // =========================================================================
    // fromSensorLevel() Tests
    // =========================================================================

    public function test_from_sensor_level_exact(): void
    {
        $this->assertEquals(ScanLevel::UNSCANNED, ScanLevel::fromSensorLevel(0));
        $this->assertEquals(ScanLevel::GEOGRAPHY, ScanLevel::fromSensorLevel(1));
        $this->assertEquals(ScanLevel::GATES, ScanLevel::fromSensorLevel(2));
        $this->assertEquals(ScanLevel::BASIC_RESOURCES, ScanLevel::fromSensorLevel(3));
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(9));
    }

    public function test_from_sensor_level_clamps_negative(): void
    {
        $this->assertEquals(ScanLevel::UNSCANNED, ScanLevel::fromSensorLevel(-1));
        $this->assertEquals(ScanLevel::UNSCANNED, ScanLevel::fromSensorLevel(-100));
    }

    public function test_from_sensor_level_clamps_above_max(): void
    {
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(10));
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(100));
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::fromSensorLevel(ScanLevel::PRECURSOR_LEVEL));
    }

    // =========================================================================
    // max() Tests
    // =========================================================================

    public function test_max_returns_precursor_secrets(): void
    {
        $this->assertEquals(ScanLevel::PRECURSOR_SECRETS, ScanLevel::max());
        $this->assertEquals(9, ScanLevel::max()->value);
    }
}
