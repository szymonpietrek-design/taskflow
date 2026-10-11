<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, FileText, LoaderCircle, Plus } from '@lucide/vue';
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { shallowRef, computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Checkbox } from '@/components/ui/checkbox';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
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
const formatter = new DateFormatter('en-US', { dateStyle: 'medium' });
const start = shallowRef<DateValue | undefined>(parseDate(today(getLocalTimeZone()).subtract({ days: 30 }).toString()));
const end = shallowRef<DateValue | undefined>(today(getLocalTimeZone()));
const sources = [
    { value: 'tasks', title: 'Tasks', description: 'Created or completed tasks' },
    { value: 'messages', title: 'Messages', description: 'Task discussions' },
    { value: 'activity', title: 'Activity', description: 'Recorded changes' },
    { value: 'worklogs', title: 'Worklogs', description: 'Recorded time' },
];
const selected = shallowRef<string[]>(['tasks', 'messages', 'activity', 'worklogs']);
const format = ref<'project_summary' | 'work_report'>('project_summary');
const peopleMode = ref<'all' | 'selected'>('all');
const selectedPeople = ref<number[]>([]);
function togglePerson(id: number, checked: boolean) {
    selectedPeople.value = checked ? [...selectedPeople.value, id] : selectedPeople.value.filter((personId) => personId !== id);
}
function chooseFormat(value: 'project_summary' | 'work_report') {
    format.value = value;
    if (value === 'work_report' && !selected.value.includes('worklogs')) {
        selected.value = [...selected.value, 'worklogs'];
    }
}
const sourceLabels: Record<string, string> = { tasks: 'Tasks', messages: 'Messages', activity: 'Activity', worklogs: 'Worklogs' };
const validPeriod = computed(() => Boolean(start.value && end.value && start.value.toString() <= end.value.toString()));
function changeSource(value: string, checked: boolean) {
    if (format.value === 'work_report' && value === 'worklogs' && !checked) return;
    selected.value = checked ? [...selected.value, value] : selected.value.filter((source) => source !== value);
}
function formatDate(value?: DateValue) {
    return value ? formatter.format(value.toDate(getLocalTimeZone())) : 'Select a date';
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`Reports · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="`/projects/${project.id}`"><ArrowLeft class="size-4" /> Back to project</Link>
            </Button>
            <Badge variant="secondary" class="w-fit">{{ project.key }}</Badge>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Reports</h1>
                <p class="mt-1 text-sm text-muted-foreground">Create reports from project activity and worklogs for {{ project.name }}.</p>
            </div>
        </div>
        <div class="grid items-start gap-6" :class="can_create ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : ''">
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">Saved reports</h2>
                    <span class="text-sm text-muted-foreground">{{ reports.total }} {{ reports.total === 1 ? 'report' : 'reports' }}</span>
                </div>
                <Card v-if="reports.data.length === 0" class="border-dashed">
                    <CardContent class="flex flex-col items-center gap-2 py-10 text-center">
                        <FileText class="size-9 text-muted-foreground" />
                        <p class="font-medium">No reports yet</p>
                        <p class="text-sm text-muted-foreground">Create your first report.</p>
                    </CardContent>
                </Card>
                <div v-else class="space-y-3">
                    <Link v-for="report in reports.data" :key="report.id" :href="`${baseUrl}/${report.id}`" class="block">
                        <Card class="gap-2 py-4 transition-colors hover:bg-muted/30">
                            <CardContent class="space-y-2 px-5">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-semibold">{{ report.title }}</span>
                                    <Badge :variant="report.is_generated ? 'default' : 'secondary'">{{ report.is_generated ? 'Generated' : 'Draft' }}</Badge>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                    <span>{{ report.format === 'work_report' ? 'Detailed report' : 'Summary' }}</span>
                                    <span>·</span>
                                    <span>{{ formatReportPeriod(report.period_start, report.period_end) }}</span>
                                    <span>·</span>
                                    <span>{{ report.sources.map((s) => sourceLabels[s] ?? s).join(', ') }}</span>
                                </div>
                                <p v-if="report.created_by" class="text-xs text-muted-foreground">By {{ report.created_by }}</p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
                <div v-if="reports.last_page > 1" class="flex items-center justify-between gap-3 pt-2">
                    <Button v-if="reports.prev_page_url" size="sm" variant="outline" as-child><Link :href="reports.prev_page_url">Previous</Link></Button>
                    <Button v-else size="sm" variant="outline" disabled>Previous</Button>
                    <span class="text-xs text-muted-foreground">Page {{ reports.current_page }} of {{ reports.last_page }}</span>
                    <Button v-if="reports.next_page_url" size="sm" variant="outline" as-child><Link :href="reports.next_page_url">Next</Link></Button>
                    <Button v-else size="sm" variant="outline" disabled>Next</Button>
                </div>
            </section>
            <Card v-if="can_create" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"><Plus class="size-4" /> New report</CardTitle>
                    <CardDescription>Choose a period and sources.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form :action="baseUrl" method="post" class="space-y-4" v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label>Report content</Label>
                            <input type="hidden" name="format" :value="format" />
                            <div class="flex items-start gap-3 rounded-lg border p-3">
                                <Checkbox id="report-detailed" class="mt-0.5 shrink-0" :model-value="format === 'work_report'"
                                    @update:model-value="(checked) => chooseFormat(checked === true ? 'work_report' : 'project_summary')" />
                                <div class="min-w-0 flex-1 space-y-1">
                                    <Label for="report-detailed" class="block cursor-pointer text-sm leading-5 font-medium">Include detailed worklog</Label>
                                    <p class="text-xs leading-5 text-muted-foreground">Add a dated table of recorded work, people and exact hours.</p>
                                </div>
                            </div>
                            <InputError :message="errors.format" />
                        </div>
                        <div class="grid gap-2">
                            <Label>People</Label>
                            <template v-if="can_select_people">
                                <RadioGroup :model-value="peopleMode" class="grid gap-2 sm:grid-cols-2"
                                    @update:model-value="(value) => peopleMode = value === 'selected' ? 'selected' : 'all'">
                                    <div v-for="opt in [{ value: 'all', text: 'All contributors' }, { value: 'selected', text: 'Selected people' }]" :key="opt.value"
                                        class="flex items-center gap-2 rounded-lg border p-3 text-sm">
                                        <RadioGroupItem :id="`report-people-${opt.value}`" :value="opt.value" />
                                        <Label :for="`report-people-${opt.value}`" class="cursor-pointer font-normal">{{ opt.text }}</Label>
                                    </div>
                                </RadioGroup>
                                <div v-if="peopleMode === 'selected'" class="h-32 space-y-1 overflow-y-auto rounded-lg border p-2">
                                    <div v-for="person in contributors" :key="person.id" class="flex h-8 items-center gap-2 rounded-md px-2 text-sm hover:bg-muted/30">
                                        <Checkbox :id="`report-person-${person.id}`" :model-value="selectedPeople.includes(person.id)"
                                            @update:model-value="(checked) => togglePerson(person.id, checked === true)" />
                                        <Label :for="`report-person-${person.id}`" class="cursor-pointer font-normal">{{ person.name }}</Label>
                                    </div>
                                    <input v-for="id in selectedPeople" :key="`person-${id}`" type="hidden" name="contributor_ids[]" :value="id" />
                                </div>
                                <p v-if="peopleMode === 'selected'" class="text-xs text-muted-foreground">Only the selected people will be included.</p>
                            </template>
                            <p v-else class="text-sm text-muted-foreground">Only your own work. Team reports require project management permissions.</p>
                            <InputError :message="errors.contributor_ids" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="report-title">Title <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <Input id="report-title" name="title" maxlength="160" placeholder="e.g. Weekly project summary" />
                            <p class="text-xs text-muted-foreground">Leave blank to use the selected dates.</p>
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label>From</Label>
                            <input type="hidden" name="period_start" :value="start?.toString() ?? ''" />
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button type="button" variant="outline" class="w-full justify-start font-normal">
                                        <CalendarDays class="size-4" /> {{ formatDate(start) }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start"><Calendar v-model="start" layout="month-and-year" initial-focus /></PopoverContent>
                            </Popover>
                            <InputError :message="errors.period_start" />
                        </div>
                        <div class="grid gap-2">
                            <Label>To</Label>
                            <input type="hidden" name="period_end" :value="end?.toString() ?? ''" />
                            <Popover>
                                <PopoverTrigger as-child>
                                    <Button type="button" variant="outline" class="w-full justify-start font-normal">
                                        <CalendarDays class="size-4" /> {{ formatDate(end) }}
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-auto p-0" align="start"><Calendar v-model="end" layout="month-and-year" initial-focus /></PopoverContent>
                            </Popover>
                            <InputError :message="errors.period_end" />
                            <p v-if="!validPeriod" class="text-xs text-destructive">The end date must not precede the start date.</p>
                        </div>
                        <div class="grid gap-2">
                            <Label>Include sources</Label>
                            <input v-for="source in selected" :key="`selected-source-${source}`" type="hidden" name="sources[]" :value="source" />
                            <div v-for="source in sources" :key="source.value" class="flex items-start gap-2 rounded-md border px-3 py-2.5">
                                <Checkbox :id="`report-source-${source.value}`" class="mt-0.5"
                                    :model-value="selected.includes(source.value)"
                                    :disabled="format === 'work_report' && source.value === 'worklogs'"
                                    @update:model-value="(checked) => changeSource(source.value, checked === true)" />
                                <Label :for="`report-source-${source.value}`" class="min-w-0 cursor-pointer leading-normal">
                                    <span class="block text-sm font-medium">{{ source.title }}</span>
                                    <span class="block text-xs font-normal text-muted-foreground">{{ source.description }}</span>
                                </Label>
                            </div>
                            <p v-if="format === 'work_report'" class="text-xs text-muted-foreground">Worklogs are required for the work register.</p>
                            <InputError :message="errors.sources" />
                            <InputError :message="errors['sources.0']" />
                        </div>
                        <Button type="submit" class="w-full" :disabled="processing || !validPeriod || !selected.length || (can_select_people && peopleMode === 'selected' && !selectedPeople.length)">
                            <LoaderCircle v-if="processing" class="size-4 animate-spin" /><Plus v-else class="size-4" />
                            {{ processing ? 'Creating…' : 'Create report' }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
