<?php

namespace App\Http\Controllers\Api;

use App\Models\PlayerShip;
use App\Models\PlayerShipComponent;
use App\Models\PointOfInterest;
use App\Models\SalvageYardInventory;
use App\Services\SalvageYardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalvageYardController extends BaseApiController
{
    public function __construct(
        private SalvageYardService $salvageYardService
    ) {}

    /**
     * List all items available at the salvage yard at the player's current location.
     *
     * GET /api/players/{uuid}/salvage-yard
     */
    public function index(Request $request, string $uuid): JsonResponse
    {
        $player = $this->findAuthenticatedPlayerOrFail($uuid, $request, ['currentLocation.tradingHub']);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $hub = $player->currentLocation?->tradingHub;

        if (! $hub) {
            return $this->error('You are not at a trading hub with a salvage yard.', 'NO_SALVAGE_YARD');
        }

        $inventory = $this->salvageYardService->getInventoryByType($hub);

        return $this->success([
            'hub' => [
                'id' => $hub->id,
                'name' => $hub->name,
                'tier' => $hub->getTier(),
            ],
            'inventory' => $inventory,
        ]);
    }

    /**
     * Get components installed on the player's active ship.
     *
     * GET /api/players/{uuid}/ship-components
     */
    public function shipComponents(Request $request, string $uuid): JsonResponse
    {
        $player = $this->findPlayerWithShipOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $ship = $player->activeShip;
        $components = $this->salvageYardService->getInstalledComponents($ship);

        return $this->success([
            'ship' => [
                'id' => $ship->id,
                'name' => $ship->name,
                'class' => $ship->ship->name ?? 'Unknown',
            ],
            'components' => $components,
        ]);
    }

    /**
     * Purchase a component from the salvage yard.
     *
     * POST /api/players/{uuid}/salvage-yard/purchase
     */
    public function purchase(Request $request, string $uuid): JsonResponse
    {
        $player = $this->findAuthenticatedPlayerOrFail($uuid, $request, ['currentLocation.tradingHub']);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $validated = $request->validate([
            'inventory_id' => 'required|integer|exists:salvage_yard_inventory,id',
            'slot_index' => 'required|integer|min:1',
            'ship_id' => 'nullable|integer',
        ]);

        $hub = $player->currentLocation?->tradingHub;

        if (! $hub) {
            return $this->error('You are not at a trading hub with a salvage yard.', 'NO_SALVAGE_YARD');
        }

        $item = SalvageYardInventory::find($validated['inventory_id']);

        if (! $item || $item->trading_hub_id !== $hub->id) {
            return $this->error('Item not available at this salvage yard.', 'ITEM_NOT_AVAILABLE');
        }

        $ship = isset($validated['ship_id'])
            ? PlayerShip::where('id', $validated['ship_id'])->where('player_id', $player->id)->first()
            : $player->activeShip;

        if (! $ship) {
            return $this->error('Ship not found.', 'SHIP_NOT_FOUND');
        }

        $result = $this->salvageYardService->purchaseComponent(
            $player,
            $hub,
            $item,
            $ship,
            $validated['slot_index']
        );

        if (! $result['success']) {
            return $this->error($result['error'] ?? $result['message'] ?? 'Purchase failed');
        }

        return $this->success([
            'component_id' => $result['component']->id,
            'credits_remaining' => $result['credits_remaining'],
        ], $result['message'] ?? 'Component purchased.');
    }

    /**
     * Uninstall a component from the player's ship.
     *
     * POST /api/players/{uuid}/ship-components/{componentId}/uninstall
     */
    public function uninstall(Request $request, string $uuid, int $componentId): JsonResponse
    {
        $player = $this->findAuthenticatedPlayerOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $component = PlayerShipComponent::find($componentId);

        if (! $component) {
            return $this->notFound('Component not found.');
        }

        if ($component->playerShip->player_id !== $player->id) {
            return $this->forbidden('This component is not installed on your ship.');
        }

        $sellToYard = $request->boolean('sell', false);

        $result = $this->salvageYardService->uninstallComponent(
            $player,
            $component,
            $sellToYard
        );

        if (! $result['success']) {
            return $this->error($result['error'] ?? $result['message'] ?? 'Uninstall failed');
        }

        return $this->success([
            'credits_received' => $result['credits_received'] ?? 0,
            'credits_total' => $result['credits_total'] ?? $player->credits,
        ], $result['message'] ?? 'Component uninstalled.');
    }

    /**
     * Browse salvage yard components at a specific system POI.
     * Triggers lazy inventory generation on first visit.
     *
     * GET /api/systems/{uuid}/salvage-yard
     */
    public function indexBySystem(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->first();

        if (! $poi) {
            return $this->notFound('System not found.');
        }

        $this->salvageYardService->ensureSalvageYardInventory($poi);

        $inventory = $this->salvageYardService->getInventoryByPoi($poi);

        return $this->success([
            'system' => [
                'uuid' => $poi->uuid,
                'name' => $poi->name,
            ],
            'inventory' => $inventory,
        ]);
    }

    /**
     * Sell a ship to the salvage yard for lump-sum credits.
     *
     * POST /api/players/{uuid}/salvage-yard/sell-ship
     */
    public function sellShip(Request $request, string $uuid): JsonResponse
    {
        $player = $this->findPlayerWithLocationOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $validated = $request->validate([
            'ship_uuid' => 'required|string',
        ]);

        $ship = PlayerShip::where('uuid', $validated['ship_uuid'])
            ->where('player_id', $player->id)
            ->first();

        if (! $ship) {
            return $this->notFound('Ship not found.');
        }

        $poi = $player->currentLocation;

        $result = $this->salvageYardService->sellShipToSalvageYard($player, $ship, $poi);

        if (! $result['success']) {
            return $this->error($result['error']);
        }

        return $this->success([
            'credits_received' => $result['credits_received'],
            'components_salvaged' => $result['components_salvaged'],
            'credits_total' => $player->fresh()->credits,
        ], sprintf(
            'Ship sold for %s credits. %d components salvaged.',
            number_format($result['credits_received']),
            $result['components_salvaged']
        ));
    }
}
