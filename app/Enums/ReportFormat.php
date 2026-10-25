<?php

namespace App\Enums;

enum ReportFormat: string
{
    case PROJECT_SUMMARY = 'project_summary';
    case TECHNICAL_REPORT = 'technical_report';
    case WORK_REPORT = 'work_report';

    public function label(): string
    {
        return match ($this) {
            self::PROJECT_SUMMARY => 'Project Summary',
            self::TECHNICAL_REPORT => 'Technical Report',
            self::WORK_REPORT => 'Work Report',
        };
    }
}
