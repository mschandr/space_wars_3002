<?php

namespace App\Enums\PointsOfInterest;

enum PointOfInterestStatus: int
{
    case DRAFT = 0;
    case ACTIVE = 1;
    case INACTIVE = 2;
    case DESTROYED = 3;
    case HIDDEN = 4;
}
