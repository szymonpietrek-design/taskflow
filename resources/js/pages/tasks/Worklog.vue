<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Link, useForm } from '@inertiajs/vue3';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
    today,
} from '@internationalized/date';
import { Clock3, LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WorklogDatePicker from '@/components/worklogs/WorklogDatePicker.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { TaskWorklogPage, TaskWorklogSummary } from '@/types/task';

const props = defineProps<{
    projectId: number;
    taskId: number;
    entries: TaskWorklogPage;
    totalMinutes: number;
    canLog: boolean;
}>();

const baseUrl = computed(
    () => `/projects/${props.projectId}/tasks/${props.taskId}/worklogs`,
);
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
const dateFormatter = computed(() => new DateFormatter(locale.value === 'pl' ? 'pl-PL' : 'en-US', { dateStyle: 'medium' }));
watch(
    () => props.taskId,
    () => {
        closeEditor();
        deletingId.value = null;
    },
);

function durationLabel(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return (
        [hours ? `${hours}h` : '', remainder ? `${remainder}m` : '']
            .filter(Boolean)
            .join(' ') || '0m'
    );
}
function dateLabel(isoDate: string): string {
    return dateFormatter.value.format(parseDate(isoDate).toDate(getLocalTimeZone()));
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
        onError: () =>
            toast.error(
                t('audit.could_not_save_worklog_check_the_form_and_try_again'),
            ),
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
        onSuccess: () => {
            deletingId.value = null;
        },
        onError: () => toast.error(t('audit.could_not_delete_worklog_entry')),
    });
}
const { t, locale, countLabel, formatText } = useI18n();
</script>

<template>
    <Card id="worklog">
        <CardHeader>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2"
                        ><Clock3 class="size-4" /> {{ t('stage2.worklog') }}</CardTitle
                    >
                    <CardDescription
                        >{{ countLabel('entry', entries.total) }} ·
                        {{ durationLabel(totalMinutes) }}
                        {{ t('followup.logged') }}</CardDescription
                    >
                </div>
                <Button
                    v-if="canLog && !editorOpen"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="addEntry"
                >
                    <Plus class="size-4" /> {{ t('stage2.log_work') }}
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-5">
            <form
                v-if="canLog && editorOpen"
                class="space-y-4 rounded-lg border bg-muted/20 p-4"
                @submit.prevent="saveEntry"
            >
                <h3 class="text-sm font-semibold">
                    {{ editingId === null ? t('fix.worklog_add') : t('fix.worklog_edit') }}
                </h3>
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_6rem_6rem]">
                    <div class="grid gap-1.5">
                        <Label>{{ t('stage2.work_date') }}</Label>
                        <WorklogDatePicker
                            id="worklog-date"
                            v-model="form.work_date"
                            :max-date="today(getLocalTimeZone()).toString()"
                            :disabled="form.processing"
                            :invalid="Boolean(form.errors.work_date)"
                        />
                        <InputError :message="form.errors.work_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="worklog-hours">{{ t('stage2.hours') }}</Label>
                        <Input
                            id="worklog-hours"
                            :model-value="form.hours"
                            @update:model-value="
                                (value) => (form.hours = Number(value))
                            "
                            type="number"
                            min="0"
                            max="8"
                            step="1"
                            required
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.hours" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="worklog-minutes">{{ t('stage2.minutes') }}</Label>
                        <Input
                            id="worklog-minutes"
                            :model-value="form.minutes"
                            @update:model-value="
                                (value) => (form.minutes = Number(value))
                            "
                            type="number"
                            min="0"
                            max="59"
                            step="1"
                            required
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.minutes" />
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('stage2.daily_limit_8h_per_person_across_all_tasks_and_projects_existing_entries_fo') }}
                </p>
                <div class="grid gap-1.5">
                    <Label for="worklog-description"
                        >{{ t('stage2.work_performed_outcome') }}
                        <span class="font-normal text-muted-foreground"
                            >{{ t('stage2.optional') }}</span
                        ></Label
                    >
                    <Textarea
                        id="worklog-description"
                        v-model="form.description"
                        maxlength="4000"
                        rows="3"
                        :disabled="form.processing"
                        :placeholder="t('stage2.describe_what_you_completed_during_this_time')"
                    />
                    <InputError :message="form.errors.description" />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox
                        id="worklog-creative"
                        :model-value="form.is_creative"
                        :disabled="form.processing"
                        @update:model-value="
                            (checked) => (form.is_creative = checked === true)
                        "
                    />
                    <Label for="worklog-creative" class="cursor-pointer"
                        >{{ t('stage2.creative_work') }}
                        <span class="font-normal text-muted-foreground"
                            >{{ t('stage2.optional') }}</span
                        ></Label
                    >
                </div>
                <InputError :message="form.errors.is_creative" />
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" size="sm" :disabled="form.processing">
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                        />
                        {{ form.processing ? t('audit.saving') : t('audit.save_worklog') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="form.processing"
                        @click="closeEditor"
                        >{{ t('stage2.cancel') }}</Button
                    >
                </div>
            </form>
            <div v-if="entries.data.length" class="divide-y">
                <article
                    v-for="entry in entries.data"
                    :key="entry.id"
                    class="space-y-2 py-4 first:pt-0 last:pb-0"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="flex min-w-0 items-start gap-2.5">
                            <UserAvatar v-if="entry.author" :user-id="entry.author.id" :name="entry.author.name" small />
                            <div class="min-w-0 space-y-1">
                                <p class="text-sm font-medium">
                                    {{ dateLabel(entry.work_date) }} ·
                                    {{ durationLabel(entry.duration_minutes) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ entry.author?.name ?? t('fix.deleted_user') }}
                                    <span v-if="entry.is_creative"> · {{ t('stage2.creative_work') }}</span>
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="entry.can_update || entry.can_delete"
                            class="flex shrink-0 items-center gap-1"
                        >
                            <TooltipProvider :delay-duration="150">
                                <Tooltip v-if="entry.can_update">
                                    <TooltipTrigger as-child>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon-sm"
                                            class="text-muted-foreground hover:text-foreground"
                                            :aria-label="`${t('stage2.edit')} — ${dateLabel(entry.work_date)}`"
                                            :disabled="form.processing"
                                            @click="editEntry(entry)"
                                        >
                                            <Pencil class="size-4" aria-hidden="true" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent side="top">{{ t('stage2.edit') }}</TooltipContent>
                                </Tooltip>
                                <Dialog
                                    v-if="entry.can_delete"
                                    :open="deletingId === entry.id"
                                    @update:open="
                                        (open) =>
                                            (deletingId = open ? entry.id : null)
                                    "
                                >
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <DialogTrigger as-child>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="text-muted-foreground hover:text-destructive"
                                                    :aria-label="`${t('stage2.delete')} — ${dateLabel(entry.work_date)}`"
                                                    :disabled="deleteForm.processing"
                                                >
                                                    <Trash2 class="size-4" aria-hidden="true" />
                                                </Button>
                                            </DialogTrigger>
                                        </TooltipTrigger>
                                        <TooltipContent side="top">{{ t('stage2.delete') }}</TooltipContent>
                                    </Tooltip>
                                    <DialogContent>
                                        <DialogHeader
                                            ><DialogTitle
                                                >{{ t('stage2.delete_worklog_entry') }}</DialogTitle
                                            ><DialogDescription
                                                >{{ t('audit.this_will_permanently_remove_the_logged_time') }}</DialogDescription
                                            ></DialogHeader
                                        >
                                        <DialogFooter
                                            ><Button
                                                type="button"
                                                variant="destructive"
                                                :disabled="deleteForm.processing"
                                                @click="removeEntry(entry)"
                                            >
                                                <LoaderCircle
                                                    v-if="deleteForm.processing"
                                                    class="size-4 animate-spin"
                                                />
                                                {{ t('stage2.delete_entry') }}
                                            </Button></DialogFooter
                                        >
                                    </DialogContent>
                                </Dialog>
                            </TooltipProvider>
                        </div>
                    </div>
                    <p
                        v-if="entry.description"
                        class="text-sm leading-relaxed break-words whitespace-pre-wrap"
                    >
                        {{ entry.description }}
                    </p>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                {{
                    canLog
                        ? t('followup.no_time_add')
                        : t('followup.no_time')
                }}
            </p>
            <div
                v-if="entries.last_page > 1"
                class="flex items-center justify-between gap-3 border-t pt-4 text-sm"
            >
                <span class="text-muted-foreground"
                    >{{ formatText('fix.page_of', { page: entries.current_page, pages: entries.last_page }) }}</span
                >
                <div class="flex gap-2">
                    <Button
                        v-if="!entries.previous_page_url"
                        size="sm"
                        variant="outline"
                        disabled
                        >{{ t('stage2.previous') }}</Button
                    >
                    <Button v-else as-child size="sm" variant="outline">
                        <Link :href="entries.previous_page_url" preserve-scroll
                            >{{ t('stage2.previous') }}</Link
                        >
                    </Button>
                    <Button
                        v-if="!entries.next_page_url"
                        size="sm"
                        variant="outline"
                        disabled
                        >{{ t('stage2.next') }}</Button
                    >
                    <Button v-else as-child size="sm" variant="outline">
                        <Link :href="entries.next_page_url" preserve-scroll
                            >{{ t('stage2.next') }}</Link
                        >
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
