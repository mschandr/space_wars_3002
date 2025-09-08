<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuidAndVersion
{
    protected static function bootHasUuidAndVersion(): void
    {
        static::creating(function ($model) {
            // Ensure UUID before persisting
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            // Ensure Version before persisting
            if (config('game_config.feature.stamp_version', true)) {
                if (empty($model->version) && file_exists(base_path('VERSION'))) {
                    $model->version = trim(file_get_contents(base_path('VERSION')));
                }
            }
        });
    }

    /**
     * Lazy initialize UUID if accessed before save.
     */
    public function getUuidAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['uuid'] = (string) Str::uuid();
        }
        return $this->attributes['uuid'];
    }

    /**
     * Lazy initialize version if accessed before save.
     */
    public function getVersionAttribute($value)
    {
        if (empty($value) && config('game_config.feature.stamp_version', true)) {
            if (file_exists(base_path('VERSION'))) {
                $this->attributes['version'] = trim(file_get_contents(base_path('VERSION')));
            }
        }
        return $this->attributes['version'] ?? null;
    }
}
