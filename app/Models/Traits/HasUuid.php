<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Trait for automatic UUID generation on model creation.
 *
 * Automatically generates a UUID when creating a new model if the uuid
 * attribute is empty. This consolidates the common boot pattern used
 * across multiple models.
 *
 * Usage:
 * ```php
 * class Player extends Model {
 *     use HasUuid;
 * }
 * ```
 *
 * The model must have a `uuid` column in its database table.
 */
trait HasUuid
{
    /**
     * Boot the HasUuid trait.
     *
     * Automatically called by Laravel when the model boots.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     *
     * This allows route model binding to use UUID instead of ID.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Find a model by its UUID.
     */
    public static function findByUuid(string $uuid): ?static
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * Find a model by its UUID or throw an exception.
     *
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findByUuidOrFail(string $uuid): static
    {
        return static::where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Scope to find by UUID.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereUuid($query, string $uuid)
    {
        return $query->where('uuid', $uuid);
    }
}
