<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { DateFormatter, getLocalTimeZone, parseDate, today } from '@internationalized/date';
import { Clock3, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import WorklogDatePicker from '@/components/worklogs/WorklogDatePicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { TaskWorklogPage, TaskWorklogSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    entries: TaskWorklogPage;
    totalMinutes: number;
    canLog: boolean;
}>();

const baseUrl = computed(() => `/projects/${props.projectId}/tasks/${props.taskId}/worklogs`);
const editingId = ref<number | null>(null);
const editorOpen = ref(false);
const deletingId = ref<number | null>(null);
const deleteForm = useForm({});
const form = useForm({
    work_date: today(getLocalTimeZone()).toString(),
    timezone: getLocalTimeZone(),
    hours: 0,
    minutes: 0,
    description: '',
    is_creative: false,
});
const dateFormatter = new DateFormatter('en-US', { dateStyle: 'medium' });
watch(() => props.taskId, () => { closeEditor(); deletingId.value = null; });

function durationLabel(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return [hours ? `${hours}h` : '', remainder ? `${remainder}m` : ''].filter(Boolean).join(' ') || '0m';
}
function dateLabel(isoDate: string): string {
    return dateFormatter.format(parseDate(isoDate).toDate(getLocalTimeZone()));
}
function closeEditor(): void {
    editorOpen.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
}
function addEntry(): void {
    closeEditor();
    editorOpen.value = true;
}
function editEntry(entry: TaskWorklogSummary): void {
    editorOpen.value = true;
    editingId.value = entry.id;
    form.work_date = entry.work_date;
    form.hours = Math.floor(entry.duration_minutes / 60);
    form.minutes = entry.duration_minutes % 60;
    form.description = entry.description ?? '';
    form.is_creative = entry.is_creative;
    form.clearErrors();
}
function saveEntry(): void {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeEditor(),
        onError: () => toast.error('Could not save worklog. Check the form and try again.'),
    };
    if (editingId.value !== null) {
        form.patch(`${baseUrl.value}/${editingId.value}`, options);
    } else {
        form.post(baseUrl.value, options);
    }
}
function removeEntry(entry: TaskWorklogSummary): void {
    deleteForm.delete(`${baseUrl.value}/${entry.id}`, {
        preserveScroll: true,
        onSuccess: () => { deletingId.value = null; },
        onError: () => toast.error('Could not delete worklog entry.'),
    });
}
</script>

<template>
    <Card id="worklog">
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2"><Clock3 class="size-4" /> Worklog</CardTitle>
                    <CardDescription>{{ entries.total }} entries · {{ durationLabel(totalMinutes) }} logged</CardDescription>
                </div>
                <Button v-if="canLog && !editorOpen" type="button" size="sm" variant="outline" @click="addEntry">
                    <Plus class="size-4" /> Log work
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-5">
            <form v-if="canLog && editorOpen" class="space-y-4 rounded-lg border bg-muted/20 p-4" @submit.prevent="saveEntry">
                <h3 class="text-sm font-semibold">{{ editingId === null ? 'Log time' : 'Edit worklog entry' }}</h3>
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                    <div class="grid gap-1.5">
                        <Label>Work date</Label>
                        <WorklogDatePicker id="worklog-date" v-model="form.work_date" :max-date="today(getLocalTimeZone()).toString()"
                            :disabled="form.processing" :invalid="Boolean(form.errors.work_date)" />
                        <InputError :message="form.errors.work_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="worklog-hours">Hours</Label>
                        <Input id="worklog-hours" :model-value="form.hours" @update:model-value="(value) => form.hours = Number(value)" type="number" min="0" max="8" step="1" required :disabled="form.processing" />
                        <InputError :message="form.errors.hours" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="worklog-minutes">Minutes</Label>
                        <Input id="worklog-minutes" :model-value="form.minutes" @update:model-value="(value) => form.minutes = Number(value)" type="number" min="0" max="59" step="1" required :disabled="form.processing" />
                        <InputError :message="form.errors.minutes" />
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Daily limit: 8h per person across all tasks and projects. Existing entries for the selected date count toward the limit.</p>
                <div class="grid gap-1.5">
                    <Label for="worklog-description">Work performed / outcome <span class="text-xs text-muted-foreground">(optional)</span></Label>
                    <Textarea id="worklog-description" v-model="form.description" maxlength="4000" rows="3"
                        :disabled="form.processing" placeholder="Describe what you completed during this time" />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox id="worklog-creative" :model-value="form.is_creative" :disabled="form.processing"
                        @update:model-value="(checked) => form.is_creative = checked === true" />
                    <Label for="worklog-creative" class="cursor-pointer">Creative work <span class="font-normal text-muted-foreground">(optional reporting label)</span></Label>
                </div>
                <InputError :message="form.errors.is_creative" />
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" /> {{ form.processing ? 'Saving…' : 'Save worklog' }}
                    </Button>
                    <Button type="button" variant="outline" size="sm" :disabled="form.processing" @click="closeEditor">Cancel</Button>
                </div>
            </form>
            <div v-if="entries.data.length" class="divide-y">
                <article v-for="entry in entries.data" :key="entry.id" class="space-y-2 py-4 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <p class="text-sm font-medium">{{ dateLabel(entry.work_date) }} · {{ durationLabel(entry.duration_minutes) }}</p>
                            <p class="text-xs text-muted-foreground">{{ entry.author?.name ?? 'Deleted user' }}<span v-if="entry.is_creative"> · Creative work</span></p>
                        </div>
                        <div v-if="entry.can_update || entry.can_delete" class="flex gap-1">
                            <Button v-if="entry.can_update" type="button" variant="ghost" size="sm" :disabled="form.processing" @click="editEntry(entry)">
                                <Pencil class="size-3.5" /> Edit
                            </Button>
                            <Dialog v-if="entry.can_delete" :open="deletingId === entry.id" @update:open="(open) => deletingId = open ? entry.id : null">
                                <DialogTrigger as-child><Button type="button" variant="ghost" size="sm"><Trash2 class="size-3.5" /> Delete</Button></DialogTrigger>
                                <DialogContent>
                                    <DialogHeader><DialogTitle>Delete worklog entry?</DialogTitle><DialogDescription>This will permanently remove the logged time.</DialogDescription></DialogHeader>
                                    <DialogFooter><Button type="button" variant="destructive" :disabled="deleteForm.processing" @click="removeEntry(entry)">
                                        <LoaderCircle v-if="deleteForm.processing" class="size-4 animate-spin" /> Delete entry
                                    </Button></DialogFooter>
                                </DialogContent>
                            </Dialog>
                        </div>
                    </div>
                    <p v-if="entry.description" class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ entry.description }}</p>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">{{ canLog ? 'No time logged yet. Add an entry whenever you work on this task.' : 'No time logged for this task.' }}</p>
            <div v-if="entries.last_page > 1" class="flex items-center justify-between gap-3 border-t pt-4 text-sm">
                <span class="text-muted-foreground">Page {{ entries.current_page }} of {{ entries.last_page }}</span>
                <div class="flex gap-2">
                    <Button v-if="!entries.previous_page_url" size="sm" variant="outline" disabled>Previous</Button>
                    <Button v-else as-child size="sm" variant="outline">
                        <Link :href="entries.previous_page_url" preserve-scroll>Previous</Link>
                    </Button>
                    <Button v-if="!entries.next_page_url" size="sm" variant="outline" disabled>Next</Button>
                    <Button v-else as-child size="sm" variant="outline">
                        <Link :href="entries.next_page_url" preserve-scroll>Next</Link>
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
