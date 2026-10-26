<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    FileText,
    LoaderCircle,
    Plus,
} from '@lucide/vue';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { shallowRef, computed, ref } from 'vue';
import { canIncludeVcsSource, normalizeReportSources, toggleReportSource } from '@/lib/reportSourceSelection';
import type { ReportFormatValue, ReportPeopleMode } from '@/lib/reportSourceSelection';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import InputError from '@/components/InputError.vue';
import { formatReportPeriod } from '@/lib/reportDates';

type ReportRow = {
    id: number;
    title: string;
    period_start: string;
    period_end: string;
    format: string;
    sources: string[];
    is_generated: boolean;
    published_at: string | null;
    created_by: string | null;
    created_at: string | null;
};
const props = defineProps<{
    project: { id: number; name: string; key: string };
    can_create: boolean;
    can_select_people: boolean;
    has_vcs_repository: boolean;
    contributors: { id: number; name: string }[];
    reports: {
        data: ReportRow[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();
const baseUrl = `/projects/${props.project.id}/reports`;
const formatter = computed(() => new DateFormatter(locale.value, { dateStyle: 'medium' }));
const start = shallowRef<DateValue | undefined>(
    parseDate(today(getLocalTimeZone()).subtract({ days: 30 }).toString()),
);
const end = shallowRef<DateValue | undefined>(today(getLocalTimeZone()));
const sources = [
    {
        value: 'tasks',
        title: 'fix.breadcrumb_tasks',
        description: 'fix.report_source_tasks',
    },
    { value: 'messages', title: 'fix.report_source_messages_title', description: 'fix.report_source_messages' },
    { value: 'activity', title: 'reports.taskHistoryTitle', description: 'reports.taskHistoryDescription' },
    { value: 'worklogs', title: 'fix.report_source_worklogs_title', description: 'fix.report_source_worklogs' },
    { value: 'vcs', title: 'reports.gitActivityTitle', description: 'reports.gitActivityDescription' },
];
const selected = shallowRef<string[]>([
    'tasks',
    'messages',
    'activity',
    'worklogs',
]);
const format = ref<ReportFormatValue>('project_summary');
const peopleMode = ref<ReportPeopleMode>('all');
const reportFormats: { value: ReportFormatValue; label: string; help: string }[] = [
    { value: 'project_summary', label: 'reports.projectReport', help: 'reports.summaryDescription' },
    { value: 'technical_report', label: 'reports.technicalReport', help: 'reports.technicalReportDescription' },
    { value: 'work_report', label: 'audit.detailed_report', help: 'reports.workReportDescription' },
];
const selectedPeople = ref<number[]>([]);
function togglePerson(id: number, checked: boolean) {
    selectedPeople.value = checked
        ? [...selectedPeople.value, id]
        : selectedPeople.value.filter((personId) => personId !== id);
}
function chooseFormat(value: ReportFormatValue) {
    format.value = value;
    selected.value = normalizeReportSources(selected.value, format.value, peopleMode.value, props.can_select_people, props.has_vcs_repository);
}
function choosePeopleMode(value: string) {
    peopleMode.value = value === 'selected' ? 'selected' : 'all';
    selected.value = normalizeReportSources(selected.value, format.value, peopleMode.value, props.can_select_people, props.has_vcs_repository);
}
const vcsUnavailableReason = computed(() => {
    if (!props.has_vcs_repository) return 'reports.vcsNoRepository';
    if (format.value === 'work_report') return 'reports.vcsWorkReportRestriction';
    if (!props.can_select_people) return 'reports.vcsPersonalRestriction';
    if (peopleMode.value === 'selected') return 'reports.vcsPeopleRestriction';
    return null;
});
const selectedSourcesText = computed(() => formatText('reports.selectedSources', { count: selected.value.length }));
const reportsCountText = computed(() => {
    const category = new Intl.PluralRules(locale.value).select(props.reports.total);
    const suffix = category === 'one' ? 'One' : category === 'few' ? 'Few' : 'Many';
    return formatText(`reports.count${suffix}`, { count: props.reports.total });
});
const sourceLabels: Record<string, string> = {
    tasks: 'fix.report_source_tasks_title',
    messages: 'fix.report_source_messages_title',
    activity: 'reports.taskHistoryTitle',
    worklogs: 'fix.report_source_worklogs_title',
    vcs: 'reports.gitActivityTitle',
};
const validPeriod = computed(() =>
    Boolean(
        start.value &&
        end.value &&
        start.value.toString() <= end.value.toString(),
    ),
);
function changeSource(value: string, checked: boolean) {
    selected.value = toggleReportSource(selected.value, value, checked, format.value, peopleMode.value, props.can_select_people, props.has_vcs_repository);
}
function formatDate(value?: DateValue) {
    return value
        ? formatter.value.format(value.toDate(getLocalTimeZone()))
        : t('audit.no_date');
}
defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
const { t, locale, formatText, translateLiteral } = useI18n();
</script>

<template>
    <Head :title="`Reports · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="`/projects/${project.id}`"
                    ><ArrowLeft class="size-4" /> {{ t('stage3.back_to_project') }}</Link
                >
            </Button>
            <Badge variant="secondary" class="w-fit">{{ project.key }}</Badge>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('stage3.reports') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ formatText('audit.reports_created_for', { project: project.name }) }}
                </p>
            </div>
        </div>
        <div
            class="grid items-start gap-6"
            :class="can_create ? 'lg:grid-cols-[minmax(0,1fr)_minmax(24rem,29rem)]' : ''"
        >
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">{{ t('stage3.saved_reports') }}</h2>
                    <span class="text-sm text-muted-foreground">{{ reportsCountText }}</span>
                </div>
                <Card v-if="reports.data.length === 0" class="border-dashed">
                    <CardContent
                        class="flex flex-col items-center gap-2 py-10 text-center"
                    >
                        <FileText class="size-9 text-muted-foreground" />
                        <p class="font-medium">{{ t('stage3.no_reports_yet') }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ t('audit.create_your_first_report') }}
                        </p>
                    </CardContent>
                </Card>
                <div v-else class="space-y-3">
                    <Link
                        v-for="report in reports.data"
                        :key="report.id"
                        :href="`${baseUrl}/${report.id}`"
                        class="block"
                    >
                        <Card
                            class="gap-2 py-4 transition-colors hover:bg-muted/30"
                        >
                            <CardContent class="space-y-2 px-5">
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <span class="font-semibold">{{
                                        report.title
                                    }}</span>
                                    <Badge
                                        :variant="
                                            report.published_at
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{
                                            report.published_at
                                                ? t('audit.published')
                                                : t('audit.draft')
                                        }}</Badge
                                    >
                                </div>
                                <div
                                    class="flex flex-wrap gap-2 text-xs text-muted-foreground"
                                >
                                    <span>{{
                                        report.format === 'work_report'
                                            ? t('audit.detailed_report')
                                            : report.format === 'technical_report'
                                                ? t('reports.technicalReport')
                                                : t('reports.projectReport')
                                    }}</span>
                                    <span>·</span>
                                    <span>{{
                                        formatReportPeriod(
                                            report.period_start,
                                            report.period_end,
                                        locale,
                                        )
                                    }}</span>
                                    <span>·</span>
                                    <span>{{
                                        report.sources
                                            .map((s) => t(sourceLabels[s] ?? s))
                                            .join(', ')
                                    }}</span>
                                </div>
                                <p
                                    v-if="report.created_by"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ t('fix.by') }} {{ report.created_by }}
                                </p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
                <div
                    v-if="reports.last_page > 1"
                    class="flex items-center justify-between gap-3 pt-2"
                >
                    <Button
                        v-if="reports.prev_page_url"
                        size="sm"
                        variant="outline"
                        as-child
                        ><Link :href="reports.prev_page_url"
                            >{{ t('stage3.previous') }}</Link
                        ></Button
                    >
                    <Button v-else size="sm" variant="outline" disabled
                        >{{ t('stage3.previous') }}</Button
                    >
                    <span class="text-xs text-muted-foreground"
                        >{{ formatText('audit.count_pages', { page: reports.current_page, pages: reports.last_page }) }}</span
                    >
                    <Button
                        v-if="reports.next_page_url"
                        size="sm"
                        variant="outline"
                        as-child
                        ><Link :href="reports.next_page_url">{{ t('stage3.next') }}</Link></Button
                    >
                    <Button v-else size="sm" variant="outline" disabled
                        >{{ t('stage3.next') }}</Button
                    >
                </div>
            </section>
            <Card v-if="can_create" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Plus class="size-4" /> {{ t('stage3.new_report') }}</CardTitle
                    >
                    <CardDescription
                        >{{ t('stage3.choose_a_period_and_sources') }}</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Form
                        :action="baseUrl"
                        method="post"
                        class="space-y-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label>{{ t('stage3.report_content') }}</Label>
                            <input type="hidden" name="format" :value="format" />
                            <RadioGroup
                                :model-value="format"
                                class="grid gap-2"
                                @update:model-value="(value) => chooseFormat(value === 'technical_report' || value === 'work_report' ? value : 'project_summary')"
                            >
                                <div
                                    v-for="option in reportFormats"
                                    :key="option.value"
                                    class="flex min-w-0 items-start gap-3 rounded-lg border px-3.5 py-3 transition-colors hover:bg-muted/20"
                                    :class="format === option.value ? 'border-primary/60 bg-primary/[0.04]' : 'border-border'"
                                >
                                    <RadioGroupItem
                                        :id="`report-format-${option.value}`"
                                        :value="option.value"
                                        class="mt-0.5 shrink-0"
                                    />
                                    <Label
                                        :for="`report-format-${option.value}`"
                                        class="!flex min-w-0 flex-1 cursor-pointer flex-col items-start gap-0.5"
                                    >
                                        <span class="text-sm font-medium leading-5">{{ t(option.label) }}</span>
                                        <span class="text-xs font-normal leading-5 text-muted-foreground">{{ t(option.help) }}</span>
                                    </Label>
                                </div>
                            </RadioGroup>
                            <InputError :message="errors.format" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ t('stage3.people') }}</Label>
                            <template v-if="can_select_people">
                                <RadioGroup
                                    :model-value="peopleMode"
                                    class="grid grid-cols-2 gap-2"
                                    @update:model-value="(value) => choosePeopleMode(value === 'selected' ? 'selected' : 'all')"
                                >
                                    <div
                                        v-for="opt in [
                                            {
                                                value: 'all',
                                                text: 'fix.report_all_contributors',
                                            },
                                            {
                                                value: 'selected',
                                                text: 'fix.report_selected_people',
                                            },
                                        ]"
                                        :key="opt.value"
                                        class="flex min-w-0 items-center gap-2.5 rounded-lg border px-3 py-3 transition-colors hover:bg-muted/20"
                                        :class="peopleMode === opt.value ? 'border-primary/60 bg-primary/[0.04]' : 'border-border'"
                                    >
                                        <RadioGroupItem
                                            :id="`report-people-${opt.value}`"
                                            :value="opt.value"
                                            class="shrink-0"
                                        />
                                        <Label
                                            :for="`report-people-${opt.value}`"
                                            class="!block min-w-0 cursor-pointer text-sm font-medium leading-5"
                                            >{{ t(opt.text) }}</Label
                                        >
                                    </div>
                                </RadioGroup>
                                <div
                                    v-if="peopleMode === 'selected'"
                                    class="h-32 space-y-1 overflow-y-auto rounded-lg border p-2"
                                >
                                    <div
                                        v-for="person in contributors"
                                        :key="person.id"
                                        class="flex h-8 items-center gap-2 rounded-md px-2 text-sm hover:bg-muted/30"
                                    >
                                        <Checkbox
                                            :id="`report-person-${person.id}`"
                                            :model-value="
                                                selectedPeople.includes(
                                                    person.id,
                                                )
                                            "
                                            @update:model-value="
                                                (checked) =>
                                                    togglePerson(
                                                        person.id,
                                                        checked === true,
                                                    )
                                            "
                                        />
                                        <Label
                                            :for="`report-person-${person.id}`"
                                            class="cursor-pointer font-normal"
                                            >{{ person.name }}</Label
                                        >
                                    </div>
                                    <input
                                        v-for="id in selectedPeople"
                                        :key="`person-${id}`"
                                        type="hidden"
                                        name="contributor_ids[]"
                                        :value="id"
                                    />
                                </div>
                                <p v-if="peopleMode === 'selected'" class="text-xs text-muted-foreground">
                                    {{ t('audit.only_the_selected_people_will_be_included') }}
                                </p>
                                <p v-if="peopleMode === 'selected' && selectedPeople.length === 0" class="text-xs text-destructive" role="status">
                                    {{ contributors.length ? t('reports.selectContributorHint') : t('reports.noContributorsHint') }}
                                </p>
                            </template>
                            <p v-else class="text-sm text-muted-foreground">
                                {{ t('audit.only_your_own_work_team_reports_require_project_management_permissions') }}
                            </p>
                            <InputError :message="errors.contributor_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="report-title"
                                >{{ t('audit.title') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage3.optional') }}</span
                                ></Label
                            >
                            <Input
                                id="report-title"
                                name="title"
                                maxlength="160"
                                :placeholder="t('audit.e_g_weekly_project_summary')"
                            />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ t('stage3.from') }}</Label>
                            <input
                                type="hidden"
                                name="period_start"
                                :value="start?.toString() ?? ''"
                            />
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full justify-start font-normal"
                                    >
                                        <CalendarDays class="size-4" />
                                        {{ formatDate(start) }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start"
                                    ><Calendar
                                        v-model="start"
                                        layout="month-and-year"
                                        initial-focus
                                /></PopoverContent>
                            </Popover>
                            <InputError :message="errors.period_start" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ t('stage3.to') }}</Label>
                            <input
                                type="hidden"
                                name="period_end"
                                :value="end?.toString() ?? ''"
                            />
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="w-full justify-start font-normal"
                                    >
                                        <CalendarDays class="size-4" />
                                        {{ formatDate(end) }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start"
                                    ><Calendar
                                        v-model="end"
                                        layout="month-and-year"
                                        initial-focus
                                /></PopoverContent>
                            </Popover>
                            <InputError :message="errors.period_end" />
                            <p
                                v-if="!validPeriod"
                                class="text-xs text-destructive"
                            >
                                {{ t('audit.the_end_date_must_not_precede_the_start_date') }}
                            </p>
                        </div>
                        <div class="grid gap-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <Label>{{ t('stage3.include_sources') }}</Label>
                                <span class="text-xs text-muted-foreground" aria-live="polite">{{ selectedSourcesText }}</span>
                            </div>
                            <input
                                v-for="source in selected"
                                :key="`selected-source-${source}`"
                                type="hidden"
                                name="sources[]"
                                :value="source"
                            />
                            <div class="divide-y overflow-hidden rounded-lg border">
                                <div
                                    v-for="source in sources"
                                    :key="source.value"
                                    class="flex min-w-0 items-start gap-3 px-3.5 py-3 transition-colors"
                                    :class="source.value === 'vcs' && vcsUnavailableReason ? 'bg-muted/20' : 'hover:bg-muted/20'"
                                >
                                    <Checkbox
                                        :id="`report-source-${source.value}`"
                                        class="mt-0.5 shrink-0"
                                        :model-value="selected.includes(source.value)"
                                        :disabled="
                                            (source.value === 'worklogs' && format === 'work_report') ||
                                            (source.value === 'vcs' && !canIncludeVcsSource(format, peopleMode, can_select_people, has_vcs_repository))
                                        "
                                        @update:model-value="
                                            (checked) => changeSource(source.value, checked === true)
                                        "
                                    />
                                    <Label
                                        :for="`report-source-${source.value}`"
                                        class="!flex min-w-0 flex-1 flex-col items-start gap-0.5"
                                        :class="source.value === 'vcs' && vcsUnavailableReason ? 'cursor-not-allowed' : 'cursor-pointer'"
                                    >
                                        <span class="text-sm font-medium leading-5">{{ t(source.title) }}</span>
                                        <span class="text-xs font-normal leading-5 text-muted-foreground">{{ t(source.description) }}</span>
                                        <span
                                            v-if="source.value === 'vcs' && vcsUnavailableReason"
                                            class="mt-1 text-xs font-normal leading-5 text-muted-foreground"
                                            role="note"
                                        >{{ t(vcsUnavailableReason) }}</span>
                                    </Label>
                                </div>
                            </div>
                            <p
                                v-if="format === 'work_report'"
                                class="text-xs text-muted-foreground"
                            >
                                {{ t('audit.worklogs_are_required_for_the_work_register') }}
                            </p>
                            <InputError :message="errors.sources" />
                            <InputError :message="errors['sources.0']" />
                        </div>
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="
                                processing ||
                                !validPeriod ||
                                !selected.length ||
                                (can_select_people &&
                                    peopleMode === 'selected' &&
                                    !selectedPeople.length)
                            "
                        >
                            <LoaderCircle
                                v-if="processing"
                                class="size-4 animate-spin"
                            /><Plus v-else class="size-4" />
                            {{ processing ? t('audit.creating') : t('audit.create_report') }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
