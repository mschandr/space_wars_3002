<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Player extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'call_sign',
        'credits',
        'experience',
        'level',
        'current_poi_id',
        'status',
    ];

    protected $casts = [
        'credits' => 'decimal:2',
        'experience' => 'integer',
        'level' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($player) {
            if (empty($player->uuid)) {
                $player->uuid = Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(PointOfInterest::class, 'current_poi_id');
    }

    public function ships(): HasMany
    {
        return $this->hasMany(PlayerShip::class);
    }

    public function activeShip()
    {
        return $this->hasOne(PlayerShip::class)->where('is_active', true);
    }

    public function addCredits(float $amount): void
    {
        $this->credits += $amount;
        $this->save();
    }

    public function deductCredits(float $amount): bool
    {
        if ($this->credits < $amount) {
            return false;
        }

        $this->credits -= $amount;
        $this->save();

        return true;
    }

    public function addExperience(int $amount): void
    {
        $this->experience += $amount;

        $newLevel = $this->calculateLevel($this->experience);
        if ($newLevel > $this->level) {
            $this->level = $newLevel;
        }

        $this->save();
    }

    protected function calculateLevel(int $experience): int
    {
        return (int) floor(sqrt($experience / 100)) + 1;
    }
}
