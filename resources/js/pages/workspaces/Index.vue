<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Check, Plus, Users } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
}>();

const page = usePage();
const workspaces = computed(() => page.props.workspace.items);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Workspaces',
                href: '/workspaces',
            },
        ],
    },
});

function roleLabel(role: WorkspaceSummary['role']): string {
    return t(role === 'owner' ? 'fix.owners' : role === 'admin' ? 'fix.admin' : 'fix.member');
}
const { t, formatText, countLabel } = useI18n();
</script>

<template>
    <Head :title="t('stage2.workspaces')" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            :title="t('stage2.workspaces')"
            :description="t('stage2.create_a_workspace_or_choose_where_you_want_to_work')"
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-medium">{{ t('stage2.your_workspaces') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ countLabel('workspace', workspaces.length) }}
                        </p>
                    </div>
                </div>

                <div v-if="workspaces.length" class="grid gap-3">
                    <Card
                        v-for="workspaceItem in workspaces"
                        :key="workspaceItem.id"
                        class="gap-4 py-5"
                    >
                        <CardContent
                            class="flex flex-wrap items-center gap-4 px-5"
                        >
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/50"
                            >
                                <Users class="size-5 text-muted-foreground" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium">
                                        {{ workspaceItem.name }}
                                    </p>
                                    <Badge
                                        v-if="workspaceItem.is_current"
                                        variant="secondary"
                                    >
                                        <Check class="size-3" />
                                        {{ t('stage2.current') }}
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ roleLabel(workspaceItem.role) }} ·
                                    {{ workspaceItem.slug }}
                                </p>
                            </div>

                            <div
                                class="flex w-full flex-wrap items-center justify-end gap-3 sm:w-auto"
                            >
                                <TooltipProvider :delay-duration="150">
                                    <div
                                        v-if="
                                            props.memberPreviews[
                                                workspaceItem.id
                                            ]?.members.length
                                        "
                                        class="flex shrink-0 items-center -space-x-2"
                                        :aria-label="formatText('fix.members_of', { workspace: workspaceItem.name })"
                                    >
                                        <Tooltip
                                            v-for="member in props
                                                .memberPreviews[
                                                workspaceItem.id
                                            ]?.members ?? []"
                                            :key="member.id"
                                        >
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="inline-flex shrink-0 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                                                    :aria-label="member.name"
                                                    tabindex="0"
                                                >
                                                    <UserAvatar
                                                        :user-id="member.id"
                                                        :name="member.name"
                                                        compact
                                                        stacked
                                                    />
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>{{
                                                member.name
                                            }}</TooltipContent>
                                        </Tooltip>
                                        <Tooltip
                                            v-if="
                                                props.memberPreviews[
                                                    workspaceItem.id
                                                ]?.remaining
                                            "
                                        >
                                            <TooltipTrigger as-child>
                                                <span
                                                    class="relative z-10 flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-medium text-muted-foreground ring-2 ring-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    tabindex="0"
                                                    :aria-label="formatText('fix.more_members', { count: props.memberPreviews[workspaceItem.id]?.remaining ?? 0 })"
                                                >
                                                    +{{
                                                        props.memberPreviews[
                                                            workspaceItem.id
                                                        ]?.remaining ?? 0
                                                    }}
                                                </span>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                {{ formatText('fix.more_members', { count: props.memberPreviews[workspaceItem.id]?.remaining ?? 0 }) }}
                                            </TooltipContent>
                                        </Tooltip>
                                    </div>
                                </TooltipProvider>

                                <Button
                                    v-if="
                                        workspaceItem.role === 'owner' ||
                                        workspaceItem.role === 'admin'
                                    "
                                    variant="outline"
                                    as-child
                                >
                                    <Link
                                        :href="`/workspaces/${workspaceItem.id}/members`"
                                    >
                                        <Users class="size-4" /> {{ t('stage2.members') }}
                                    </Link>
                                </Button>

                                <Form
                                    v-if="!workspaceItem.is_current"
                                    :action="`/workspaces/${workspaceItem.id}/current`"
                                    method="put"
                                    v-slot="{ processing }"
                                >
                                    <Button
                                        variant="outline"
                                        :disabled="processing"
                                    >
                                        {{ t('stage2.use_workspace') }}
                                    </Button>
                                </Form>

                                <Button v-else variant="ghost" disabled>
                                    {{ t('stage2.selected') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card v-else class="border-dashed">
                    <CardContent
                        class="flex flex-col items-center py-10 text-center"
                    >
                        <div
                            class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted"
                        >
                            <Users class="size-5 text-muted-foreground" />
                        </div>
                        <p class="font-medium">{{ t('stage2.no_workspaces_yet') }}</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            {{ t('stage2.create_your_first_workspace_to_start_organizing_projects_and_tasks') }}
                        </p>
                    </CardContent>
                </Card>
            </section>

            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus class="size-4" />
                        {{ t('stage2.new_workspace') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('stage2.workspaces_group_projects_members_and_integrations') }}
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Form
                        action="/workspaces"
                        method="post"
                        class="space-y-4"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="workspace-name">{{ t('stage2.name') }}</Label>
                            <Input
                                id="workspace-name"
                                name="name"
                                maxlength="120"
                                required
                                autocomplete="organization"
                                :placeholder="t('audit.acme_team')"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <Button class="w-full" :disabled="processing">
                            <Plus class="size-4" />
                            {{ t('stage2.create_workspace') }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
