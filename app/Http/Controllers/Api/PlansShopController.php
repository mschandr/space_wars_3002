<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Player;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlansShopController extends BaseApiController
{
    /**
     * Check if trading hub sells plans and list available
     *
     * GET /api/trading-hubs/{uuid}/plans-shop
     */
    public function getPlansShop(Request $request, string $uuid): JsonResponse
    {
        $tradingHub = $this->findTradingHub($uuid);

        if (! $tradingHub) {
            return $this->notFound('Trading hub not found');
        }

        $tradingHub->load('plans');

        $sellsPlans = $tradingHub->has_plans;
        $plans = $tradingHub->plans;

        if (! $sellsPlans || $plans->isEmpty()) {
            return $this->success([
                'has_plans_shop' => false,
                'available_plans' => [],
            ]);
        }

        // Get player's owned plans if authenticated
        $player = null;
        if ($request->user()) {
            $player = Player::where('user_id', $request->user()->id)->first();
        }

        $enrichedPlans = $plans->map(function ($plan) use ($player) {
            $ownedCount = $player ? $player->getPlanCount($plan->id) : 0;
            $currentBonus = $ownedCount * $plan->additional_levels;
            $projectedBonus = $currentBonus + $plan->additional_levels;

            return [
                'plan' => new PlanResource($plan),
                'owned_count' => $ownedCount,
                'current_bonus' => $currentBonus,
                'projected_bonus' => $projectedBonus,
            ];
        });

        return $this->success([
            'has_plans_shop' => true,
            'trading_hub_name' => $tradingHub->name,
            'available_plans' => $enrichedPlans,
        ]);
    }

    /**
     * Browse all available upgrade plans catalog
     *
     * GET /api/plans/catalog
     */
    public function getCatalog(Request $request): JsonResponse
    {
        $query = Plan::query();

        // Optional filters
        if ($request->has('component')) {
            $query->where('component', $request->component);
        }

        if ($request->has('rarity')) {
            $query->where('rarity', $request->rarity);
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $plans = $query->orderBy('component')->orderBy('price')->get();

        return $this->success([
            'plans' => PlanResource::collection($plans),
            'total_count' => $plans->count(),
        ]);
    }

    /**
     * Purchase an upgrade plan
     *
     * POST /api/players/{uuid}/plans/purchase
     */
    public function purchasePlan(Request $request, string $uuid): JsonResponse
    {
        $player = Player::where('uuid', $uuid)->firstOrFail();
        $this->authorizePlayer($player, $request->user());

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'trading_hub_uuid' => 'required|string',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $tradingHub = $this->findTradingHub($validated['trading_hub_uuid']);

        if (! $tradingHub) {
            return $this->error('Trading hub not found', 'NOT_FOUND', null, 404);
        }

        // Check if hub sells plans
        if (! $tradingHub->has_plans) {
            return $this->error('This trading hub does not sell upgrade plans', 400);
        }

        // Check if plan is available at this hub
        if (! $tradingHub->plans->contains($plan->id)) {
            return $this->error('This plan is not available at this trading hub', 400);
        }

        // Check requirements
        if ($plan->requirements && isset($plan->requirements['min_level'])) {
            if ($player->level < $plan->requirements['min_level']) {
                return $this->error(
                    "You need to be level {$plan->requirements['min_level']} to purchase this plan",
                    400
                );
            }
        }

        // Check credits
        if ($player->credits < $plan->price) {
            return $this->error('Insufficient credits', 400);
        }

        // Deduct credits
        $player->credits -= $plan->price;
        $player->save();

        // Grant plan to player (attach with timestamp)
        $player->plans()->attach($plan->id, [
            'acquired_at' => now(),
        ]);

        // Calculate new totals
        $ownedCount = $player->getPlanCount($plan->id);
        $totalBonus = $ownedCount * $plan->additional_levels;

        return $this->success([
            'plan' => new PlanResource($plan),
            'cost_paid' => $plan->price,
            'remaining_credits' => $player->credits,
            'owned_count' => $ownedCount,
            'total_bonus' => $totalBonus,
        ], 'Upgrade plan purchased successfully');
    }
}
