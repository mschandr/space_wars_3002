<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Galaxy\GalaxyGenerator;
use App\Models\Galaxy;
use Illuminate\Contracts\Container\BindingResolutionException;
use Random\RandomException;

class GalaxyGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'galaxy:generate
                            {--name=}
                            {--seed=}
                            {--height=}
                            {--width=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new galaxy from config/game_config.php';

    /**
     * Execute the console command.
     * @throws BindingResolutionException
     * @throws RandomException
     */
    public function handle(): int
    {
        $cfgGalaxy  = config('game_config.galaxy');
        $cfgOres    = config('game_config.ores');

        if (!$cfgGalaxy || !$cfgOres) {
            $this->error('Missing required keys in config/game_config.php (galaxy, ores).');
            return self::FAILURE;
        }


        $w = (int)($this->option('width')   ?? $cfgGalaxy['width']);
        $h = (int)($this->option('height')  ?? $cfgGalaxy['height']);

        $seed = (int)($this->option('seed') ?? random_int(1, PHP_INT_MAX));
        $name = $this->option('name');

        $generator = app()->makeWith(GalaxyGenerator::class, [
            'config' => [
                'galaxy'    => $cfgGalaxy,
                'ores'      => $cfgOres,
            ],
            'seed' => $seed,
        ]);

        $galaxy = $generator->generate($name);

        $this->info("✔ Galaxy {$galaxy->name} [id {$galaxy->id}] generated (seed {$galaxy->seed})");
        $this->line("Systems: " . $galaxy->star_systems()->count());
        $this->line("Warps:   " . $galaxy->warps()->count());
        $this->line("Markets: " . $galaxy->markets()->count());
        return self::SUCCESS;
    }

}
