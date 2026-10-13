const shortMonth = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    timeZone: 'UTC',
});
const monthDay = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});
const fullDate = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    timeZone: 'UTC',
});

// Report periods are calendar dates, not timestamps: keep them independent of the browser timezone.
function calendarDate(value: string): Date {
    return new Date(`${value}T12:00:00Z`);
}

export function formatReportPeriod(start: string, end: string): string {
    if (start === end) return fullDate.format(calendarDate(start));

    const startDate = calendarDate(start);
    const endDate = calendarDate(end);
    const startYear = startDate.getUTCFullYear();
    const endYear = endDate.getUTCFullYear();

    if (startYear !== endYear) {
        return `${fullDate.format(startDate)} – ${fullDate.format(endDate)}`;
    }
    if (startDate.getUTCMonth() !== endDate.getUTCMonth()) {
        return `${monthDay.format(startDate)} – ${fullDate.format(endDate)}`;
    }
    return `${shortMonth.format(startDate)} ${startDate.getUTCDate()}–${endDate.getUTCDate()}, ${endYear}`;
}

export function formatReportTimestamp(isoDate: string): string {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(isoDate));
}
