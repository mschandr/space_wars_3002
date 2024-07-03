<?php

namespace App\Models;

use App\Exceptions\StarSystemTemplateException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use mschandr\WeightedRandom\WeightedRandomGenerator;

class StarSystemTemplate extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $table = 'star_system_templates';
    protected $fillable = [
        'name',
        'description',
        'for_system_star_type',
        'planet_count',
        'star_system_configuration',
        'weight',
    ];
    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'star_system_configuration' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
        });
    }

    /**
     * Gets a template by specifying a star system type (e.g. O or K or M or G)
     *
     * @param  string $starSystemType
     * @returns StarSystemTemplate
     * @throws StarSystemTemplateException
     */
    public function getByStarSystemType(string $starSystemType): StarSystemTemplate
    {
         $templates = self::where('for_system_star_type', $starSystemType)->all();
         if ($templates->isNotEmpty()) {
             foreach ($templates as $template) {

             }
         } else {
             throw new StarSystemTemplateException('Template for $starSystemType not found.', '5404');
         }
    }


    /**
     * @throws StarSystemTemplateException
     */
    public function getRandomWeightedTemplateId(string $starSystemType): string
    {
        $star_system_templates = StarType::whereIn('classification', $starSystemType)
                                         ->get(['id', 'classification']);
        $generator             = new WeightedRandomGenerator();
        try {
            foreach ($star_system_templates as $k => $template) {
                $generator->registerValue($template->id, $template->getWeight());
            }
        } catch (StarSystemTemplateException $e) {
            throw new StarSystemTemplateException('Could not register non-existent weight. Template weight missing',
                '5406');
        }
        return $generator->generate();
    }

    public function getTemplateWeight()
    {
        return $this->weight;
    }
}
