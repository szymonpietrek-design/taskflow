<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Archive, ArchiveRestore, Check, Pencil, Plus, Settings2, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import ProjectFormDialog from '@/components/ProjectFormDialog.vue';
import ProjectIdentity from '@/components/ProjectIdentity.vue';
import type { WorkspaceProjectSummary } from '@/types/project';
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
    projectsByWorkspace: Record<number, WorkspaceProjectSummary[]>;
    archivedProjectsByWorkspace: Record<number, WorkspaceProjectSummary[]>;
}>();

const page = usePage();
const workspaces = computed(() => page.props.workspace.items);
const { t, formatText, countLabel } = useI18n();
const dialogOpen = ref(false);
const editingWorkspace = ref<WorkspaceSummary | null>(null);
const workspaceForm = useForm({ name: '' });
const projectDialogOpen = ref(false);
const projectWorkspaceId = ref<number | null>(null);
const editingProject = ref<WorkspaceProjectSummary | null>(null);
// Prefer the latest Inertia props after uploading/removing a logo. Keeping only
// the object captured when opening the dialog leaves a stale logo thumbnail.
const currentEditingProject = computed<WorkspaceProjectSummary | null>(() => {
    const selected = editingProject.value;
    const workspaceId = projectWorkspaceId.value;
    if (!selected || workspaceId === null) return null;
    return props.projectsByWorkspace[workspaceId]?.find(project => project.id === selected.id)
        ?? props.archivedProjectsByWorkspace[workspaceId]?.find(project => project.id === selected.id)
        ?? selected;
});
const confirmingProject = ref<{ workspaceId: number; project: WorkspaceProjectSummary; action: 'archive' | 'restore' } | null>(null);
const projectActionBusy = ref(false);

function confirmProjectAction(workspaceId: number, project: WorkspaceProjectSummary, action: 'archive' | 'restore'): void {
    if (!project.can_archive || projectActionBusy.value) return;
    confirmingProject.value = { workspaceId, project, action };
}

function onProjectActionDialogChange(open: boolean): void {
    if (!open && !projectActionBusy.value) confirmingProject.value = null;
}

function submitProjectAction(): void {
    const selected = confirmingProject.value;
    if (!selected || projectActionBusy.value) return;
    router.patch(`/workspaces/${selected.workspaceId}/projects/${selected.project.id}/${selected.action}`, {}, {
        preserveScroll: true,
        onStart: () => { projectActionBusy.value = true; },
        onSuccess: () => { confirmingProject.value = null; },
        onFinish: () => { projectActionBusy.value = false; },
    });
}

function archivedCount(count: number): string {
    const rule = new Intl.PluralRules(page.props.locale).select(count);
    const form = rule === 'one' ? 'One' : rule === 'few' ? 'Few' : 'Many';
    return formatText(`workspaceProjects.archivedCount${form}`, { count });
}

function openProjectDialog(workspaceId: number, project: WorkspaceProjectSummary | null = null): void {
    projectWorkspaceId.value = workspaceId;
    editingProject.value = project;
    projectDialogOpen.value = true;
}

function projectCount(count: number): string {
    const rule = new Intl.PluralRules(page.props.locale).select(count);
    const form = rule === 'one' ? 'One' : rule === 'few' ? 'Few' : 'Many';
    return formatText(`workspaceProjects.count${form}`, { count });
}


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
                    <div v-for="workspaceItem in workspaces" :key="workspaceItem.id" class="px-4 py-5 sm:px-6">
                      <div class="flex flex-wrap items-center gap-4">
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
                                    <Users class="size-4" /> {{ t('team.workspaceAdmins') }}
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

                      <section class="mt-5 space-y-3 border-t pt-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="space-y-0.5">
                                <h3 class="text-sm font-semibold">{{ t('workspaceProjects.title') }}</h3>
                                <p class="text-xs text-muted-foreground">{{ projectCount(props.projectsByWorkspace[workspaceItem.id]?.length ?? 0) }}</p>
                            </div>
                            <Button v-if="workspaceItem.role === 'owner' || workspaceItem.role === 'admin'" type="button" size="sm" variant="outline" @click="openProjectDialog(workspaceItem.id)">
                                <Plus class="size-4" /> {{ t('workspaceProjects.add') }}
                            </Button>
                        </div>

                        <div v-if="props.projectsByWorkspace[workspaceItem.id]?.length" class="divide-y rounded-lg border">
                            <div v-for="project in props.projectsByWorkspace[workspaceItem.id]" :key="project.id" class="flex flex-wrap items-center gap-3 px-3 py-3 transition-colors hover:bg-muted/25 focus-within:bg-muted/25 sm:px-4">
                                <ProjectIdentity :logo-url="project.logo_url" :icon="project.icon" :color="project.color" :type="project.type" />
                                <div class="min-w-0 flex-1 space-y-1">
                                    <Link :href="`/projects/${project.id}`" class="break-words text-sm font-medium hover:underline">{{ project.name }}</Link>
                                    <p class="flex flex-wrap items-center gap-1 text-xs text-muted-foreground">
                                        <span>{{ project.key }}</span><span>·</span>
                                        <span>{{ project.type === 'application' ? t('stage2.application') : t('stage2.standard') }}</span>

                                    </p>
                                </div>
                                <TooltipProvider v-if="project.can_edit" :delay-duration="150">
                                    <div class="flex shrink-0 items-center gap-1" role="group" :aria-label="project.name">
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button type="button" size="icon-sm" variant="ghost"
                                                    :aria-label="`${t('workspaceProjects.edit')}: ${project.name}`"
                                                    @click="openProjectDialog(workspaceItem.id, project)">
                                                    <Pencil class="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('workspaceProjects.edit') }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button as-child size="icon-sm" variant="ghost">
                                                    <Link :href="`/projects/${project.id}/statuses`"
                                                        :aria-label="`${t('workspaceProjects.statuses')}: ${project.name}`">
                                                        <Settings2 class="size-4" />
                                                    </Link>
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('workspaceProjects.statuses') }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip v-if="project.can_archive">
                                            <TooltipTrigger as-child>
                                                <Button type="button" size="icon-sm" variant="ghost"
                                                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:text-destructive"
                                                    :aria-label="`${t('workspaceProjects.archive')}: ${project.name}`"
                                                    @click="confirmProjectAction(workspaceItem.id, project, 'archive')">
                                                    <Archive class="size-4" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>{{ t('workspaceProjects.archive') }}</TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>
                            </div>
                        </div>
                        <p v-else class="rounded-lg border border-dashed px-4 py-5 text-sm text-muted-foreground">{{ t('workspaceProjects.empty') }}</p>
                      </section>

                      <section v-if="props.archivedProjectsByWorkspace[workspaceItem.id]?.length" class="mt-5 space-y-3 border-t pt-4">
                        <div class="space-y-1">
                            <h3 class="flex items-center gap-2 text-sm font-semibold"><Archive class="size-4" /> {{ t('workspaceProjects.archivedTitle') }}</h3>
                            <p class="text-xs text-muted-foreground">{{ archivedCount(props.archivedProjectsByWorkspace[workspaceItem.id]?.length ?? 0) }} · {{ t('workspaceProjects.archiveWarning') }}</p>
                        </div>
                        <div class="divide-y rounded-lg border bg-muted/20">
                            <div v-for="project in props.archivedProjectsByWorkspace[workspaceItem.id]" :key="project.id" class="flex flex-wrap items-center gap-3 px-3 py-3 transition-colors hover:bg-muted/25 focus-within:bg-muted/25 sm:px-4">
                                <ProjectIdentity :logo-url="project.logo_url" :icon="project.icon" :color="project.color" :type="project.type" class="opacity-60" />
                                <div class="min-w-0 flex-1 space-y-1">
                                    <Link :href="`/projects/${project.id}`" class="break-words text-sm font-medium hover:underline">{{ project.name }}</Link>
                                    <p class="text-xs text-muted-foreground">{{ project.key }} · {{ project.type === 'application' ? t('stage2.application') : t('stage2.standard') }} · {{ t('stage3.archived') }}</p>
                                </div>
                                <TooltipProvider v-if="project.can_archive" :delay-duration="150">
                                    <Tooltip>
                                        <TooltipTrigger as-child>
                                            <Button type="button" size="icon-sm" variant="ghost"
                                                :aria-label="`${t('workspaceProjects.restore')}: ${project.name}`"
                                                @click="confirmProjectAction(workspaceItem.id, project, 'restore')">
                                                <ArchiveRestore class="size-4" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>{{ t('workspaceProjects.restore') }}</TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                      </section>
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

        <ProjectFormDialog
            v-if="projectWorkspaceId !== null"
            v-model:open="projectDialogOpen"
            :workspace-id="projectWorkspaceId"
            :project="currentEditingProject"
        />

        <Dialog :open="confirmingProject !== null" @update:open="onProjectActionDialogChange">
            <DialogContent :show-close-button="!projectActionBusy">
                <DialogHeader>
                    <DialogTitle>{{ confirmingProject?.action === 'archive' ? t('workspaceProjects.archiveTitle') : t('workspaceProjects.restoreTitle') }}</DialogTitle>
                    <DialogDescription>
                        {{ confirmingProject ? formatText(confirmingProject.action === 'archive' ? 'workspaceProjects.archiveDescription' : 'workspaceProjects.restoreDescription', { project: confirmingProject.project.name }) : '' }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" type="button" :disabled="projectActionBusy" @click="confirmingProject = null">{{ t('stage2.cancel') }}</Button>
                    <Button type="button" :disabled="projectActionBusy" @click="submitProjectAction">
                        <Archive v-if="confirmingProject?.action === 'archive'" class="size-4" />
                        <ArchiveRestore v-else class="size-4" />
                        {{ projectActionBusy ? t(confirmingProject?.action === 'archive' ? 'workspaceProjects.archiving' : 'workspaceProjects.restoring') : t(confirmingProject?.action === 'archive' ? 'workspaceProjects.archive' : 'workspaceProjects.restore') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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
