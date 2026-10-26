// Report periods are calendar dates, not timestamps. UTC noon avoids local DST shifts.
function calendarDate(value: string): Date {
    return new Date(`${value}T12:00:00Z`);
}

export function formatReportPeriod(start: string, end: string, language: string = 'en'): string {
    const formatter = new Intl.DateTimeFormat(language, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    });
    const startDate = calendarDate(start);
    const endDate = calendarDate(end);

    // formatRange applies the locale's own order, separators and shared-date elision.
    // For example German uses day-month-year, unlike the English month-day-year.
    return formatter.formatRange(startDate, endDate);
}

export function formatReportTimestamp(isoDate: string, language: string = 'en'): string {
    return new Intl.DateTimeFormat(language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(isoDate));
}
