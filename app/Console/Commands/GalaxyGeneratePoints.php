<?php

namespace App\Console\Commands;

use App\Support\PoissonDisk;
use Illuminate\Console\Command;

class GalaxyGeneratePoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:galaxy:generate-points
                            {--width=300    : Galaxy width}
                            {--height=300   : Galaxy height}
                            {--points=3000  : Number of points of interest}
                            {--spacing=0.75 : Spacing factor}
                            {--seed=43      : RNG seed}
                            {--floats       : return floats instead of the the snapped ints (True|False}}
                            {--format=csv   : Output format (csv|json)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate galaxy POIs using Poisson-disk sampling and dump them';


    /**
     * Execute the console command.
     */
    public function handle()
    {
        $disk = new PoissonDisk(
            (int)$this->option('width'),
            (int)$this->option('height'),
            (int)$this->option('points'),
            (float)$this->option('spacing'),
            (int)$this->option('seed'),
            ['returnFloats' => $this->option('floats')]
        );

        $result = $disk->sample();
        $points = $result['points'];
        $r = $result['r'];

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['r' => $r, 'points' => $points], JSON_PRETTY_PRINT));
        } else {
            // CSV output
            $this->line("x,y");
            foreach ($points as [$x, $y]) {
                $this->line("$x,$y");
            }
        }

        $this->info("Generated " . count($points) . " POIs (r = $r)");
        return 0;
    }
}
