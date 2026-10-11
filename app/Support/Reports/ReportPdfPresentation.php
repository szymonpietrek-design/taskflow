<?php

namespace App\Support\Reports;

use App\Enums\ReportFormat;
use App\Models\Report;

/**
 * Formats the PDF copy of a report without altering the stored Markdown
 * or the public HTML document.
 */
final class ReportPdfPresentation
{
    public function title(Report $report): string
    {
        $locale = $this->locale($report);
        if ($report->title !== $this->defaultEnglishTitle($report)) {
            return $report->title;
        }

        $titles = [
            'pl' => ['Raport projektu', 'Raport pracy'],
            'de' => ['Projektbericht', 'Arbeitsbericht'],
            'fr' => ['Rapport de projet', 'Rapport de travail'],
            'es' => ['Informe del proyecto', 'Informe de trabajo'],
            'it' => ['Rapporto di progetto', 'Rapporto di lavoro'],
            'pt' => ['Relatório de projeto', 'Relatório de trabalho'],
            'nl' => ['Projectrapport', 'Werkrapport'],
            'uk' => ['Звіт про проєкт', 'Звіт про роботу'],
        ];
        if (! isset($titles[$locale])) {
            return $report->title;
        }

        $index = $report->format === ReportFormat::WORK_REPORT ? 1 : 0;

        return $titles[$locale][$index].' · '.$this->period($report);
    }

    public function period(Report $report): string
    {
        $start = $report->period_start;
        $end = $report->period_end;
        $locale = $this->locale($report);

        if ($locale === 'pl') {
            $months = ['sty', 'lut', 'mar', 'kwi', 'maj', 'cze', 'lip', 'sie', 'wrz', 'paź', 'lis', 'gru'];
            $month = static fn ($date): string => $months[$date->month - 1];
            if ($start->isSameDay($end)) {
                return $start->day.' '.$month($start).' '.$start->year;
            }
            if ($start->format('Y-m') === $end->format('Y-m')) {
                return $start->day.'–'.$end->day.' '.$month($end).' '.$end->year;
            }
            if ($start->year === $end->year) {
                return $start->day.' '.$month($start).' – '.$end->day.' '.$month($end).' '.$end->year;
            }

            return $start->day.' '.$month($start).' '.$start->year.' – '.$end->day.' '.$month($end).' '.$end->year;
        }

        if ($locale === 'en') {
            return $this->englishPeriod($report);
        }

        // Carbon has locale-aware month names for supported languages.
        if ($start->isSameDay($end)) {
            return $start->locale($locale)->isoFormat('LL');
        }

        return $start->locale($locale)->isoFormat('LL').' – '.$end->locale($locale)->isoFormat('LL');
    }

    public function body(Report $report): string
    {
        $body = $report->body ?? '';
        if ($report->format !== ReportFormat::WORK_REPORT) {
            return $body;
        }

        // Only omit the exact boilerplate already reproduced in the PDF masthead.
        // Custom headings and manually edited metadata stay in the document.
        $pattern = '/\A\h*#\h+(?:Raport pracy|Raport z prac|Work Report|Work report)\h*\R+'
            .'\h*\*\*(?:Projekt|Project):\*\*\h*'.preg_quote($report->project->name, '/').'\h*\R+'
            .'\h*\*\*(?:Okres|Period):\*\*\h*'.preg_quote($report->period_start->format('Y-m-d'), '/')
            .'\h*[–-]\h*'.preg_quote($report->period_end->format('Y-m-d'), '/').'\h*\R*/u';

        return preg_replace($pattern, '', $body, 1) ?? $body;
    }

    private function defaultEnglishTitle(Report $report): string
    {
        $prefix = $report->format === ReportFormat::WORK_REPORT ? 'Work report · ' : 'Project report · ';

        return $prefix.$this->englishPeriod($report);
    }

    private function englishPeriod(Report $report): string
    {
        $start = $report->period_start;
        $end = $report->period_end;
        if ($start->isSameDay($end)) {
            return $start->format('M j, Y');
        }
        if ($start->format('Y-m') === $end->format('Y-m')) {
            return $start->format('M j').'–'.$end->format('j, Y');
        }
        if ($start->year === $end->year) {
            return $start->format('M j').' – '.$end->format('M j, Y');
        }

        return $start->format('M j, Y').' – '.$end->format('M j, Y');
    }

    private function locale(Report $report): string
    {
        return strtolower(explode('-', str_replace('_', '-', $report->generated_locale ?: 'en'))[0]);
    }
}
