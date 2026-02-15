<?php

namespace App\Http\Controllers\Api;

use App\Models\PoiType;
use Illuminate\Http\JsonResponse;

class PoiTypeController extends BaseApiController
{
    /**
     * Get all POI types.
     *
     * GET /api/poi-types
     */
    public function index(): JsonResponse
    {
        $types = PoiType::orderBy('id')->get();

        return $this->success([
            'types' => $types->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'description' => $type->description,
                'domain' => $type->domain,
                'category' => $type->category,
                'capabilities' => [
                    'is_habitable' => $type->is_habitable,
                    'is_mineable' => $type->is_mineable,
                    'is_orbital' => $type->is_orbital,
                    'is_dockable' => $type->is_dockable,
                    'can_have_trading_hub' => $type->can_have_trading_hub,
                    'can_have_warp_gate' => $type->can_have_warp_gate,
                ],
                'base_danger_level' => $type->base_danger_level,
                'icon' => $type->icon,
                'color' => $type->color,
                'produces_minerals' => $type->produces_minerals,
            ]),
        ]);
    }

    /**
     * Get a specific POI type by ID or code.
     *
     * GET /api/poi-types/{idOrCode}
     */
    public function show(string $idOrCode): JsonResponse
    {
        $type = is_numeric($idOrCode)
            ? PoiType::find($idOrCode)
            : PoiType::byCode($idOrCode);

        if (! $type) {
            return $this->notFound('POI type not found');
        }

        return $this->success([
            'id' => $type->id,
            'code' => $type->code,
            'label' => $type->label,
            'description' => $type->description,
            'domain' => $type->domain,
            'category' => $type->category,
            'capabilities' => [
                'is_habitable' => $type->is_habitable,
                'is_mineable' => $type->is_mineable,
                'is_orbital' => $type->is_orbital,
                'is_dockable' => $type->is_dockable,
                'can_have_trading_hub' => $type->can_have_trading_hub,
                'can_have_warp_gate' => $type->can_have_warp_gate,
            ],
            'base_danger_level' => $type->base_danger_level,
            'icon' => $type->icon,
            'color' => $type->color,
            'produces_minerals' => $type->produces_minerals,
        ]);
    }

    /**
     * Get POI types grouped by category.
     *
     * GET /api/poi-types/by-category
     */
    public function byCategory(): JsonResponse
    {
        $types = PoiType::orderBy('id')->get();

        $grouped = $types->groupBy('category')->map(fn ($group) => $group->map(fn ($type) => [
            'id' => $type->id,
            'code' => $type->code,
            'label' => $type->label,
            'color' => $type->color,
            'icon' => $type->icon,
        ]));

        return $this->success(['categories' => $grouped]);
    }

    /**
     * Get only habitable POI types.
     *
     * GET /api/poi-types/habitable
     */
    public function habitable(): JsonResponse
    {
        $types = PoiType::habitable()->orderBy('id')->get();

        return $this->success([
            'types' => $types->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'description' => $type->description,
            ]),
        ]);
    }

    /**
     * Get only mineable POI types.
     *
     * GET /api/poi-types/mineable
     */
    public function mineable(): JsonResponse
    {
        $types = PoiType::mineable()->orderBy('id')->get();

        return $this->success([
            'types' => $types->map(fn ($type) => [
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'produces_minerals' => $type->produces_minerals,
            ]),
        ]);
    }
}
