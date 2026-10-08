<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, PanelsTopLeft, Settings2 } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';

const page = usePage();
const { isMobile, state } = useSidebar();

const workspace = computed(() => page.props.workspace);
const currentWorkspace = computed(() => workspace.value.current);

function switchWorkspace(workspaceId: number): void {
    if (currentWorkspace.value?.id === workspaceId) {
        return;
    }

    router.put(
        `/workspaces/${workspaceId}/current`,
        {},
        {
            preserveScroll: true,
        },
    );
}
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        :tooltip="currentWorkspace?.name ?? 'Choose workspace'"
                    >
                        <div
                            class="flex aspect-square size-8 items-center justify-center rounded-lg border bg-sidebar-accent text-sidebar-accent-foreground"
                        >
                            <PanelsTopLeft class="size-4" />
                        </div>

                        <div class="grid flex-1 text-left text-sm leading-tight">
                            <span class="truncate font-medium">
                                {{ currentWorkspace?.name ?? 'No workspace' }}
                            </span>
                            <span class="truncate text-xs text-muted-foreground">
                                {{ currentWorkspace ? 'Workspace' : 'Create your first workspace' }}
                            </span>
                        </div>

                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'right' : 'bottom'"
                    align="start"
                    :side-offset="4"
                >
                    <DropdownMenuLabel class="text-xs text-muted-foreground">
                        Workspaces
                    </DropdownMenuLabel>

                    <template v-if="workspace.items.length">
                        <DropdownMenuItem
                            v-for="item in workspace.items"
                            :key="item.id"
                            class="gap-2 p-2"
                            @select="switchWorkspace(item.id)"
                        >
                            <div
                                class="flex size-6 items-center justify-center rounded-md border"
                            >
                                <PanelsTopLeft class="size-3.5" />
                            </div>
                            <span class="min-w-0 flex-1 truncate">{{ item.name }}</span>
                            <Check v-if="item.is_current" class="size-4" />
                        </DropdownMenuItem>
                    </template>

                    <DropdownMenuItem v-else disabled>
                        No workspaces yet
                    </DropdownMenuItem>

                    <DropdownMenuSeparator />

                    <DropdownMenuItem as-child>
                        <Link href="/workspaces" class="cursor-pointer">
                            <Settings2 class="size-4" />
                            Manage workspaces
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
