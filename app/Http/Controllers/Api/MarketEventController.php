<?php

namespace App\Http\Controllers\Api;

use App\Models\Galaxy;
use App\Models\MarketEvent;
use App\Models\TradingHub;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketEventController extends BaseApiController
{
    /**
     * List active market events in a galaxy
     */
    public function galaxyEvents(string $galaxyUuid, Request $request): JsonResponse
    {
        $galaxy = Galaxy::where('uuid', $galaxyUuid)->firstOrFail();

        $query = MarketEvent::where('galaxy_id', $galaxy->id)
            ->where('is_active', true)
            ->with(['mineral', 'tradingHub.poi']);

        // Filter by event type if specified
        if ($request->has('event_type')) {
            $query->where('event_type', $request->get('event_type'));
        }

        // Filter by mineral if specified
        if ($request->has('mineral')) {
            $query->whereHas('mineral', function ($q) use ($request) {
                $q->where('name', $request->get('mineral'))
                    ->orWhere('symbol', $request->get('mineral'));
            });
        }

        $events = $query->orderBy('expires_at', 'asc')->get();

        $formattedEvents = $events->map(function ($event) {
            return [
                'uuid' => $event->uuid,
                'event_type' => $event->event_type,
                'mineral' => [
                    'name' => $event->mineral->name,
                    'symbol' => $event->mineral->symbol,
                ],
                'price_modifier' => $event->price_modifier,
                'trading_hub' => $event->tradingHub ? [
                    'uuid' => $event->tradingHub->uuid,
                    'name' => $event->tradingHub->poi->name ?? 'Unknown',
                    'location' => [
                        'x' => $event->tradingHub->poi->x ?? null,
                        'y' => $event->tradingHub->poi->y ?? null,
                    ],
                ] : null,
                'description' => $event->description,
                'created_at' => $event->created_at?->toIso8601String(),
                'expires_at' => $event->expires_at?->toIso8601String(),
                'time_remaining_seconds' => $event->expires_at ? max(0, now()->diffInSeconds($event->expires_at, false)) : null,
            ];
        });

        return $this->success([
            'galaxy' => [
                'uuid' => $galaxy->uuid,
                'name' => $galaxy->name,
            ],
            'total_active_events' => $events->count(),
            'events' => $formattedEvents,
        ], 'Market events retrieved successfully');
    }

    /**
     * Get specific market event details
     */
    public function show(string $eventUuid): JsonResponse
    {
        $event = MarketEvent::where('uuid', $eventUuid)
            ->with(['mineral', 'tradingHub.poi', 'galaxy'])
            ->firstOrFail();

        $eventData = [
            'uuid' => $event->uuid,
            'event_type' => $event->event_type,
            'galaxy' => [
                'uuid' => $event->galaxy->uuid,
                'name' => $event->galaxy->name,
            ],
            'mineral' => [
                'uuid' => $event->mineral->uuid,
                'name' => $event->mineral->name,
                'symbol' => $event->mineral->symbol,
                'base_price' => $event->mineral->base_price,
            ],
            'price_modifier' => $event->price_modifier,
            'modified_price' => round($event->mineral->base_price * $event->price_modifier, 2),
            'trading_hub' => $event->tradingHub ? [
                'uuid' => $event->tradingHub->uuid,
                'name' => $event->tradingHub->poi->name ?? 'Unknown',
                'location' => [
                    'x' => $event->tradingHub->poi->x ?? null,
                    'y' => $event->tradingHub->poi->y ?? null,
                ],
            ] : null,
            'description' => $event->description,
            'is_active' => $event->is_active,
            'created_at' => $event->created_at?->toIso8601String(),
            'expires_at' => $event->expires_at?->toIso8601String(),
            'time_remaining_seconds' => $event->expires_at && $event->is_active
                ? max(0, now()->diffInSeconds($event->expires_at, false))
                : 0,
        ];

        return $this->success($eventData, 'Market event details retrieved successfully');
    }

    /**
     * Get active market events affecting a specific trading hub
     */
    public function hubEvents(string $hubUuid): JsonResponse
    {
        $hub = TradingHub::where('uuid', $hubUuid)
            ->with('poi')
            ->firstOrFail();

        $events = MarketEvent::where('trading_hub_id', $hub->id)
            ->where('is_active', true)
            ->with('mineral')
            ->orderBy('expires_at', 'asc')
            ->get();

        $formattedEvents = $events->map(function ($event) {
            return [
                'uuid' => $event->uuid,
                'event_type' => $event->event_type,
                'mineral' => [
                    'name' => $event->mineral->name,
                    'symbol' => $event->mineral->symbol,
                    'base_price' => $event->mineral->base_price,
                ],
                'price_modifier' => $event->price_modifier,
                'modified_price' => round($event->mineral->base_price * $event->price_modifier, 2),
                'price_change_percent' => round(($event->price_modifier - 1) * 100, 1),
                'description' => $event->description,
                'expires_at' => $event->expires_at?->toIso8601String(),
                'time_remaining_seconds' => $event->expires_at ? max(0, now()->diffInSeconds($event->expires_at, false)) : null,
            ];
        });

        return $this->success([
            'trading_hub' => [
                'uuid' => $hub->uuid,
                'name' => $hub->poi->name ?? 'Unknown',
                'location' => [
                    'x' => $hub->poi->x ?? null,
                    'y' => $hub->poi->y ?? null,
                ],
            ],
            'active_events_count' => $events->count(),
            'events' => $formattedEvents,
        ], 'Trading hub events retrieved successfully');
    }
}
