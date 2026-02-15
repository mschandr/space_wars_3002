<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\SalvageYardInventory;
use App\Services\SalvageYardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalvageYardController extends Controller
{
    public function __construct(
        private SalvageYardService $salvageYardService
    ) {}

    /**
     * List all items available at the salvage yard at the player's current location.
     *
     * GET /api/players/{uuid}/salvage-yard
     */
    public function index(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        // Get current location trading hub
        $hub = $player->currentLocation?->tradingHub;

        if (! $hub) {
            return response()->json([
                'success' => false,
                'message' => 'You are not at a trading hub with a salvage yard.',
            ], 400);
        }

        $inventory = $this->salvageYardService->getInventoryByType($hub);

        return response()->json([
            'success' => true,
            'data' => [
                'hub' => [
                    'id' => $hub->id,
                    'name' => $hub->name,
                    'tier' => $hub->getTier(),
                ],
                'inventory' => $inventory,
            ],
        ]);
    }

    /**
     * Get components installed on the player's active ship.
     *
     * GET /api/players/{uuid}/ship-components
     */
    public function shipComponents(string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        $ship = $player->activeShip;

        if (! $ship) {
            return response()->json([
                'success' => false,
                'message' => 'No active ship.',
            ], 400);
        }

        $components = $this->salvageYardService->getInstalledComponents($ship);

        return response()->json([
            'success' => true,
            'data' => [
                'ship' => [
                    'id' => $ship->id,
                    'name' => $ship->name,
                    'class' => $ship->ship->name ?? 'Unknown',
                ],
                'components' => $components,
            ],
        ]);
    }

    /**
     * Purchase a component from the salvage yard.
     *
     * POST /api/players/{uuid}/salvage-yard/purchase
     *
     * @bodyParam inventory_id int required The salvage yard inventory item ID
     * @bodyParam slot_index int required Which slot to install the component in (1-based)
     * @bodyParam ship_id int optional The ship to install on (defaults to active ship)
     */
    public function purchase(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        $validated = $request->validate([
            'inventory_id' => 'required|integer|exists:salvage_yard_inventory,id',
            'slot_index' => 'required|integer|min:1',
            'ship_id' => 'nullable|integer',
        ]);

        // Get the hub at current location
        $hub = $player->currentLocation?->tradingHub;

        if (! $hub) {
            return response()->json([
                'success' => false,
                'message' => 'You are not at a trading hub with a salvage yard.',
            ], 400);
        }

        // Get the inventory item
        $item = SalvageYardInventory::find($validated['inventory_id']);

        if (! $item || $item->trading_hub_id !== $hub->id) {
            return response()->json([
                'success' => false,
                'message' => 'Item not available at this salvage yard.',
            ], 400);
        }

        // Get the ship
        $ship = isset($validated['ship_id'])
            ? PlayerShip::where('id', $validated['ship_id'])->where('player_id', $player->id)->first()
            : $player->activeShip;

        if (! $ship) {
            return response()->json([
                'success' => false,
                'message' => 'Ship not found.',
            ], 400);
        }

        $result = $this->salvageYardService->purchaseComponent(
            $player,
            $hub,
            $item,
            $ship,
            $validated['slot_index']
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? $result['error'] ?? null,
            'data' => $result['success'] ? [
                'component_id' => $result['component']->id,
                'credits_remaining' => $result['credits_remaining'],
            ] : null,
        ], $result['success'] ? 200 : 400);
    }

    /**
     * Uninstall a component from the player's ship.
     *
     * POST /api/players/{uuid}/ship-components/{componentId}/uninstall
     *
     * @bodyParam sell bool optional Whether to sell the component (default false)
     */
    public function uninstall(Request $request, string $uuid, int $componentId): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->first();

        if (! $player) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found',
            ], 404);
        }

        $component = PlayerShipComponent::find($componentId);

        if (! $component) {
            return response()->json([
                'success' => false,
                'message' => 'Component not found.',
            ], 404);
        }

        // Verify ownership
        if ($component->playerShip->player_id !== $player->id) {
            return response()->json([
                'success' => false,
                'message' => 'This component is not installed on your ship.',
            ], 403);
        }

        $sellToYard = $request->boolean('sell', false);

        $result = $this->salvageYardService->uninstallComponent(
            $player,
            $component,
            $sellToYard
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? $result['error'] ?? null,
            'data' => $result['success'] ? [
                'credits_received' => $result['credits_received'] ?? 0,
                'credits_total' => $result['credits_total'] ?? $player->credits,
            ] : null,
        ], $result['success'] ? 200 : 400);
    }
}
