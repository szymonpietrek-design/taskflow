<?php

namespace App\Enums;

enum ReleaseStatus: int
{
    case DRAFT = 1;
    case PLANNED = 2;
    case RELEASED = 3;
    case CANCELED = 4;
}
