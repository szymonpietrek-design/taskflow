<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Clock3,
    GitBranch,
    LayoutGrid,
    PanelsTopLeft,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavProjects from '@/components/NavProjects.vue';
import NavUser from '@/components/NavUser.vue';
import WorkspaceSwitcher from '@/components/WorkspaceSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useI18n } from '@/composables/useI18n';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const { t, locale } = useI18n();
const page = usePage();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('nav.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: t('nav.worklog'),
        href: '/worklogs',
        icon: Clock3,
    },
    {
        title: t('nav.workspaces'),
        href: '/workspaces',
        icon: PanelsTopLeft,
    },
    ...(page.props.auth.user?.is_system_admin ? [
        {
            title: t('adminUsers.nav'),
            href: '/admin/users',
            icon: UsersRound,
        },
        {
            title: locale.value === 'pl' ? 'Integracje: Git' : 'Git integrations',
            href: '/admin/integrations/git',
            icon: GitBranch,
        },
    ] : []),
]);

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <WorkspaceSwitcher />
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavProjects />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
