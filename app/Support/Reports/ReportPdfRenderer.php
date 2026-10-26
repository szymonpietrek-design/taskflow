<?php

namespace App\Support\Reports;

use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;

final class ReportPdfRenderer
{
    public function render(string $html, string $locale = 'en'): string
    {
        if (! class_exists(Dompdf::class)) {
            throw new \RuntimeException('PDF export requires dompdf/dompdf. Install it using Composer.');
        }

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $pdf = new Dompdf($options);
        $pdf->setPaper('a4');
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        // Resolve the saved report's language once, before Dompdf renders its page callbacks.
        $pageLabel = app(ReportDocumentLocale::class)->text($locale, 'reports.document.page', 'Page');

        // Add a quiet footer to every A4 page without embedding executable PHP in HTML.
        $pdf->getCanvas()->page_script(static function (
            int $pageNumber,
            int $pageCount,
            Canvas $canvas,
            FontMetrics $fontMetrics
        ) use ($pageLabel): void {
            $font = $fontMetrics->getFont('DejaVu Sans');
            $size = 7.5;
            $left = 42.5; // 15 mm, aligned with the @page horizontal margin.
            $y = $canvas->get_height() - 34;
            $right = $canvas->get_width() - $left;
            $gray = [0.47, 0.47, 0.47];
            $canvas->line($left, $y - 10, $right, $y - 10, [0.85, 0.85, 0.85], 0.5);
            $canvas->text($left, $y, 'TaskFlow', $font, $size, $gray);

            $label = $pageLabel.' '.$pageNumber.' / '.$pageCount;
            $textWidth = $fontMetrics->getTextWidth($label, $font, $size);
            $canvas->text($right - $textWidth, $y, $label, $font, $size, $gray);
        });

        return $pdf->output();
    }
}
