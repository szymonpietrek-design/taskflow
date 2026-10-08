<?php

namespace App\Enums;

enum TaskStatusCategory: int
{
    case TODO = 1;
    case IN_PROGRESS = 2;
    case DONE = 3;
    case CANCELED = 4;
}
