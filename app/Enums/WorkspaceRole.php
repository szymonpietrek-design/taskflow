<?php

namespace App\Enums;

enum WorkspaceRole: int
{
    case OWNER = 1;
    case ADMIN = 2;
    case MEMBER = 3;
}
