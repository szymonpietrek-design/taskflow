<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Check, Plus, Users } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
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
import type { WorkspaceSummary } from '@/types';

const props = defineProps<{
    workspaces: WorkspaceSummary[];
}>();

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
    return role.charAt(0).toUpperCase() + role.slice(1);
}
</script>

<template>
    <Head title="Workspaces" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Workspaces"
            description="Create a workspace or choose where you want to work."
        />

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-medium">Your workspaces</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ props.workspaces.length }}
                            {{ props.workspaces.length === 1 ? 'workspace' : 'workspaces' }}
                        </p>
                    </div>
                </div>

                <div v-if="props.workspaces.length" class="grid gap-3">
                    <Card
                        v-for="workspace in props.workspaces"
                        :key="workspace.id"
                        class="gap-4 py-5"
                    >
                        <CardContent class="flex items-center gap-4 px-5">
                            <div
                                class="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/50"
                            >
                                <Users class="size-5 text-muted-foreground" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="truncate font-medium">
                                        {{ workspace.name }}
                                    </p>
                                    <Badge v-if="workspace.is_current" variant="secondary">
                                        <Check class="size-3" />
                                        Current
                                    </Badge>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ roleLabel(workspace.role) }} · {{ workspace.slug }}
                                </p>
                            </div>

                            <Form
                                v-if="!workspace.is_current"
                                :action="`/workspaces/${workspace.id}/current`"
                                method="put"
                                v-slot="{ processing }"
                            >
                                <Button variant="outline" :disabled="processing">
                                    Use workspace
                                </Button>
                            </Form>

                            <Button v-else variant="ghost" disabled>
                                Selected
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card v-else class="border-dashed">
                    <CardContent class="flex flex-col items-center py-10 text-center">
                        <div
                            class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted"
                        >
                            <Users class="size-5 text-muted-foreground" />
                        </div>
                        <p class="font-medium">No workspaces yet</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            Create your first workspace to start organizing projects and tasks.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <Card class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus class="size-4" />
                        New workspace
                    </CardTitle>
                    <CardDescription>
                        Workspaces group projects, members, and integrations.
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
                            <Label for="workspace-name">Name</Label>
                            <Input
                                id="workspace-name"
                                name="name"
                                maxlength="120"
                                required
                                autocomplete="organization"
                                placeholder="Acme Team"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <Button class="w-full" :disabled="processing">
                            <Plus class="size-4" />
                            Create workspace
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
