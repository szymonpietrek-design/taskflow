<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogDescription, DialogFooter,
    DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { WorkspaceProjectSummary } from '@/types/project';

const props = defineProps<{
    open: boolean;
    workspaceId: number;
    project?: WorkspaceProjectSummary | null;
}>();
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
const { t } = useI18n();
const editing = computed(() => !!props.project);

// Shared dialog for both /workspaces and the sidebar /projects shortcut.
// The workspace ID is sent in the URL, never inferred from an old session.
const form = useForm({
    name: '', key: '', description: '', type: 1, visibility: 1,
});

watch(() => [props.open, props.project, props.workspaceId] as const, () => {
    if (!props.open) return;
    form.reset();
    form.clearErrors();
    if (!props.project) return;
    form.name = props.project.name;
    form.key = props.project.key;
    form.description = props.project.description ?? '';
    form.type = props.project.type === 'application' ? 2 : 1;
    form.visibility = props.project.visibility === 'private' ? 2 : 1;
}, { immediate: true });

function setOpen(open: boolean): void {
    if (form.processing) return;
    emit('update:open', open);
}

function submit(): void {
    if (form.processing) return;
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
        <DialogContent :show-close-button="!form.processing" class="sm:max-w-lg">
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
                    <Label for="workspace-project-description">{{ t('stage2.description') }} ({{ t('stage2.optional') }})</Label>
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
                <div class="grid gap-2">
                    <Label for="workspace-project-visibility">{{ t('stage2.visibility') }}</Label>
                    <select id="workspace-project-visibility" v-model.number="form.visibility" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50">
                        <option :value="1">{{ t('stage2.workspace') }}</option>
                        <option :value="2">{{ t('stage2.private') }}</option>
                    </select>
                    <InputError :message="form.errors.visibility" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" :disabled="form.processing" @click="setOpen(false)">{{ t('stage2.cancel') }}</Button>
                    <Button type="submit" :disabled="form.processing || !form.name.trim() || (!editing && !form.key.trim())">
                        {{ editing ? t('stage2.save_changes') : t('stage2.create_project') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
