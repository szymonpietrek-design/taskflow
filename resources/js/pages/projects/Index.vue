<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { AppWindow, FolderKanban, LockKeyhole, Plus } from '@lucide/vue';
import { computed } from 'vue';
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
import type { ProjectSummary } from '@/types';

const page = usePage();
const projects = computed(() => page.props.projects.items);
const canCreate = computed(() => page.props.projects.can_create);
const workspace = computed(() => page.props.workspace.current);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Projects',
                href: '/projects',
            },
        ],
    },
});

function typeLabel(type: ProjectSummary['type']): string {
    return type === 'application' ? 'Application' : 'Standard';
}

function visibilityLabel(visibility: ProjectSummary['visibility']): string {
    return visibility === 'private' ? 'Private' : 'Workspace';
}
</script>

<template>
    <Head title="Projects" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Projects"
            :description="workspace ? `Projects in ${workspace.name}.` : 'Choose a workspace to manage projects.'"
        />

        <div
            v-if="workspace"
            class="grid gap-6"
            :class="canCreate ? 'lg:grid-cols-[minmax(0,1fr)_24rem]' : ''"
        >
            <section class="space-y-3">
                <div>
                    <h2 class="text-base font-medium">Workspace projects</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ projects.length }} {{ projects.length === 1 ? 'project' : 'projects' }}
                    </p>
                </div>

                <div v-if="projects.length" class="grid gap-3">
                    <Link
                        v-for="project in projects"
                        :key="project.id"
                        :href="`/projects/${project.id}`"
                        class="block"
                    >
                        <Card class="gap-4 py-5 transition-colors hover:bg-muted/30">
                            <CardContent class="flex items-center gap-4 px-5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/50"
                                >
                                    <AppWindow
                                        v-if="project.type === 'application'"
                                        class="size-5 text-muted-foreground"
                                    />
                                    <FolderKanban v-else class="size-5 text-muted-foreground" />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-medium">{{ project.name }}</p>
                                        <Badge variant="secondary">{{ project.key }}</Badge>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-2 text-sm text-muted-foreground">
                                        <span>{{ typeLabel(project.type) }}</span>
                                        <span>·</span>
                                        <span class="inline-flex items-center gap-1">
                                            <LockKeyhole
                                                v-if="project.visibility === 'private'"
                                                class="size-3.5"
                                            />
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
                        <div class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted">
                            <FolderKanban class="size-5 text-muted-foreground" />
                        </div>
                        <p class="font-medium">No projects yet</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            Create the first project for this workspace.
                        </p>
                    </CardContent>
                </Card>
            </section>

            <Card v-if="canCreate" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus class="size-4" />
                        New project
                    </CardTitle>
                    <CardDescription>
                        Use Application for software projects that will use releases, changelogs, and GitHub integration.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <Form
                        action="/projects"
                        method="post"
                        class="space-y-4"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="project-name">Name</Label>
                            <Input
                                id="project-name"
                                name="name"
                                maxlength="120"
                                required
                                placeholder="Customer Portal"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-key">Key</Label>
                            <Input
                                id="project-key"
                                name="key"
                                maxlength="12"
                                required
                                placeholder="APP"
                                class="uppercase"
                            />
                            <p class="text-xs text-muted-foreground">
                                Used for task identifiers such as APP-142.
                            </p>
                            <InputError :message="errors.key" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-description">Description <span class="font-normal text-muted-foreground">(optional)</span></Label>
                            <textarea
                                id="project-description"
                                name="description"
                                rows="3"
                                maxlength="5000"
                                placeholder="What is this project for?"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition-[color,box-shadow] placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-type">Type</Label>
                            <select
                                id="project-type"
                                name="type"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="1">Standard</option>
                                <option value="2">Application</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-visibility">Visibility</Label>
                            <select
                                id="project-visibility"
                                name="visibility"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="1">Workspace</option>
                                <option value="2">Private</option>
                            </select>
                            <InputError :message="errors.visibility" />
                        </div>

                        <Button class="w-full" :disabled="processing">
                            <Plus class="size-4" />
                            Create project
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
