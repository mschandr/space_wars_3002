<?php

namespace App\Console\Commands;

use App\Enums\Galaxy\GalaxyDistributionMethod;
use App\Enums\Galaxy\GalaxyRandomEngine;
use App\Generators\Points\HaltonSequence;
use App\Generators\Points\LatinHypercube;
use App\Generators\Points\PoissonDisk;
use App\Generators\Points\R2Sequence;
use App\Generators\Points\RandomScatter;
use App\Generators\Points\StratifiedGrid;
use App\Generators\Points\UniformRandom;
use App\Generators\Points\VogelsSpiral;
use App\Models\Galaxy;
use Illuminate\Console\Command;
use Random\RandomException;

class GalaxyGeneratePoints extends Command
{
    protected $signature = 'galaxy:generate-points
                            {--method=scatter : Distribution method (scatter, poisson, halton, vogel, stratified, latin, r2, uniform)}
                            {--width=1000 : Width of the galaxy map}
                            {--height=1000 : Height of the galaxy map}
                            {--count=50 : Number of points of interest}
                            {--seed=42 : RNG seed}
                            {--engine=mt19937 : RNG engine (mt19937, pcg, xoshiro)}
                            {--json : Output POIs as JSON for debug/visualization}';

    protected $description = 'Generate a galaxy with points of interest using a chosen distribution method';

    /**
     * @throws RandomException
     */
    public function handle(): int
    {
        $method = strtolower($this->option('method'));
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $count = (int) $this->option('count');
        $seed = (int) $this->option('seed');
        $engine = $this->option('engine');

        // Generate and persist galaxy
        $galaxy = Galaxy::createGalaxy([
            'width' => $width,
            'height' => $height,
            'seed' => $seed,
            'distribution_method' => GalaxyDistributionMethod::fromName($method),
            'engine' => GalaxyRandomEngine::fromName($engine),
        ]);

        // Pick generator
        $generator = match ($method) {
            'scatter' => new RandomScatter($width, $height, $count, 0.75, $seed, [], $engine),
            'poisson' => new PoissonDisk($width, $height, $count, 0.75, $seed, [], $engine),
            'halton' => new HaltonSequence($width, $height, $count, 0.75, $seed, [], $engine),
            'vogel' => new VogelsSpiral($width, $height, $count, 0.75, $seed, [], $engine),
            'stratified' => new StratifiedGrid($width, $height, $count, 0.75, $seed, [], $engine),
            'latin' => new LatinHypercube($width, $height, $count, 0.75, $seed, [], $engine),
            'r2' => new R2Sequence($width, $height, $count, 0.75, $seed, [], $engine),
            'uniform' => new UniformRandom($width, $height, $count, 0.75, $seed, [], $engine),
            default => throw new \InvalidArgumentException("Unknown method: {$method}"),
        };

        $points = $generator->sample($galaxy);

        if ($this->option('json')) {
            // Dump JSON for Vue debug
            $data = [
                'galaxy' => [
                    'id' => $galaxy->id,
                    'name' => $galaxy->name,
                    'width' => $galaxy->width,
                    'height' => $galaxy->height,
                ],
                'points' => $galaxy->pointsOfInterest()
                    ->get(['x', 'y', 'type', 'name', 'is_hidden', 'attributes'])
                    ->toArray(),
            ];
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
        } else {
            // CLI summary
            $this->info("✅ Created galaxy: {$galaxy->name}");
            $this->info("   Width: {$galaxy->width}, Height: {$galaxy->height}, Seed: {$galaxy->seed}");
            $this->info("   Distribution: {$galaxy->distribution_method->name}, Engine: {$galaxy->engine->name}");
            $this->info('   Points of Interest: '.$galaxy->pointsOfInterest()->count());
        }

        return Command::SUCCESS;
    }
}
