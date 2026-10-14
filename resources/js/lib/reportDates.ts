const shortMonth = (locale: string) => new Intl.DateTimeFormat(locale, {
    month: 'short',
    timeZone: 'UTC',
});
const monthDay = (locale: string) => new Intl.DateTimeFormat(locale, {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});
const fullDate = (locale: string) => new Intl.DateTimeFormat(locale, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
});

// Report periods are calendar dates, not timestamps: keep them independent of the browser timezone.
function calendarDate(value: string): Date {
    return new Date(`${value}T12:00:00Z`);
}

export function formatReportPeriod(start: string, end: string, language: string = 'en'): string {
    const locale = language === 'pl' ? 'pl-PL' : 'en-US';
    if (start === end) return fullDate(locale).format(calendarDate(start));

    const startDate = calendarDate(start);
    const endDate = calendarDate(end);
    const startYear = startDate.getUTCFullYear();
    const endYear = endDate.getUTCFullYear();

    if (language === 'pl') {
        return `${fullDate(locale).format(startDate)} – ${fullDate(locale).format(endDate)}`;
    }
    if (startYear !== endYear) {
        return `${fullDate(locale).format(startDate)} – ${fullDate(locale).format(endDate)}`;
    }
    if (startDate.getUTCMonth() !== endDate.getUTCMonth()) {
        return `${monthDay(locale).format(startDate)} – ${fullDate(locale).format(endDate)}`;
    }
    return `${shortMonth(locale).format(startDate)} ${startDate.getUTCDate()}–${endDate.getUTCDate()}, ${endYear}`;
}

export function formatReportTimestamp(isoDate: string, language: string = 'en'): string {
    return new Intl.DateTimeFormat(language === 'pl' ? 'pl-PL' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(isoDate));
}
