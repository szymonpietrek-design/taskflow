<?php

namespace App\Enums;

enum ReportSource: string
{
    case TASKS = 'tasks';
    case MESSAGES = 'messages';
    case ACTIVITY = 'activity';
}
