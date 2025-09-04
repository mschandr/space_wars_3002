<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Factories\PointGeneratorFactory;
use App\Contracts\PointGeneratorInterface;

class GalaxyGeneratePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:galaxy:generate-points
                            {--width=      : Override galaxy width}
                            {--height=     : Override galaxy height}
                            {--points=     : Override number of points of interest}
                            {--spacing=    : Override spacing factor}
                            {--seed=       : Override RNG seed}
                            {--format=json : Output format (csv|json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate galaxy POIs using the configured generator and dump them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Use the factory to instantiate the correct generator
        $generator = PointGeneratorFactory::make(
            width:   $this->option('width')   ? (int)$this->option('width')   : null,
            height:  $this->option('height')  ? (int)$this->option('height')  : null,
            count:   $this->option('points')  ? (int)$this->option('points')  : null,
            spacing: $this->option('spacing') ? (float)$this->option('spacing') : null,
            seed:    $this->option('seed')    ? (int)$this->option('seed')    : null,
        );

        $points = $generator->sample();

        if ($this->option('format') === 'csv') {
            $this->line("x,y");
            foreach ($points as [$x, $y]) {
                $this->line("$x,$y");
            }
        } else {
            $this->line(json_encode(['points' => $points], JSON_PRETTY_PRINT));
        }

        $this->info("Generated " . count($points) . " POIs using " . get_class($generator));
        return 0;
    }
}
