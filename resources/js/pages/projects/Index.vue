<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { FolderKanban, LockKeyhole, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ProjectIdentity from '@/components/ProjectIdentity.vue';
import ProjectFormDialog from '@/components/ProjectFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import type { ProjectSummary } from '@/types';

const page = usePage();
const projects = computed(() => page.props.projects.items);
const canCreate = computed(() => page.props.projects.can_create);
const workspace = computed(() => page.props.workspace.current);
const { t, formatText, locale } = useI18n();
const createDialogOpen = ref(false);

// The sidebar plus button links to /projects?create=1, opening the shared dialog.
watch(() => page.url, (url) => {
    if (/[?&]create=1(?:&|$)/.test(url) && canCreate.value) createDialogOpen.value = true;
}, { immediate: true });

function typeLabel(type: ProjectSummary['type']): string {
    return type === 'application' ? t('stage2.application') : t('stage2.standard');
}
function visibilityLabel(visibility: ProjectSummary['visibility']): string {
    return visibility === 'private' ? t('stage2.private') : t('stage2.workspace');
}
const projectCount = computed(() => {
    const count = projects.value.length;
    const rule = new Intl.PluralRules(locale.value).select(count);
    const form = rule === 'one' ? 'One' : rule === 'few' ? 'Few' : 'Many';
    return formatText(`projects.count${form}`, { count });
});

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
</script>

<template>
    <Head :title="t('stage2.projects')" />
    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-7 p-4 md:p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <Heading
                :title="t('stage2.projects')"
                :description="workspace ? formatText('projects.inWorkspace', { workspace: workspace.name }) : t('projects.chooseWorkspace')"
            />
            <Button v-if="workspace && canCreate" type="button" @click="createDialogOpen = true">
                <Plus class="size-4" /> {{ t('stage2.new_project') }}
            </Button>
        </div>

        <section v-if="workspace" class="space-y-3">
            <div>
                <h2 class="text-base font-medium">{{ t('stage2.workspace_projects') }}</h2>
                <p class="text-sm text-muted-foreground">{{ projectCount }}</p>
            </div>
            <div v-if="projects.length" class="grid gap-3">
                <Link v-for="project in projects" :key="project.id" :href="`/projects/${project.id}`" class="block">
                    <Card class="gap-0 py-4 transition-colors hover:bg-muted/30">
                        <CardContent class="flex items-center gap-4 px-5">
                            <ProjectIdentity
                                :logo-url="project.logo_url"
                                :icon="project.icon"
                                :color="project.color"
                                :type="project.type"
                                size="md"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="min-w-0 break-words font-medium">{{ project.name }}</p>
                                    <Badge variant="secondary">{{ project.key }}</Badge>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-2 text-sm text-muted-foreground">
                                    <span>{{ typeLabel(project.type) }}</span><span>·</span>
                                    <span class="inline-flex items-center gap-1">
                                        <LockKeyhole v-if="project.visibility === 'private'" class="size-3.5" />
                                        {{ visibilityLabel(project.visibility) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
            <Card v-else class="border-dashed">
                <CardContent class="flex flex-col items-center py-10 text-center">
                    <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted"><FolderKanban class="size-5 text-muted-foreground" /></div>
                    <p class="font-medium">{{ t('stage2.no_projects_yet') }}</p>
                    <p class="mt-1 max-w-sm text-sm text-muted-foreground">{{ t('stage2.create_the_first_project_for_this_workspace') }}</p>
                </CardContent>
            </Card>
        </section>
        <ProjectFormDialog v-if="workspace && canCreate" v-model:open="createDialogOpen" :workspace-id="workspace.id" />
    </div>
</template>
