<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Check, ChevronDown, ImagePlus, Trash2 } from '@lucide/vue';
import { Label } from '@/components/ui/label';
import ProjectIdentity from '@/components/ProjectIdentity.vue';
import { projectColorOptions, projectColors, projectIconFor, projectIconOptions } from '@/lib/projectIdentity';
import type { ProjectColor, ProjectIcon, WorkspaceProjectSummary } from '@/types/project';

const props = defineProps<{
    open: boolean;
    workspaceId: number;
    project?: WorkspaceProjectSummary | null;
}>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { t } = useI18n();
const editing = computed(() => !!props.project);
const iconPickerOpen = ref(false);
const logoInput = ref<HTMLInputElement | null>(null);
const logoForm = useForm<{ logo: File | null }>({ logo: null });
const removingLogo = ref(false);
// An Inertia redirect can refresh page props without replacing the project object
// originally passed to this dialog. Keep the preview correct immediately.
const logoPreviewOverride = ref<{ projectId: number; url: string | null } | null>(null);
const displayedLogoUrl = computed(() => {
    if (!props.project) return null;
    const override = logoPreviewOverride.value;
    return override?.projectId === props.project.id ? override.url : (props.project.logo_url ?? null);
});

// Shared dialog for both /workspaces and the sidebar /projects shortcut.
// The workspace ID is sent in the URL, never inferred from an old session.
const form = useForm({
    name: '', key: '', description: '', type: 1, visibility: 2,
    icon: null as ProjectIcon | null, color: null as ProjectColor | null,
});
const previewIcon = computed<ProjectIcon>(() => form.icon ?? (form.type === 2 ? 'code-2' : 'folder-kanban'));
const activeColor = computed<ProjectColor>(() => form.color ?? 'slate');
// Keep the selected checkmark legible across both light and dark swatches.
const darkCheckColors = new Set<ProjectColor>([
    'blue', 'emerald', 'violet', 'amber', 'rose', 'cyan', 'orange',
    'indigo', 'teal', 'lime', 'fuchsia', 'pink', 'red', 'sky',
]);


watch(() => [props.open, props.project?.id, props.workspaceId] as const, () => {
    if (!props.open) return;
    form.reset();
    form.clearErrors();
    logoForm.reset();
    logoForm.clearErrors();
    logoPreviewOverride.value = null;
    iconPickerOpen.value = false;
    if (!props.project) return;
    form.name = props.project.name;
    form.key = props.project.key;
    form.description = props.project.description ?? '';
    form.type = props.project.type === 'application' ? 2 : 1;
    form.icon = props.project.icon;
    form.color = props.project.color;
}, { immediate: true });

function uploadLogo(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (input) input.value = '';
    if (!props.project || !file || logoForm.processing || removingLogo.value) return;
    const projectId = props.project.id;
    logoForm.logo = file;
    logoForm.post(`/projects/${projectId}/logo`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            logoForm.reset();
            // The private endpoint serves the current file, so a fresh URL also
            // forces a new image request when replacing an existing logo.
            logoPreviewOverride.value = {
                projectId,
                url: `/projects/${projectId}/logo?v=${Date.now()}`,
            };
        },
    });
}

function removeLogo(): void {
    if (!props.project || logoForm.processing || removingLogo.value) return;
    const projectId = props.project.id;
    removingLogo.value = true;
    router.delete(`/projects/${projectId}/logo`, {
        preserveScroll: true,
        onSuccess: () => {
            // Do not wait for a parent component to replace its stale project reference.
            logoPreviewOverride.value = { projectId, url: null };
        },
        onFinish: () => { removingLogo.value = false; },
    });
}

function setOpen(open: boolean): void {
    if (form.processing || logoForm.processing || removingLogo.value) return;
    emit('update:open', open);
}

function submit(): void {
    if (form.processing || logoForm.processing || removingLogo.value) return;
    form.name = form.name.trim();
    form.key = form.key.trim().toUpperCase();
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
            form.clearErrors();
        },
    };
    if (props.project) {
        form.patch(`/workspaces/${props.workspaceId}/projects/${props.project.id}`, options);
    } else {
        form.post(`/workspaces/${props.workspaceId}/projects`, options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="setOpen">
        <DialogContent :show-close-button="!form.processing" class="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ editing ? t('workspaceProjects.editTitle') : t('stage2.new_project') }}</DialogTitle>
                <DialogDescription>
                    {{ editing ? t('workspaceProjects.editDescription') : t('stage2.use_application_for_software_projects_that_will_use_releases_changelogs_and') }}
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="workspace-project-name">{{ t('stage2.name') }}</Label>
                    <Input id="workspace-project-name" v-model="form.name" maxlength="120" required autofocus :placeholder="t('audit.customer_portal')" />
                    <InputError :message="form.errors.name" />
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="workspace-project-key">{{ t('stage2.key') }}</Label>
                    <Input id="workspace-project-key" v-model="form.key" maxlength="12" required class="uppercase" placeholder="APP" />
                    <p class="text-xs text-muted-foreground">{{ t('stage2.used_for_task_identifiers_such_as_app_142') }}</p>
                    <InputError :message="form.errors.key" />
                </div>
                <div v-else class="space-y-1 text-sm text-muted-foreground">
                    {{ t('workspaceProjects.keyUnchanged') }}: <span class="font-medium">{{ form.key }}</span>
                </div>
                <div class="grid gap-2">
                    <Label for="workspace-project-description">{{ t('stage2.description') }} {{ t('stage2.optional') }}</Label>
                    <textarea id="workspace-project-description" v-model="form.description" rows="3" maxlength="5000"
                        :placeholder="t('stage2.what_is_this_project_for')"
                        class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50" />
                    <InputError :message="form.errors.description" />
                </div>
                <div v-if="!editing" class="grid gap-2">
                    <Label for="workspace-project-type">{{ t('stage2.type') }}</Label>
                    <select id="workspace-project-type" v-model.number="form.type" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50">
                        <option :value="1">{{ t('stage2.standard') }}</option>
                        <option :value="2">{{ t('stage2.application') }}</option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>
                <section class="space-y-3 border-t pt-4" :aria-label="t('projectIdentity.appearance')">
                    <div class="flex items-center justify-between gap-3">
                        <div class="space-y-0.5">
                            <h3 class="text-sm font-semibold">{{ t('projectIdentity.appearance') }}</h3>
                            <p class="text-xs text-muted-foreground">{{ t('projectIdentity.appearanceHelp') }}</p>
                        </div>
                        <ProjectIdentity :logo-url="displayedLogoUrl" :icon="form.icon" :color="form.color" :type="form.type === 2 ? 'application' : 'standard'" size="lg" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Popover v-model:open="iconPickerOpen">
                            <PopoverTrigger as-child>
                                <Button type="button" variant="outline" size="sm" class="gap-2" :disabled="form.processing">
                                    <component :is="projectIconFor(previewIcon, undefined)" class="size-4" />
                                    {{ t('projectIdentity.chooseIcon') }}
                                    <ChevronDown class="size-3.5 text-muted-foreground" />
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent align="start" class="w-[min(19rem,calc(100vw-2rem))] p-3">
                                <p class="mb-2 text-xs font-medium">{{ t('projectIdentity.iconSelection') }}</p>
                                <div class="grid grid-cols-6 gap-1.5" role="group" :aria-label="t('projectIdentity.icon')">
                                    <button v-for="icon in projectIconOptions" :key="icon" type="button"
                                        class="relative flex size-9 items-center justify-center rounded-md border transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        :class="previewIcon === icon ? 'border-primary bg-accent' : 'border-border'"
                                        :aria-label="t(`projectIdentity.iconNames.${icon}`)"
                                        :aria-pressed="previewIcon === icon"
                                        :title="t(`projectIdentity.iconNames.${icon}`)"
                                        @click="form.icon = icon; iconPickerOpen = false">
                                        <component :is="projectIconFor(icon, undefined)" class="size-4" />
                                    </button>
                                </div>
                            </PopoverContent>
                        </Popover>
                        <template v-if="editing">
                            <input ref="logoInput" type="file" class="sr-only" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp" :disabled="logoForm.processing || removingLogo" :aria-label="t('projectIdentity.uploadLogo')" @change="uploadLogo" />
                            <Button type="button" variant="outline" size="sm" class="gap-2" :disabled="logoForm.processing || removingLogo" @click="logoInput?.click()">
                                <ImagePlus class="size-4" />
                                {{ t(displayedLogoUrl ? 'projectIdentity.logoReplace' : 'projectIdentity.uploadLogo') }}
                            </Button>
                            <Button v-if="displayedLogoUrl" type="button" variant="outline" size="sm" class="gap-2 text-muted-foreground hover:border-destructive/40 hover:bg-destructive/5 hover:text-destructive focus-visible:border-destructive/40 focus-visible:ring-destructive/20" :disabled="logoForm.processing || removingLogo" @click="removeLogo">
                                <Trash2 class="size-4" />
                                {{ t('projectIdentity.removeLogo') }}
                            </Button>
                        </template>
                    </div>
                    <InputError :message="form.errors.icon" />
                    <InputError :message="logoForm.errors.logo" />
                    <p v-if="editing" class="text-xs text-muted-foreground">{{ t('projectIdentity.logoHint') }}</p>
                    <div v-else class="flex items-start gap-2 rounded-md bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                        <ImagePlus class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        <p>{{ t('projectIdentity.logoAfterCreate') }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label>{{ t('projectIdentity.color') }}</Label>
                        <div class="grid w-fit max-w-full grid-cols-6 gap-2 sm:grid-cols-8" role="group" :aria-label="t('projectIdentity.color')">
                            <button v-for="color in projectColorOptions" :key="color" type="button"
                                class="flex size-8 items-center justify-center rounded-full border border-border transition-transform hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                :class="activeColor === color ? 'ring-2 ring-ring/60 ring-offset-1 ring-offset-background' : undefined"
                                :aria-label="t(`projectIdentity.colors.${color}`)"
                                :aria-pressed="activeColor === color"
                                :title="t(`projectIdentity.colors.${color}`)"
                                @click="form.color = color">
                                <span class="flex size-6 items-center justify-center rounded-full" :class="projectColors[color].dot">
                                    <Check v-if="activeColor === color" class="size-4 drop-shadow-sm" :class="darkCheckColors.has(color) ? 'text-slate-950' : 'text-white'" />
                                </span>
                            </button>
                        </div>
                        <InputError :message="form.errors.color" />
                    </div>
                </section>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing || logoForm.processing || removingLogo" @click="setOpen(false)">{{ t('stage2.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing || logoForm.processing || removingLogo || !form.name.trim() || (!editing && !form.key.trim())">
                        {{ editing ? t('stage2.save_changes') : t('stage2.create_project') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
