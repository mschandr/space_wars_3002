<?php
namespace App\Contracts;

use App\Models\Galaxy;

interface PointGeneratorInterface
{
    /**
     * Generate points within given bounds.
     *
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(Galaxy $galaxy): array;
}
