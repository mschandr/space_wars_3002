<?php
namespace App\Contracts;

interface PointGeneratorInterface
{
    /**
     * Generate points within given bounds.
     *
     * @return array<int,array{0:int,1:int}>
     */
    public function sample(): array;
}
