<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FileText, FolderKanban, LayoutDashboard, ListTodo, Package, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ProjectIdentity from '@/components/ProjectIdentity.vue';
import { useI18n } from '@/composables/useI18n';
import { projectColors } from '@/lib/projectIdentity';
import {
    SidebarGroup,
    SidebarGroupAction,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';

type ProjectSection = 'overview' | 'tasks' | 'releases' | 'reports';

const page = usePage();
const { t } = useI18n();
const projects = computed(() => page.props.projects.items);
const pathname = computed(() => page.url.split('?')[0].replace(/\/$/, '') || '/');

// Match a complete project ID, not a prefix (e.g. /projects/6 vs /projects/60).
const activeProjectId = computed<number | null>(() => {
    const match = /^\/projects\/(\d+)(?:\/|$)/.exec(pathname.value);
    return match ? Number(match[1]) : null;
});

// Clicking the entire project row toggles its submenu without navigating.
// Navigation remains available via the section links (including Overview).
const { state: sidebarState, setOpen } = useSidebar();
const expandedProjectId = ref<number | null>(activeProjectId.value);

// Directly opening a project URL (or navigating to another project via Inertia)
// automatically reveals its section without forcing navigation when toggling.
watch(activeProjectId, (current, previous) => {
    if (current !== previous) expandedProjectId.value = current;
});

function isProjectExpanded(projectId: number): boolean {
    return expandedProjectId.value === projectId;
}

function toggleProjectNavigation(projectId: number): void {
    if (sidebarState.value === 'collapsed') {
        setOpen(true);
        expandedProjectId.value = projectId;
        return;
    }
    expandedProjectId.value = isProjectExpanded(projectId) ? null : projectId;
}

function isActive(projectId: number): boolean {
    return activeProjectId.value === projectId;
}

function isSectionActive(projectId: number, section: ProjectSection): boolean {
    const base = `/projects/${projectId}`;
    const sectionPath = section === 'overview' ? base : `${base}/${section}`;
    return section === 'overview'
        ? pathname.value === sectionPath
        : pathname.value === sectionPath || pathname.value.startsWith(`${sectionPath}/`);
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

        <SidebarMenu class="gap-0.5">
            <template v-if="projects.length">
                <SidebarMenuItem
                    v-for="project in projects"
                    :key="project.id"
                >
                    <!-- One accessible button handles the entire row. Submenu links navigate. -->
                    <SidebarMenuButton
                        type="button"
                        size="lg"
                        :is-active="isActive(project.id)"
                        :tooltip="`${project.name} (${project.key})`"
                        class="relative min-w-0 gap-2 text-left transition-colors duration-150 data-[active=true]:bg-sidebar-accent/65 data-[active=true]:font-medium group-data-[collapsible=icon]:p-0!"
                        :class="{ 'bg-sidebar-accent/35': isProjectExpanded(project.id) && !isActive(project.id) }"
                        :aria-expanded="isProjectExpanded(project.id)"
                        :aria-controls="`project-subnav-${project.id}`"
                        :aria-label="`${project.name} — ${isProjectExpanded(project.id) ? t('nav.collapseProjectNavigation') : t('nav.expandProjectNavigation')}`"
                        @click="toggleProjectNavigation(project.id)"
                    >
                        <span
                            v-if="isActive(project.id)"
                            aria-hidden="true"
                            class="absolute top-2 bottom-2 left-0 w-0.5 rounded-full group-data-[collapsible=icon]:hidden"
                            :class="projectColors[project.color ?? 'slate'].dot"
                        />
                        <ProjectIdentity
                            :logo-url="project.logo_url"
                            :icon="project.icon"
                            :color="project.color"
                            :type="project.type"
                            size="sm"
                        />
                        <span class="flex min-w-0 flex-1 flex-col gap-0.5 leading-tight group-data-[collapsible=icon]:hidden">
                            <span class="truncate text-[13px] font-medium">{{ project.name }}</span>
                            <span class="truncate text-[11px] text-muted-foreground">{{ project.key }}</span>
                        </span>
                    </SidebarMenuButton>

                    <!-- Grid rows animate from 0fr to 1fr, so variable submenu height works. -->
                    <Transition name="project-subnav-slide">
                        <div
                            v-show="isProjectExpanded(project.id)"
                            :id="`project-subnav-${project.id}`"
                            class="project-subnav-slide grid min-w-0 group-data-[collapsible=icon]:hidden"
                        >
                            <div class="min-h-0 overflow-hidden">
                                <SidebarMenuSub
                                    class="my-0.5 ml-5 mr-0 gap-0.5 border-sidebar-border/90 pl-2.5"
                                >
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child size="sm" class="data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-semibold" :is-active="isSectionActive(project.id, 'overview')">
                                            <Link :href="`/projects/${project.id}`" :aria-current="isSectionActive(project.id, 'overview') ? 'page' : undefined">
                                                <LayoutDashboard />
                                                <span>{{ t('audit.overview') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child size="sm" class="data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-semibold" :is-active="isSectionActive(project.id, 'tasks')">
                                            <Link :href="`/projects/${project.id}/tasks`" :aria-current="isSectionActive(project.id, 'tasks') ? 'page' : undefined">
                                                <ListTodo />
                                                <span>{{ t('stage2.tasks') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem v-if="project.type === 'application'">
                                        <SidebarMenuSubButton as-child size="sm" class="data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-semibold" :is-active="isSectionActive(project.id, 'releases')">
                                            <Link :href="`/projects/${project.id}/releases`" :aria-current="isSectionActive(project.id, 'releases') ? 'page' : undefined">
                                                <Package />
                                                <span>{{ t('stage2.releases') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                    <SidebarMenuSubItem>
                                        <SidebarMenuSubButton as-child size="sm" class="data-[active=true]:bg-sidebar-accent/70 data-[active=true]:font-semibold" :is-active="isSectionActive(project.id, 'reports')">
                                            <Link :href="`/projects/${project.id}/reports`" :aria-current="isSectionActive(project.id, 'reports') ? 'page' : undefined">
                                                <FileText />
                                                <span>{{ t('stage2.reports') }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </div>
                        </div>
                    </Transition>
                </SidebarMenuItem>
            </template>

            <SidebarMenuItem v-else>
                <SidebarMenuButton v-if="page.props.workspace.current" as-child>
                    <Link href="/projects">
                        <FolderKanban />
                        <span>{{ t('nav.noProjects') }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuButton v-else disabled :tooltip="t('nav.chooseWorkspaceFirst')">
                    <FolderKanban />
                    <span>{{ t('nav.chooseWorkspace') }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

<style scoped>
/* Neutral navigation highlights. Project colors stay in the identity icon
   and the active project's rail; expanding a group never recolors a section. */
.project-subnav-slide {
    grid-template-rows: 1fr;
}

.project-subnav-slide-enter-active,
.project-subnav-slide-leave-active {
    transition:
        grid-template-rows 220ms cubic-bezier(0.22, 1, 0.36, 1),
        opacity 180ms ease;
}

.project-subnav-slide-enter-from,
.project-subnav-slide-leave-to {
    grid-template-rows: 0fr;
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .project-subnav-slide-enter-active,
    .project-subnav-slide-leave-active {
        transition: none;
    }
}
</style>
