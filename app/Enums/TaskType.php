<?php

namespace App\Enums;

enum TaskType: int
{
    case TASK = 1;
    case FEATURE = 2;
    case IMPROVEMENT = 3;
    case BUG = 4;
}
