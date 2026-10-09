<?php

namespace App\Enums;

enum TaskActivityType: int
{
    case STATUS_CHANGED = 1;
    case TITLE_CHANGED = 2;
    case DESCRIPTION_CHANGED = 3;
    case PRIORITY_CHANGED = 4;
    case ASSIGNEE_CHANGED = 5;
    case DUE_DATE_CHANGED = 6;
}
