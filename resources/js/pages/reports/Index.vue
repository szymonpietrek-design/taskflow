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
import { shallowRef, computed, ref, watch } from 'vue';
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
const formatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));
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
    { value: 'activity', title: 'fix.report_source_activity_title', description: 'fix.report_source_activity' },
    { value: 'worklogs', title: 'fix.report_source_worklogs_title', description: 'fix.report_source_worklogs' },
    { value: 'vcs', title: 'vcs.reportTitle', description: 'vcs.reportDescription' },
];
const selected = shallowRef<string[]>([
    'tasks',
    'messages',
    'activity',
    'worklogs',
]);
const format = ref<'project_summary' | 'work_report'>('project_summary');
const peopleMode = ref<'all' | 'selected'>('all');
const selectedPeople = ref<number[]>([]);
function togglePerson(id: number, checked: boolean) {
    selectedPeople.value = checked
        ? [...selectedPeople.value, id]
        : selectedPeople.value.filter((personId) => personId !== id);
}
function chooseFormat(value: 'project_summary' | 'work_report') {
    format.value = value;
    if (value === 'work_report') selected.value = selected.value.filter((source) => source !== 'vcs');
    if (value === 'work_report' && !selected.value.includes('worklogs')) {
        selected.value = [...selected.value, 'worklogs'];
    }
}
const sourceLabels: Record<string, string> = {
    tasks: 'fix.report_source_tasks_title',
    messages: 'fix.report_source_messages_title',
    activity: 'fix.report_source_activity_title',
    worklogs: 'fix.report_source_worklogs_title',
    vcs: 'vcs.reportTitle',
};
watch(peopleMode, (mode) => {
    if (mode === 'selected') selected.value = selected.value.filter((source) => source !== 'vcs');
});
const validPeriod = computed(() =>
    Boolean(
        start.value &&
        end.value &&
        start.value.toString() <= end.value.toString(),
    ),
);
function changeSource(value: string, checked: boolean) {
    if (value === 'vcs' && (format.value === 'work_report' || !props.can_select_people || peopleMode.value === 'selected')) return;
    if (format.value === 'work_report' && value === 'worklogs' && !checked)
        return;
    selected.value = checked
        ? [...selected.value, value]
        : selected.value.filter((source) => source !== value);
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
            :class="can_create ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : ''"
        >
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">{{ t('stage3.saved_reports') }}</h2>
                    <span class="text-sm text-muted-foreground"
                        >{{ reports.total }}
                        {{ reports.total === 1 ? t('audit.reports_nominative') : 'reports' }}</span
                    >
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
                                            : t('audit.summary')
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
                            <input
                                type="hidden"
                                name="format"
                                :value="format"
                            />
                            <div
                                class="flex items-start gap-3 rounded-lg border p-3"
                            >
                                <Checkbox
                                    id="report-detailed"
                                    class="mt-0.5 shrink-0"
                                    :model-value="format === 'work_report'"
                                    @update:model-value="
                                        (checked) =>
                                            chooseFormat(
                                                checked === true
                                                    ? 'work_report'
                                                    : 'project_summary',
                                            )
                                    "
                                />
                                <div class="min-w-0 flex-1 space-y-1">
                                    <Label
                                        for="report-detailed"
                                        class="block cursor-pointer text-sm leading-5 font-medium"
                                        >{{ t('stage3.include_detailed_worklog') }}</Label
                                    >
                                    <p
                                        class="text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ t('audit.add_a_dated_table_of_recorded_work_people_and_exact_hours') }}
                                    </p>
                                </div>
                            </div>
                            <InputError :message="errors.format" />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{ t('stage3.people') }}</Label>
                            <template v-if="can_select_people">
                                <RadioGroup
                                    :model-value="peopleMode"
                                    class="grid gap-2 sm:grid-cols-2"
                                    @update:model-value="
                                        (value) =>
                                            (peopleMode =
                                                value === 'selected'
                                                    ? 'selected'
                                                    : 'all')
                                    "
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
                                        class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                                    >
                                        <RadioGroupItem
                                            :id="`report-people-${opt.value}`"
                                            :value="opt.value"
                                        />
                                        <Label
                                            :for="`report-people-${opt.value}`"
                                            class="cursor-pointer font-normal"
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
                                <p
                                    v-if="peopleMode === 'selected'"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ t('audit.only_the_selected_people_will_be_included') }}
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
                            <p class="text-xs text-muted-foreground">
                                {{ t('audit.leave_blank_to_use_the_selected_dates') }}
                            </p>
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
                            <Label>{{ t('stage3.include_sources') }}</Label>
                            <input
                                v-for="source in selected"
                                :key="`selected-source-${source}`"
                                type="hidden"
                                name="sources[]"
                                :value="source"
                            />
                            <div
                                v-for="source in sources"
                                :key="source.value"
                                class="flex items-start gap-2 rounded-md border px-3 py-2.5"
                            >
                                <Checkbox
                                    :id="`report-source-${source.value}`"
                                    class="mt-0.5"
                                    :model-value="
                                        selected.includes(source.value)
                                    "
                                    :disabled="
                                        (source.value === 'worklogs' && format === 'work_report') ||
                                        (source.value === 'vcs' && (format === 'work_report' || !can_select_people || peopleMode === 'selected'))
                                    "
                                    @update:model-value="
                                        (checked) =>
                                            changeSource(
                                                source.value,
                                                checked === true,
                                            )
                                    "
                                />
                                <Label
                                    :for="`report-source-${source.value}`"
                                    class="min-w-0 cursor-pointer leading-normal"
                                >
                                    <span class="block text-sm font-medium">{{ t(source.title) }}</span>
                                    <span
                                        class="block text-xs font-normal text-muted-foreground"
                                        >{{ t(source.description) }}</span
                                    >
                                </Label>
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
