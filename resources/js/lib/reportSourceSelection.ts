/** UI rules for report sources. Server-side validation remains authoritative. */
export type ReportFormatValue = 'project_summary' | 'work_report';
export type ReportPeopleMode = 'all' | 'selected';

export function canIncludeVcsSource(
    format: ReportFormatValue,
    peopleMode: ReportPeopleMode,
    canSelectPeople: boolean,
    hasRepository = true,
): boolean {
    return hasRepository && format === 'project_summary' && peopleMode === 'all' && canSelectPeople;
}

export function normalizeReportSources(
    sources: readonly string[],
    format: ReportFormatValue,
    peopleMode: ReportPeopleMode,
    canSelectPeople: boolean,
    hasRepository = true,
): string[] {
    const normalized = [...new Set(sources)].filter(
        (source) => source !== 'vcs' || canIncludeVcsSource(format, peopleMode, canSelectPeople, hasRepository),
    );

    if (format === 'work_report' && !normalized.includes('worklogs')) {
        normalized.push('worklogs');
    }

    return normalized;
}

export function toggleReportSource(
    sources: readonly string[],
    value: string,
    checked: boolean,
    format: ReportFormatValue,
    peopleMode: ReportPeopleMode,
    canSelectPeople: boolean,
    hasRepository = true,
): string[] {
    if (value === 'vcs' && !canIncludeVcsSource(format, peopleMode, canSelectPeople, hasRepository)) {
        return normalizeReportSources(sources, format, peopleMode, canSelectPeople, hasRepository);
    }
    if (value === 'worklogs' && format === 'work_report' && !checked) {
        return normalizeReportSources(sources, format, peopleMode, canSelectPeople, hasRepository);
    }

    const updated = checked ? [...sources, value] : sources.filter((source) => source !== value);

    return normalizeReportSources(updated, format, peopleMode, canSelectPeople, hasRepository);
}
