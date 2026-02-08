<?php

namespace Tests\Unit\Services\GalaxyGeneration;

use App\Enums\Galaxy\GalaxySizeTier;
use App\Models\Galaxy;
use App\Services\GalaxyGeneration\Data\GenerationConfig;
use App\Services\GalaxyGeneration\Generators\MirrorUniverseGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MirrorUniverseGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private MirrorUniverseGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MirrorUniverseGenerator;
    }

    public function test_generator_has_correct_name(): void
    {
        $this->assertEquals('mirror_universe', $this->generator->getName());
    }

    public function test_generator_has_dependencies(): void
    {
        $dependencies = $this->generator->getDependencies();

        $this->assertContains(
            \App\Services\GalaxyGeneration\Generators\StarFieldGenerator::class,
            $dependencies
        );
        $this->assertContains(
            \App\Services\GalaxyGeneration\Generators\PrecursorContentGenerator::class,
            $dependencies
        );
    }

    public function test_skips_when_include_mirror_is_false(): void
    {
        $galaxy = Galaxy::factory()->create();

        $config = GenerationConfig::fromTier(GalaxySizeTier::SMALL, [
            'skip_mirror' => true,
        ]);

        $result = $this->generator->generate($galaxy, ['config' => $config]);

        $this->assertTrue($result->success);
        $this->assertNull($result->data['mirror_galaxy_id']);
        $this->assertEquals(
            'Mirror universe disabled via config',
            $result->metrics->getCustom('skipped')
        );
    }

    public function test_skips_when_mirror_already_exists(): void
    {
        $primeGalaxy = Galaxy::factory()->create();

        // Create existing mirror
        $mirrorGalaxy = Galaxy::factory()->create([
            'config' => [
                'is_mirror' => true,
                'prime_galaxy_id' => $primeGalaxy->id,
            ],
        ]);

        // Link prime to mirror
        $primeGalaxy->config = ['mirror_galaxy_id' => $mirrorGalaxy->id];
        $primeGalaxy->save();

        $config = GenerationConfig::fromTier(GalaxySizeTier::SMALL);
        $result = $this->generator->generate($primeGalaxy, ['config' => $config]);

        $this->assertTrue($result->success);
        $this->assertEquals($mirrorGalaxy->id, $result->data['mirror_galaxy_id']);
        $this->assertEquals('Mirror already exists', $result->metrics->getCustom('skipped'));
    }

    public function test_skips_when_disabled_in_game_config(): void
    {
        config(['game_config.mirror_universe.enabled' => false]);

        $galaxy = Galaxy::factory()->create();

        // Pass no config to trigger fallback check
        $result = $this->generator->generate($galaxy, []);

        $this->assertTrue($result->success);
        $this->assertNull($result->data['mirror_galaxy_id']);

        // Reset config
        config(['game_config.mirror_universe.enabled' => true]);
    }
}
