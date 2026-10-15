<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FolderKanban, Plus } from '@lucide/vue';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';

const page = usePage();
const { t } = useI18n();
const projects = computed(() => page.props.projects.items);

function isActive(projectId: number): boolean {
    return (
        page.url === `/projects/${projectId}` ||
        page.url.startsWith(`/projects/${projectId}/`)
    );
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ t('nav.projects') }}</SidebarGroupLabel>

        <SidebarGroupAction v-if="page.props.projects.can_create" as-child>
            <Link
                href="/projects?create=1"
                :aria-label="t('stage2.new_project')"
                :title="t('stage2.new_project')"
            >
                <Plus class="size-4" />
            </Link>
        </SidebarGroupAction>

        <SidebarMenu>
            <template v-if="projects.length">
                <SidebarMenuItem v-for="project in projects" :key="project.id">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(project.id)"
                        :tooltip="`${project.name} (${project.key})`"
                    >
                        <Link :href="`/projects/${project.id}`">
                            <FolderKanban />
                            <span class="min-w-0 flex-1 truncate">{{
                                project.name
                            }}</span>
                            <span class="text-[10px] text-muted-foreground">{{
                                project.key
                            }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>

            <SidebarMenuItem v-else>
                <SidebarMenuButton v-if="page.props.workspace.current" as-child>
                    <Link href="/projects">
                        <FolderKanban />
                        <span>{{ t('nav.noProjects') }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuButton
                    v-else
                    disabled
                    :tooltip="t('nav.chooseWorkspaceFirst')"
                >
                    <FolderKanban />
                    <span>{{ t('nav.chooseWorkspace') }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
