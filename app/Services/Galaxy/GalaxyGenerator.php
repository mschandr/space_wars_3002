<?php

namespace App\Services\Galaxy;

use App\Models\{Galaxy, Legacy\StarSystem, Market, MarketListing, Ore, Planet, Warp};
use Illuminate\Support\Collection;

final class GalaxyGenerator
{
    public function __construct(
        private array $config,       // 'galaxy' =>, 'ores' => see config/game_config.php
        private int   $seed
    ) {
    }

    public function generate(?string $name = null): Galaxy
    {
        mt_srand($this->seed);

        // Persist snapshot (array cast -> JSON in DB)
        $galaxyCfg = $this->config['galaxy'];
        $oresCfg   = $this->config['ores'];

        $galaxy = Galaxy::create([
            'name'   => $name ?: Galaxy::GenerateGalaxyName(),
            'seed'   => $this->seed,
            'width'  => (int)$galaxyCfg['width'],
            'height' => (int)$galaxyCfg['height'],
            'config' => ['galaxy' => $galaxyCfg, 'ores' => $oresCfg],
        ]);

        foreach ($oresCfg as $o) {
            Ore::updateOrCreate(
                ['key' => $o['key'] ],
                [
                    'name'                  => $o['name'],
                    'rarity'                => $o['rarity'],
                    'base_price'            => (int)$o['base_price'],
                    'origin_world_types'    => $o['origins'],
                ]
            );
        }

        $systems = $this->generateStarSystems($galaxy, $galaxyCfg);
        $this->generateWarps($systems, $galaxyCfg);
        $this->generatePlanets($systems, $galaxyCfg);
        $this->generateMarkets($systems, $galaxyCfg, $oresCfg);

        return $galaxy->fresh();
    }

    /** @return \Illuminate\Support\Collection<int,StarSystem> */
    private function generateStarSystems(Galaxy $galaxy, array $cfg): Collection
    {
        $count  = (int)$cfg['stars']['count'];
        $w      = (int)$cfg['width'];
        $h      = (int)$cfg['height'];

        $classWeights   = $cfg['star_classes'] ?? ['main_sequence'=>1];
        $classes        = array_keys($classWeights);
        $weights        = array_values($classWeights);
        $sum            = array_sum($weights);

        $coords = [];
        while (count($coords) < $count) {
            $x = mt_rand(0, $w-1); $y = mt_rand(0, $h-1);
            $coords["$x,$y"] = [$x,$y]; // ensures uniqueness
        }

        $systems = collect();
        foreach ($coords as [$x,$y]) {
            $starType = $this->weightedPick($classes, $weights, $sum);
            $systems->push(StarSystem::create([
                'galaxy_id'    => $galaxy->id,
                'x'            => $x,
                'y'            => $y,
                'star_type'    => $starType,
                'multiplicity' => mt_rand(1, (int)($cfg['stars']['max_multiplicity'] ?? 3)),
                'has_market'   => false,
            ]));
        }
        return $systems;
    }

    private function generateWarps(Collection $systems, array $cfg): void
    {
        // Prim MST for connectivity, then add local edges to hit degree targets
        $byId = $systems->keyBy('id');
        $ids  = $byId->keys()->all();
        $in   = [$ids[0]=>true]; $edges=[];

        while (count($in) < count($ids)) {
            $best = null; $A=null; $B=null;
            foreach ($in as $aid=>$_) {
                $sa = $byId[$aid];
                foreach ($ids as $bid) {
                    if (isset($in[$bid])) continue;
                    $sb = $byId[$bid];
                    $d = ($sa->x - $sb->x)**2 + ($sa->y - $sb->y)**2;
                    if ($best===null || $d<$best) { $best=$d; $A=$aid; $B=$bid; }
                }
            }
            $in[$B]=true; $edges[]=[$A,$B];
        }
        foreach ($edges as [$a,$b]) {
            Warp::firstOrCreate(['from_system_id'=>$a,'to_system_id'=>$b]);
            Warp::firstOrCreate(['from_system_id'=>$b,'to_system_id'=>$a]);
        }

        $minDeg = (int)($cfg['stars']['min_degree'] ?? 2);
        $maxDeg = (int)($cfg['stars']['max_degree'] ?? 4);
        $deg = fn($id)=>Warp::where('from_system_id',$id)->count();

        foreach ($systems as $s) {
            while ($deg($s->id) < $minDeg) {
                $n = $this->nearestNeighborNotLinked($s, $systems, 30);
                if (!$n) break;
                Warp::firstOrCreate(['from_system_id'=>$s->id,'to_system_id'=>$n->id]);
                Warp::firstOrCreate(['from_system_id'=>$n->id,'to_system_id'=>$s->id]);
            }
        }
    }

    private function generatePlanets(Collection $systems, array $cfg): void
    {
        $prob = (float)($cfg['stars']['system_probability'] ?? 0.8);
        $weights = $cfg['world_weights'] ?? [];

        foreach ($systems as $s) {
            if (mt_rand()/mt_getrandmax() > $prob) continue;
            $n = mt_rand(1, 6);
            for ($i=0; $i<$n; $i++) {
                $ww = $weights[$s->star_type] ?? ['hot'=>1,'mild'=>1,'cold'=>1];
                $wt = $this->weightedPick(array_keys($ww), array_values($ww), array_sum($ww));
                Planet::create([
                    'star_system_id' => $s->id,
                    'name'           => "P-{$s->id}-".($i+1),
                    'world_type'     => $wt,
                    'size_class'     => ['tiny','small','med','large'][mt_rand(0,3)],
                    'habitability'   => mt_rand(10,90),
                ]);
            }
        }
    }


    private function generateMarkets(Collection $systems, array $cfg, array $oresCfg): void
    {
        $ratio = (float)($cfg['markets']['station_ratio'] ?? 0.3);
        $listF = (float)($cfg['markets']['listed_ore_fraction'] ?? 0.5);
        $ores  = Ore::all();

        foreach ($systems as $s) {
            if (mt_rand()/mt_getrandmax() > $ratio) continue;
            $mkt = Market::create(['star_system_id'=>$s->id]);
            $s->update(['has_market'=>true]);

            $subset = $ores->shuffle()->take(max(1,(int)round($ores->count()*$listF)));
            foreach ($subset as $ore) {
                $stock = match($ore->rarity) {
                'common' => mt_rand(400,1200),
                    'uncommon' => mt_rand(150,500),
                    'rare' => mt_rand(30,120),
                    default => mt_rand(10,40),
            };
                $price = $this->priceForStock($ore->base_price, $stock, $ore->rarity);
                MarketListing::create([
                    'market_id'=>$mkt->id,'ore_id'=>$ore->id,
                    'stock'=>$stock,'price'=>$price,'last_tick_at'=>now()
                ]);
            }
        }
    }

    // ---------- helpers ----------

    private function weightedPick(array $items, array $weights, int|float $sum): string
    {
        $r = mt_rand(1, (int)($sum*1000)) / 1000;
        $acc=0;
        foreach ($items as $i=>$item) {
            $acc+=$weights[$i];
            if ($r <= $acc) {
                return $item;
            }
        }
        return end($items);
    }

    private function nearestNeighborNotLinked(StarSystem $a, Collection $systems, int $maxDist): ?StarSystem
    {
        $best=null; $bestD=PHP_INT_MAX;
        foreach ($systems as $b) {
            if ($a->id === $b->id) continue;
            $d2 = ($a->x-$b->x)**2 + ($a->y-$b->y)**2;
            if ($d2 >= $bestD || $d2 > $maxDist*$maxDist) continue;
            $exists = Warp::where('from_system_id',$a->id)->where('to_system_id',$b->id)->exists();
            if ($exists) continue;
            $bestD=$d2; $best=$b;
        }
        return $best;
    }

    private function priceForStock(int $base, int $stock, string $rarity): int
    {
        $k = match($rarity){ 'common'=>200, 'uncommon'=>120, 'rare'=>60, default=>30 };
        $p = $base * ($k+1)/($stock+$k);
        return max((int)round($base*0.3), min((int)round($p), (int)round($base*5)));
    }
}
