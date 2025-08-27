<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Faker\SpaceProvider;

class Galaxy extends Model
{
    /** @use HasFactory<\Database\Factories\GalaxyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'seed',
        'height',
        'width',
        'stars',
        'config',
    ];

    public function getGalaxyName(): string
    {
        return $this->name;
    }

    public static function GenerateGalaxyName(): string
    {
        return SpaceProvider::generateGalaxyName();
    }
}
