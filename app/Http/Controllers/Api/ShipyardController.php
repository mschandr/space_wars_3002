<?php

namespace App\Http\Controllers\Api;

use App\Models\PointOfInterest;
use App\Models\ShipyardInventory;
use App\Services\ShipyardInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipyardController extends BaseApiController
{
    public function __construct(
        private ShipyardInventoryService $shipyardService
    ) {}

    /**
     * List available ships at a system's shipyard.
     * Triggers lazy inventory generation on first visit.
     *
     * GET /api/systems/{uuid}/shipyard
     */
    public function index(string $uuid): JsonResponse
    {
        $poi = PointOfInterest::where('uuid', $uuid)->first();

        if (! $poi) {
            return $this->notFound('System not found.');
        }

        $this->shipyardService->ensureInventory($poi);

        $ships = $this->shipyardService->getAvailableShips($poi);

        return $this->success([
            'system' => [
                'uuid' => $poi->uuid,
                'name' => $poi->name,
            ],
            'ships' => $ships->map(fn ($item) => $this->formatShipItem($item)),
        ]);
    }

    /**
     * Get detailed view of a specific shipyard inventory item.
     *
     * GET /api/shipyard-inventory/{uuid}
     */
    public function show(string $uuid): JsonResponse
    {
        $item = ShipyardInventory::where('uuid', $uuid)
            ->with('ship')
            ->first();

        if (! $item) {
            return $this->notFound('Ship not found in shipyard inventory.');
        }

        return $this->success($this->formatShipItem($item, detailed: true));
    }

    /**
     * Purchase a ship from the shipyard.
     *
     * POST /api/players/{uuid}/shipyard/purchase
     */
    public function purchase(Request $request, string $uuid): JsonResponse
    {
        $player = $this->findAuthenticatedPlayerOrFail($uuid, $request);

        if ($player instanceof JsonResponse) {
            return $player;
        }

        $validated = $request->validate([
            'inventory_uuid' => 'required|string|exists:shipyard_inventory,uuid',
            'custom_name' => 'nullable|string|max:100',
        ]);

        $item = ShipyardInventory::where('uuid', $validated['inventory_uuid'])->first();

        if (! $item) {
            return $this->notFound('Ship not found in inventory.');
        }

        $result = $this->shipyardService->purchaseShip(
            $player,
            $item,
            $validated['custom_name'] ?? null
        );

        if (! $result['success']) {
            return $this->error($result['error']);
        }

        return $this->success([
            'ship' => [
                'uuid' => $result['ship']->uuid,
                'name' => $result['ship']->name,
                'hull' => $result['ship']->hull,
                'max_hull' => $result['ship']->max_hull,
                'cargo_hold' => $result['ship']->cargo_hold,
                'weapons' => $result['ship']->weapons,
                'sensors' => $result['ship']->sensors,
                'warp_drive' => $result['ship']->warp_drive,
            ],
            'credits_remaining' => $player->credits,
        ], 'Ship purchased successfully.');
    }

    private function formatShipItem(ShipyardInventory $item, bool $detailed = false): array
    {
        $data = [
            'uuid' => $item->uuid,
            'name' => $item->name,
            'rarity' => $item->rarity->value,
            'rarity_label' => $item->rarity->label(),
            'rarity_color' => $item->rarity->color(),
            'price' => (float) $item->price,
            'blueprint' => $item->ship ? [
                'name' => $item->ship->name,
                'class' => $item->ship->class,
            ] : null,
            'stats' => [
                'hull_strength' => $item->hull_strength,
                'shield_strength' => $item->shield_strength,
                'cargo_capacity' => $item->cargo_capacity,
                'speed' => $item->speed,
                'weapon_slots' => $item->weapon_slots,
                'utility_slots' => $item->utility_slots,
                'max_fuel' => $item->max_fuel,
                'sensors' => $item->sensors,
                'warp_drive' => $item->warp_drive,
                'weapons' => $item->weapons,
            ],
            'is_sold' => $item->is_sold,
        ];

        if ($detailed) {
            $data['variation_traits'] = $item->variation_traits;
            $data['attributes'] = $item->attributes;
        }

        return $data;
    }
}
