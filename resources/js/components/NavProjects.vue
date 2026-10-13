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

const page = usePage();
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
        <SidebarGroupLabel>Projects</SidebarGroupLabel>

        <SidebarGroupAction v-if="page.props.workspace.current" as-child>
            <Link
                href="/projects"
                aria-label="Manage projects"
                title="Manage projects"
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
                        <span>No projects yet</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuButton
                    v-else
                    disabled
                    tooltip="Choose a workspace first"
                >
                    <FolderKanban />
                    <span>Choose a workspace</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
