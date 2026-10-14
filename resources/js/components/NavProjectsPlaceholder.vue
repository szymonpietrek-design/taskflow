<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { FolderKanban } from '@lucide/vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
const { t } = useI18n();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ t('audit.projects') }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton disabled :tooltip="t('audit.no_projects_yet')">
                    <FolderKanban />
                    <span>{{ t('audit.no_projects_yet') }}</span>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
