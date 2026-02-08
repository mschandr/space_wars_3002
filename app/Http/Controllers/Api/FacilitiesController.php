<?php

namespace App\Http\Controllers\Api;

use App\Enums\PointsOfInterest\PointOfInterestType;
use App\Http\Controllers\Api\Builders\BarNameGenerator;
use App\Http\Controllers\Api\Builders\ParentStarResolver;
use App\Models\Player;
use App\Models\PointOfInterest;
use App\Services\BarRumorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Facilities Controller
 *
 * Provides a unified view of all facilities available in a star system.
 * Facilities include trading hubs, shipyards, salvage yards, cartographers, and bars.
 */
class FacilitiesController extends BaseApiController
{
    public function __construct(
        protected BarRumorService $barRumorService
    ) {}

    /**
     * List all facilities available in the player's current star system.
     *
     * GET /api/players/{playerUuid}/facilities
     *
     * Returns a categorized list of all accessible facilities with their
     * details and available actions.
     */
    public function index(string $playerUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $playerUuid)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $currentLocation = $player->currentLocation;

        if (! $currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION', null, 400);
        }

        // Get the parent star system
        $system = ParentStarResolver::resolve($currentLocation);

        if (! $system) {
            return $this->error('Could not determine star system', 'NO_SYSTEM', null, 400);
        }

        // Load system with relationships
        $system = PointOfInterest::where('id', $system->id)
            ->with(['tradingHub', 'children'])
            ->first();

        // Build facilities response
        $facilities = $this->buildFacilitiesResponse($system, $player);

        return $this->success([
            'system' => [
                'uuid' => $system->uuid,
                'name' => $system->name,
                'is_inhabited' => $system->is_inhabited,
            ],
            'facilities' => $facilities,
        ], 'Facilities retrieved');
    }

    /**
     * Get bar information and current rumors.
     *
     * GET /api/players/{playerUuid}/facilities/bar
     */
    public function bar(string $playerUuid, Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return $this->unauthorized();
        }

        $player = Player::where('uuid', $playerUuid)
            ->with(['activeShip', 'currentLocation'])
            ->first();

        if (! $player) {
            return $this->notFound('Player not found');
        }

        $this->authorizePlayer($player, $user);

        $currentLocation = $player->currentLocation;
        if (! $currentLocation) {
            return $this->error('Player has no current location', 'NO_LOCATION', null, 400);
        }

        $system = ParentStarResolver::resolve($currentLocation);

        if (! $system || ! $system->is_inhabited) {
            return $this->error('No bar available in this system', 'NO_BAR', null, 400);
        }

        // Generate bar data
        $barData = $this->buildBarData($system, $player);

        return $this->success($barData, 'Welcome to the bar');
    }

    /**
     * Build the complete facilities response.
     */
    protected function buildFacilitiesResponse(PointOfInterest $system, Player $player): array
    {
        $facilities = [
            'trading_hubs' => [],
            'shipyards' => [],
            'salvage_yards' => [],
            'cartographers' => [],
            'bars' => [],
            'trading_stations' => [],
            'defense_platforms' => [],
        ];

        // Main trading hub (attached to the star)
        if ($system->tradingHub) {
            $hub = $system->tradingHub;
            $facilities['trading_hubs'][] = [
                'uuid' => $hub->uuid,
                'name' => $hub->name,
                'type' => $hub->type ?? 'trading_post',
                'location' => 'Main System Hub',
                'services' => $hub->services ?? [],
                'has_cartographer' => $hub->has_cartographer ?? false,
                'has_salvage_yard' => $hub->has_salvage_yard ?? false,
                'actions' => [
                    'trade' => "/api/players/{$player->uuid}/trading",
                    'inventory' => "/api/players/{$player->uuid}/trading/inventory",
                ],
            ];

            // If the main hub has a cartographer, add it
            if ($hub->has_cartographer ?? false) {
                $facilities['cartographers'][] = [
                    'uuid' => $hub->uuid,
                    'name' => 'Stellar Cartographer',
                    'location' => $hub->name,
                    'actions' => [
                        'browse' => "/api/players/{$player->uuid}/cartography/available",
                        'purchase' => "/api/players/{$player->uuid}/cartography/purchase",
                    ],
                ];
            }

            // If main hub has salvage yard service
            if ($hub->has_salvage_yard ?? false) {
                $facilities['salvage_yards'][] = [
                    'uuid' => $hub->uuid,
                    'name' => "{$system->name} Salvage",
                    'location' => $hub->name,
                    'actions' => [
                        'browse' => "/api/players/{$player->uuid}/salvage-yard",
                        'sell' => "/api/players/{$player->uuid}/salvage-yard/sell",
                    ],
                ];
            }
        }

        // Orbital facilities (children of the star)
        $children = $system->children()->get();

        foreach ($children as $child) {
            $facilityData = $this->buildFacilityData($child, $player);

            if (! $facilityData) {
                continue;
            }

            switch ($child->type) {
                case PointOfInterestType::TRADING_STATION:
                    $facilities['trading_stations'][] = $facilityData;
                    break;

                case PointOfInterestType::SHIPYARD:
                    $facilities['shipyards'][] = $facilityData;
                    break;

                case PointOfInterestType::SALVAGE_YARD:
                    $facilities['salvage_yards'][] = $facilityData;
                    break;

                case PointOfInterestType::DEFENSE_PLATFORM:
                    $facilities['defense_platforms'][] = $facilityData;
                    break;
            }
        }

        // Bars - every inhabited system has at least one
        if ($system->is_inhabited) {
            $facilities['bars'] = $this->buildBarsList($system, $player);
        }

        // Add summary counts
        $facilities['summary'] = [
            'total_trading_hubs' => count($facilities['trading_hubs']),
            'total_trading_stations' => count($facilities['trading_stations']),
            'total_shipyards' => count($facilities['shipyards']),
            'total_salvage_yards' => count($facilities['salvage_yards']),
            'total_cartographers' => count($facilities['cartographers']),
            'total_bars' => count($facilities['bars']),
            'total_defense_platforms' => count($facilities['defense_platforms']),
            'has_trading' => count($facilities['trading_hubs']) > 0 || count($facilities['trading_stations']) > 0,
            'has_ship_services' => count($facilities['shipyards']) > 0,
            'has_salvage' => count($facilities['salvage_yards']) > 0,
            'has_cartography' => count($facilities['cartographers']) > 0,
            'has_bar' => count($facilities['bars']) > 0,
        ];

        // Available actions summary for the UI
        $facilities['available_actions'] = $this->buildAvailableActions($facilities, $player);

        return $facilities;
    }

    /**
     * Build data for a single orbital facility.
     */
    protected function buildFacilityData(PointOfInterest $facility, Player $player): ?array
    {
        if (! $facility->type?->isStation()) {
            return null;
        }

        $data = [
            'uuid' => $facility->uuid,
            'name' => $facility->name,
            'type' => $facility->type->value,
            'type_label' => $facility->type->label(),
            'orbital_index' => $facility->orbital_index,
            'is_inhabited' => $facility->is_inhabited ?? false,
        ];

        // Add type-specific actions
        switch ($facility->type) {
            case PointOfInterestType::TRADING_STATION:
                $data['actions'] = [
                    'trade' => "/api/players/{$player->uuid}/trading",
                ];
                break;

            case PointOfInterestType::SHIPYARD:
                $data['actions'] = [
                    'browse_ships' => "/api/players/{$player->uuid}/ship-shop",
                    'buy_ship' => "/api/players/{$player->uuid}/ship-shop/purchase",
                    'repairs' => "/api/players/{$player->uuid}/ship-services/repair",
                    'upgrades' => "/api/players/{$player->uuid}/upgrades",
                ];
                break;

            case PointOfInterestType::SALVAGE_YARD:
                $data['actions'] = [
                    'browse' => "/api/players/{$player->uuid}/salvage-yard",
                    'sell_salvage' => "/api/players/{$player->uuid}/salvage-yard/sell",
                ];
                break;

            case PointOfInterestType::DEFENSE_PLATFORM:
                // Defense platforms are not directly interactable
                $data['actions'] = [];
                $data['note'] = 'Automated defense - provides system security';
                break;
        }

        return $data;
    }

    /**
     * Build list of bars in the system.
     */
    protected function buildBarsList(PointOfInterest $system, Player $player): array
    {
        $barNames = BarNameGenerator::generate($system);

        $bars = [];
        foreach ($barNames as $index => $name) {
            $bars[] = [
                'id' => $index + 1,
                'name' => $name,
                'location' => $index === 0 ? 'Main Trading Hub' : 'Orbital Station '.($index),
                'atmosphere' => BarNameGenerator::randomAtmosphere(),
                'actions' => [
                    'visit' => "/api/players/{$player->uuid}/facilities/bar",
                ],
            ];
        }

        return $bars;
    }

    /**
     * Build full bar data with rumors.
     */
    protected function buildBarData(PointOfInterest $system, Player $player): array
    {
        $barNames = BarNameGenerator::generate($system);
        $rumors = $this->barRumorService->getRumors($player, $system);

        return [
            'system' => [
                'uuid' => $system->uuid,
                'name' => $system->name,
            ],
            'bar' => [
                'name' => $barNames[0] ?? 'Local Bar',
                'atmosphere' => BarNameGenerator::randomAtmosphere(),
                'patrons' => rand(5, 30),
            ],
            'rumors' => $rumors,
            'tip' => 'The reliability of rumors varies. Confirmed intel from trusted sources is more valuable than bar gossip.',
        ];
    }

    /**
     * Build available actions summary for UI.
     */
    protected function buildAvailableActions(array $facilities, Player $player): array
    {
        $actions = [];

        if ($facilities['summary']['has_trading']) {
            $actions[] = [
                'id' => 'trading',
                'label' => 'Trading Hub',
                'description' => 'Buy and sell commodities',
                'endpoint' => "/api/players/{$player->uuid}/trading",
                'icon' => 'trading',
            ];
        }

        if ($facilities['summary']['has_ship_services']) {
            $actions[] = [
                'id' => 'shipyard',
                'label' => 'Shipyard',
                'description' => 'Buy ships, repairs, and upgrades',
                'endpoint' => "/api/players/{$player->uuid}/ship-shop",
                'icon' => 'shipyard',
            ];
        }

        if ($facilities['summary']['has_salvage']) {
            $actions[] = [
                'id' => 'salvage',
                'label' => 'Salvage Yard',
                'description' => 'Sell salvage and find parts',
                'endpoint' => "/api/players/{$player->uuid}/salvage-yard",
                'icon' => 'salvage',
            ];
        }

        if ($facilities['summary']['has_cartography']) {
            $actions[] = [
                'id' => 'cartography',
                'label' => 'Stellar Cartographer',
                'description' => 'Purchase star charts',
                'endpoint' => "/api/players/{$player->uuid}/cartography/available",
                'icon' => 'map',
            ];
        }

        if ($facilities['summary']['has_bar']) {
            $actions[] = [
                'id' => 'bar',
                'label' => 'Bar',
                'description' => 'Hear rumors and local gossip',
                'endpoint' => "/api/players/{$player->uuid}/facilities/bar",
                'icon' => 'bar',
            ];
        }

        return $actions;
    }
}
