<?php

namespace App\Enums;

enum ReportFormat: string
{
    case PROJECT_SUMMARY = 'project_summary';
    case WORK_REPORT = 'work_report';

    public function label(): string
    {
        return match ($this) {
            self::PROJECT_SUMMARY => 'Project Summary',
            self::WORK_REPORT => 'Work Report',
        };
    }
}
