<?php

namespace App\Http\Controllers\Api\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Trait for finding models by UUID with consistent error handling.
 *
 * Consolidates the repeated pattern of:
 * - Finding a model by UUID
 * - Returning not found if missing
 * - Optional eager loading
 */
trait FindsByUuid
{
    /**
     * Find a model by UUID or return null.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array  $with  Relationships to eager load
     */
    protected function findByUuid(string $modelClass, string $uuid, array $with = []): ?Model
    {
        $query = $modelClass::where('uuid', $uuid);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }

    /**
     * Find a model by UUID or throw ModelNotFoundException.
     *
     * @param  class-string<Model>  $modelClass
     * @param  array  $with  Relationships to eager load
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    protected function findByUuidOrFail(string $modelClass, string $uuid, array $with = []): Model
    {
        $query = $modelClass::where('uuid', $uuid);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->firstOrFail();
    }

    /**
     * Find a model by UUID or return a not found response.
     *
     * @param  class-string<Model>  $modelClass
     * @param  string|null  $message  Custom not found message
     * @param  array  $with  Relationships to eager load
     */
    protected function findByUuidOrNotFound(string $modelClass, string $uuid, ?string $message = null, array $with = []): Model|JsonResponse
    {
        $model = $this->findByUuid($modelClass, $uuid, $with);

        if (! $model) {
            $modelName = class_basename($modelClass);
            $message = $message ?? "{$modelName} not found";

            return $this->notFound($message);
        }

        return $model;
    }

    /**
     * Find a model by UUID with a scope constraint.
     *
     * @param  class-string<Model>  $modelClass
     * @param  callable  $scopeCallback  Additional query constraints
     * @param  array  $with  Relationships to eager load
     */
    protected function findByUuidWithScope(string $modelClass, string $uuid, callable $scopeCallback, array $with = []): ?Model
    {
        $query = $modelClass::where('uuid', $uuid);

        $scopeCallback($query);

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->first();
    }
}
