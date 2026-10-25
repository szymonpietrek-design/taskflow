<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $report->title }}</title>
    <style>
        @page { size: A4; margin: 16mm 15mm 18mm; }
        * { box-sizing: border-box; }
        html { font-family: 'DejaVu Sans', sans-serif; color: #1b1b1b; background: white; }
        body { margin: 0 auto; max-width: 960px; padding: 32px; font-size: 13px; line-height: 1.65; }
        .masthead { padding-bottom: 16px; margin-bottom: 26px; border-bottom: 1px solid #ddd; }
        .eyebrow { font-size: 11px; letter-spacing: .11em; text-transform: uppercase; color: #656565; }
        h1.document-title { font-size: 25px; line-height: 1.3; margin: 7px 0 12px; }
        .meta { font-size: 12px; color: #555; margin: 4px 0; }
        .draft { display: inline-block; padding: 3px 8px; background: #f2f2f2; border-radius: 4px; font-size: 11px; margin-bottom: 8px; }
        .report-body { overflow-wrap: anywhere; }
        .report-body h1 { font-size: 21px; margin: 26px 0 12px; }
        .report-body h2 { font-size: 17px; margin: 24px 0 10px; }
        .report-body h3 { font-size: 14px; margin: 18px 0 8px; }
        .report-body h1, .report-body h2, .report-body h3 { line-height: 1.35; page-break-after: avoid; }
        .report-body p { margin: 0 0 13px; }
        .report-body ul, .report-body ol { padding-left: 23px; margin: 8px 0 15px; }
        .report-body li { margin: 4px 0; }
        .report-body table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 16px 0 22px; font-size: 10px; }
        .report-body thead { display: table-header-group; }
        .report-body tr { page-break-inside: avoid; }
        .report-body th, .report-body td { border: 1px solid #ddd; padding: 7px 6px; text-align: left; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
        .report-body th { background: #f4f4f4; font-weight: bold; }
        .report-body tbody tr:nth-child(even) { background: #fafafa; }
        .report-body code {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: .86em;
            color: #333;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 3px;
            padding: 1px 3px;
            overflow-wrap: anywhere;
            word-wrap: break-word;
            white-space: normal;
        }
        .report-body pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 10px 12px;
            margin: 13px 0;
        }
        .report-body pre code { font-family: 'DejaVu Sans Mono', monospace; font-size: .9em; background: transparent; border: 0; padding: 0; }
        .report-body a { color: #333; text-decoration: underline; }
        .report-body blockquote { border-left: 3px solid #ddd; padding-left: 13px; margin-left: 0; }
        .footer { border-top: 1px solid #ddd; padding-top: 8px; margin-top: 28px; font-size: 11px; color: #777; }
        @media print { body { padding: 0; max-width: none; } .masthead { margin-top: 0; } }

        /* PDF typography uses points; the public HTML keeps independent screen styles. */
        .pdf-report { max-width: none; padding: 0; font-size: 10.5pt; line-height: 1.5; }
        .pdf-report .masthead { padding-bottom: 13px; margin-bottom: 23px; }
        .pdf-report h1.document-title { font-size: 18pt; line-height: 1.3; }
        .pdf-report .report-body h1 { font-size: 16pt; }
        .pdf-report .report-body h2 { font-size: 13pt; }
        .pdf-report .report-body h3 { font-size: 11pt; }
        .pdf-report .report-body table {
            font-size: 9.2pt;
            line-height: 1.4;
            table-layout: fixed;
            margin: 14px 0 18px;
        }
        .pdf-report .report-body th,
        .pdf-report .report-body td { padding: 8px 6px; }
        /* Markdown produces a five-column worklog table, with no colgroup. */
        .pdf-report .work-report-body table th:nth-child(1) { width: 14%; }
        .pdf-report .work-report-body table th:nth-child(2) { width: 30%; }
        .pdf-report .work-report-body table th:nth-child(3) { width: 30%; }
        .pdf-report .work-report-body table th:nth-child(4) { width: 17%; }
        .pdf-report .work-report-body table th:nth-child(5) { width: 9%; }
        .pdf-report .report-body th:last-child,
        .pdf-report .report-body td:last-child { text-align: right; white-space: nowrap; }

        /* Browser-only presentation: PDF rules above never apply to this class. */
        .public-report-html {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            max-width: 1080px;
            padding: 40px 36px;
            font-size: 16px;
            line-height: 1.65;
        }
        .public-report-html .document-title { font-size: 28px; }
        .public-report-html .report-body { overflow-x: auto; }
        .public-report-html .report-body h1 { font-size: 24px; }
        .public-report-html .report-body h2 { font-size: 20px; }
        .public-report-html .report-body h3 { font-size: 17px; }
        .public-report-html .report-body > p,
        .public-report-html .report-body > ul,
        .public-report-html .report-body > ol,
        .public-report-html .report-body > h1,
        .public-report-html .report-body > h2,
        .public-report-html .report-body > h3 { max-width: 92ch; }
        .public-report-html .report-body table {
            table-layout: auto;
            min-width: 720px;
            font-size: 14px;
            line-height: 1.55;
            margin: 20px 0 26px;
        }
        .public-report-html .report-body th,
        .public-report-html .report-body td {
            padding: 11px 10px;
            vertical-align: top;
        }
        .public-report-html .report-body th { background: #f4f4f4; }
        .public-report-html .report-body td:first-child,
        .public-report-html .report-body th:first-child,
        .public-report-html .report-body td:last-child,
        .public-report-html .report-body th:last-child { white-space: nowrap; }
        .public-report-html .meta { font-size: 13px; }
        .public-report-html .footer { font-size: 12px; }
        @media screen and (max-width: 680px) {
            .public-report-html { padding: 24px 18px; }
            .public-report-html .document-title { font-size: 24px; }
        }
        @media print {
            .public-report-html {
                font-family: 'DejaVu Sans', sans-serif;
                padding: 0;
                max-width: none;
                font-size: 13px;
            }
            .public-report-html .report-body { overflow: visible; }
            .public-report-html .report-body table { min-width: 0; table-layout: fixed; font-size: 10px; }
            .public-report-html .report-body th,
            .public-report-html .report-body td { padding: 7px 6px; }
        }
    </style>
</head>
<body class="{{ $publicHtml ? 'public-report-html' : 'pdf-report' }}">
    <header class="masthead">
        <div class="eyebrow">TaskFlow</div>
        <h1 class="document-title">{{ $displayTitle }}</h1>
        @if($draft)<div class="draft">{{ str_starts_with(strtolower($locale), 'pl') ? 'Wersja robocza' : 'Draft' }}</div>@endif
        <p class="meta">{{ $projectName }} · {{ $displayPeriod }}</p>
        @if($people !== null && count($people) > 0)
            <p class="meta">{{ implode(' · ', $people) }}</p>
        @endif
    </header>
    <main class="report-body{{ $detailedReport ? ' work-report-body' : '' }}">{!! $contentHtml !!}</main>
    @if($publicHtml)<footer class="footer">TaskFlow</footer>@endif
</body>
</html>
