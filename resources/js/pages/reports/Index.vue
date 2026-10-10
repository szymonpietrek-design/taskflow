<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, FileText, LoaderCircle, Plus } from '@lucide/vue';
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import type { DateValue } from '@internationalized/date';
import { shallowRef, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import InputError from '@/components/InputError.vue';

type ReportRow = {
    id: number;
    title: string;
    period_start: string;
    period_end: string;
    sources: string[];
    is_generated: boolean;
    created_by: string | null;
    created_at: string | null;
};
const props = defineProps<{
    project: { id: number; name: string; key: string };
    can_create: boolean;
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
    { value: 'tasks', title: 'Tasks', description: 'Created or completed in this period' },
    { value: 'messages', title: 'Messages', description: 'Task discussions posted in this period' },
    { value: 'activity', title: 'Activity', description: 'Changes recorded in this period' },
];
const selected = shallowRef<string[]>(['tasks', 'messages', 'activity']);
const validPeriod = computed(() => Boolean(start.value && end.value && start.value.toString() <= end.value.toString()));
function changeSource(value: string, checked: boolean) {
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
                <p class="mt-1 text-sm text-muted-foreground">Save reporting periods and source selections for {{ project.name }}. AI summaries will be added in the next stage.</p>
            </div>
        </div>
        <div class="grid items-start gap-6" :class="can_create ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : ''">
            <section class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-medium">Saved reports</h2>
                    <span class="text-sm text-muted-foreground">{{ reports.total }} reports</span>
                </div>
                <Card v-if="reports.data.length === 0" class="border-dashed">
                    <CardContent class="flex flex-col items-center gap-2 py-10 text-center">
                        <FileText class="size-9 text-muted-foreground" />
                        <p class="font-medium">No reports yet</p>
                        <p class="text-sm text-muted-foreground">Create a reporting period to get started.</p>
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
                                    <span>{{ report.period_start }} – {{ report.period_end }}</span>
                                    <span>·</span>
                                    <span>{{ report.sources.map((s) => s === 'activity' ? 'Activity' : s === 'messages' ? 'Messages' : 'Tasks').join(', ') }}</span>
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
                    <CardDescription>Save your report settings now; generate its content later.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Form :action="baseUrl" method="post" class="space-y-4" v-slot="{ errors, processing }">
                        <div class="grid gap-2">
                            <Label for="report-title">Title *</Label>
                            <Input id="report-title" name="title" maxlength="160" required placeholder="Weekly project summary" />
                            <InputError :message="errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <Label>From *</Label>
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
                            <Label>To *</Label>
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
                            <Label>Include sources *</Label>
                            <label v-for="source in sources" :key="source.value" class="flex cursor-pointer items-start gap-2 rounded-md border px-3 py-2.5">
                                <input type="checkbox" name="sources[]" :value="source.value" :checked="selected.includes(source.value)"
                                    class="mt-0.5 size-4 accent-primary" @change="changeSource(source.value, ($event.target as HTMLInputElement).checked)" />
                                <span class="min-w-0"><span class="block text-sm font-medium">{{ source.title }}</span><span class="block text-xs text-muted-foreground">{{ source.description }}</span></span>
                            </label>
                            <InputError :message="errors.sources" />
                            <InputError :message="errors['sources.0']" />
                        </div>
                        <Button type="submit" class="w-full" :disabled="processing || !validPeriod || !selected.length">
                            <LoaderCircle v-if="processing" class="size-4 animate-spin" /><Plus v-else class="size-4" />
                            {{ processing ? 'Creating report…' : 'Create report draft' }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
