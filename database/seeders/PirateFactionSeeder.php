<?php

namespace Database\Seeders;

use App\Models\Galaxy;
use App\Models\PirateFaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PirateFactionSeeder extends Seeder
{
    public array $factions;

    public function run(): void
    {
        $this->factions = [
            [
                'name' => 'Crimson Raiders',
                'description' => 'A ruthless band of opportunists known for their blood-red ships and aggressive tactics. They strike fast and disappear into asteroid fields.',
                'attributes' => ['aggression' => 'high', 'preferred_targets' => 'merchants', 'base_region' => 'outer_rim'],
            ],
            [
                'name' => 'Black Star Syndicate',
                'description' => 'An organized crime network that controls smuggling routes across multiple sectors. They prefer negotiation over combat, but are deadly when crossed.',
                'attributes' => ['aggression' => 'medium', 'preferred_targets' => 'all', 'base_region' => 'trade_lanes'],
            ],
            [
                'name' => 'Void Corsairs',
                'description' => 'Former military officers turned pirates, they maintain strict discipline and tactical superiority. Their ambushes are legendary.',
                'attributes' => ['aggression' => 'high', 'preferred_targets' => 'military', 'base_region' => 'deep_space'],
            ],
            [
                'name' => 'Shadow Reavers',
                'description' => 'Ghost-like pirates who specialize in stealth technology and surprise attacks. They emerge from nebulae without warning.',
                'attributes' => ['aggression' => 'medium', 'preferred_targets' => 'explorers', 'base_region' => 'nebula_zones'],
            ],
            [
                'name' => 'Iron Skulls',
                'description' => 'Brutal enforcers who believe in overwhelming firepower. Their heavily armored ships are nearly indestructible.',
                'attributes' => ['aggression' => 'very_high', 'preferred_targets' => 'anyone', 'base_region' => 'core_worlds'],
            ],
            [
                'name' => 'Nebula Marauders',
                'description' => 'Nomadic raiders who live entirely in space, using cosmic phenomena as cover for their operations.',
                'attributes' => ['aggression' => 'medium', 'preferred_targets' => 'merchants', 'base_region' => 'nebulae'],
            ],
            [
                'name' => 'Stellar Outcasts',
                'description' => 'Exiles and refugees who turned to piracy out of desperation. Less organized but unpredictable.',
                'attributes' => ['aggression' => 'low', 'preferred_targets' => 'weak_ships', 'base_region' => 'frontier'],
            ],
            [
                'name' => 'Red Comet Legion',
                'description' => 'Speed-obsessed pirates who pilot the fastest ships in the galaxy. They believe no one can catch them.',
                'attributes' => ['aggression' => 'medium', 'preferred_targets' => 'cargo_haulers', 'base_region' => 'trade_routes'],
            ],
            [
                'name' => 'Dark Matter Cartel',
                'description' => 'A shadowy organization dealing in illegal technology and weapons. Their ships are equipped with experimental systems.',
                'attributes' => ['aggression' => 'medium', 'preferred_targets' => 'tech_ships', 'base_region' => 'black_markets'],
            ],
            [
                'name' => 'Scorpion Fleet',
                'description' => 'Tactical specialists who set elaborate traps along warp lanes. They strike with precision and retreat quickly.',
                'attributes' => ['aggression' => 'high', 'preferred_targets' => 'all', 'base_region' => 'warp_junctions'],
            ],
            [
                'name' => 'Phantom Blades',
                'description' => 'Elite assassins-for-hire who also engage in piracy. They value reputation over profit.',
                'attributes' => ['aggression' => 'high', 'preferred_targets' => 'high_value', 'base_region' => 'inner_systems'],
            ],
            [
                'name' => 'Blood Moon Collective',
                'description' => 'Cult-like pirates who believe they are destined to rule the stars. Fanatical and fearless in combat.',
                'attributes' => ['aggression' => 'very_high', 'preferred_targets' => 'everyone', 'base_region' => 'dark_sectors'],
            ],
        ];
    }

    public function generatePirateFactions(Galaxy $galaxy, $command = null)
    {
        foreach ($this->factions as $factionData) {
            PirateFaction::create([
                'uuid' => Str::uuid(),
                'galaxy_id' => $galaxy->id,
                'name' => $factionData['name'],
                'description' => $factionData['description'],
                'attributes' => $factionData['attributes'],
                'is_active' => true,
            ]);
        }

        if ($command) {
            $command->info('Created '.count($this->factions).' pirate factions');
        }
    }
}
