<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Check, Pencil, Plus, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { WorkspaceSummary } from '@/types';

type MemberPreview = { id: number; name: string };
type MemberGroup = { members: MemberPreview[]; remaining: number };

const props = defineProps<{
    memberPreviews: Record<number, MemberGroup>;
}>();

const page = usePage();
const workspaces = computed(() => page.props.workspace.items);
const { t, formatText, countLabel } = useI18n();
const dialogOpen = ref(false);
const editingWorkspace = ref<WorkspaceSummary | null>(null);
const workspaceForm = useForm({ name: '' });

const editing = computed(() => editingWorkspace.value !== null);

function openCreate(): void {
    workspaceForm.reset();
    workspaceForm.clearErrors();
    editingWorkspace.value = null;
    dialogOpen.value = true;
}

function openRename(workspace: WorkspaceSummary): void {
    if (workspace.role !== 'owner' && workspace.role !== 'admin') return;
    workspaceForm.reset();
    workspaceForm.clearErrors();
    workspaceForm.name = workspace.name;
    editingWorkspace.value = workspace;
    dialogOpen.value = true;
}

function closeDialog(): void {
    if (workspaceForm.processing) return;
    dialogOpen.value = false;
    editingWorkspace.value = null;
    workspaceForm.reset();
    workspaceForm.clearErrors();
}

function onDialogChange(open: boolean): void {
    if (!open) closeDialog();
    else dialogOpen.value = true;
}

function submitWorkspace(): void {
    if (workspaceForm.processing) return;
    workspaceForm.name = workspaceForm.name.trim();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            // The POST also switches the active workspace; PATCH preserves it.
            dialogOpen.value = false;
            editingWorkspace.value = null;
            workspaceForm.reset();
            workspaceForm.clearErrors();
        },
    };
    if (editingWorkspace.value) {
        workspaceForm.patch(`/workspaces/${editingWorkspace.value.id}`, options);
    } else {
        workspaceForm.post('/workspaces', options);
    }
}

function roleLabel(role: WorkspaceSummary['role']): string {
    return t(role === 'owner' ? 'fix.owners' : role === 'admin' ? 'fix.admin' : 'fix.member');
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Workspaces', href: '/workspaces' }],
    },
});
</script>

<template>
    <Head :title="t('stage2.workspaces')" />

    <div class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-7 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <h1 class="text-xl font-semibold tracking-tight">{{ t('stage2.workspaces') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('stage2.create_a_workspace_or_choose_where_you_want_to_work') }}
                </p>
            </div>
            <Button type="button" @click="openCreate">
                <Plus class="size-4" /> {{ t('stage2.new_workspace') }}
            </Button>
        </div>

        <section class="space-y-3">
            <div>
                <h2 class="text-base font-medium">{{ t('stage2.your_workspaces') }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ countLabel('workspace', workspaces.length) }}
                </p>
            </div>

            <Card v-if="workspaces.length" class="gap-0 py-0">
                <CardContent class="divide-y p-0">
                    <div
                        v-for="workspaceItem in workspaces"
                        :key="workspaceItem.id"
                        class="flex flex-wrap items-center gap-4 px-4 py-5 transition-colors hover:bg-muted/20 sm:px-6"
                    >
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-lg border bg-muted/50">
                            <Users class="size-5 text-muted-foreground" />
                        </div>

                        <div class="min-w-48 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="min-w-0 break-words font-semibold">
                                    {{ workspaceItem.name }}
                                </span>
                                <Badge v-if="workspaceItem.is_current" variant="secondary">
                                    <Check class="size-3" /> {{ t('stage2.current') }}
                                </Badge>
                            </div>
                            <p class="break-words text-sm text-muted-foreground">
                                {{ roleLabel(workspaceItem.role) }} · {{ workspaceItem.slug }}
                            </p>
                        </div>

                        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
                            <TooltipProvider :delay-duration="150">
                                <div
                                    v-if="props.memberPreviews[workspaceItem.id]?.members.length"
                                    class="mr-2 flex shrink-0 items-center -space-x-2"
                                    :aria-label="formatText('fix.members_of', { workspace: workspaceItem.name })"
                                >
                                    <Tooltip
                                        v-for="member in props.memberPreviews[workspaceItem.id]?.members ?? []"
                                        :key="member.id"
                                    >
                                        <TooltipTrigger as-child>
                                            <span
                                                class="inline-flex shrink-0 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                :aria-label="member.name"
                                                tabindex="0"
                                            >
                                                <UserAvatar :user-id="member.id" :name="member.name" compact stacked />
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>{{ member.name }}</TooltipContent>
                                    </Tooltip>
                                    <Tooltip v-if="props.memberPreviews[workspaceItem.id]?.remaining">
                                        <TooltipTrigger as-child>
                                            <span
                                                class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground ring-2 ring-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                tabindex="0"
                                                :aria-label="formatText('fix.more_members', { count: props.memberPreviews[workspaceItem.id]?.remaining ?? 0 })"
                                            >
                                                +{{ props.memberPreviews[workspaceItem.id]?.remaining ?? 0 }}
                                            </span>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            {{ formatText('fix.more_members', { count: props.memberPreviews[workspaceItem.id]?.remaining ?? 0 }) }}
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </TooltipProvider>

                            <Button
                                v-if="workspaceItem.role === 'owner' || workspaceItem.role === 'admin'"
                                type="button"
                                variant="outline"
                                size="sm"
                                :aria-label="formatText('workspaces.rename_aria', { workspace: workspaceItem.name })"
                                @click="openRename(workspaceItem)"
                            >
                                <Pencil class="size-4" /> {{ t('workspaces.edit_name') }}
                            </Button>

                            <Button
                                v-if="workspaceItem.role === 'owner' || workspaceItem.role === 'admin'"
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link :href="`/workspaces/${workspaceItem.id}/members`">
                                    <Users class="size-4" /> {{ t('stage2.members') }}
                                </Link>
                            </Button>

                            <Form
                                v-if="!workspaceItem.is_current"
                                :action="`/workspaces/${workspaceItem.id}/current`"
                                method="put"
                                v-slot="{ processing }"
                            >
                                <Button variant="outline" size="sm" :disabled="processing">
                                    {{ t('stage2.use_workspace') }}
                                </Button>
                            </Form>
                            <Button v-else variant="ghost" size="sm" disabled>
                                {{ t('stage2.selected') }}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card v-else class="border-dashed">
                <CardContent class="flex flex-col items-center py-10 text-center">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted">
                        <Users class="size-5 text-muted-foreground" />
                    </div>
                    <p class="font-medium">{{ t('stage2.no_workspaces_yet') }}</p>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                        {{ t('stage2.create_your_first_workspace_to_start_organizing_projects_and_tasks') }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <Dialog :open="dialogOpen" @update:open="onDialogChange">
            <DialogContent :show-close-button="!workspaceForm.processing">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? t('workspaces.edit_name_title') : t('stage2.new_workspace') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editing ? t('workspaces.edit_name_description') : t('workspaces.create_description') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitWorkspace">
                    <div class="grid gap-2">
                        <Label for="workspace-dialog-name">{{ t('workspaces.workspace_name') }}</Label>
                        <Input
                            id="workspace-dialog-name"
                            v-model="workspaceForm.name"
                            type="text"
                            maxlength="120"
                            required
                            autofocus
                            autocomplete="organization"
                            :placeholder="t('audit.acme_team')"
                            :aria-invalid="Boolean(workspaceForm.errors.name)"
                        />
                        <InputError :message="workspaceForm.errors.name" />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" :disabled="workspaceForm.processing" @click="closeDialog">
                            {{ t('stage2.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="workspaceForm.processing || !workspaceForm.name.trim()">
                            {{ editing ? t('stage2.save_changes') : t('stage2.create_workspace') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
