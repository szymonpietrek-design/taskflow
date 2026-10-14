<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
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
    return type === 'application' ? t('stage2.application') : t('stage2.standard');
}

function visibilityLabel(visibility: ProjectSummary['visibility']): string {
    return visibility === 'private' ? t('stage2.private') : t('stage2.workspace');
}
const { t, formatText, locale } = useI18n();

const projectCount = computed(() => {
    const count = projects.value.length;
    const plural = new Intl.PluralRules(locale.value).select(count);
    const key = plural === 'one'
        ? 'projects.countOne'
        : plural === 'few'
          ? 'projects.countFew'
          : 'projects.countMany';

    return formatText(key, { count });
});
</script>

<template>
    <Head :title="t('stage2.projects')" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            :title="t('stage2.projects')"
            :description="
                workspace
                    ? formatText('projects.inWorkspace', { workspace: workspace.name })
                    : t('projects.chooseWorkspace')
            "
        />

        <div
            v-if="workspace"
            class="grid gap-6"
            :class="canCreate ? 'lg:grid-cols-[minmax(0,1fr)_24rem]' : ''"
        >
            <section class="space-y-3">
                <div>
                    <h2 class="text-base font-medium">{{ t('stage2.workspace_projects') }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ projectCount }}
                    </p>
                </div>

                <div v-if="projects.length" class="grid gap-3">
                    <Link
                        v-for="project in projects"
                        :key="project.id"
                        :href="`/projects/${project.id}`"
                        class="block"
                    >
                        <Card
                            class="gap-4 py-5 transition-colors hover:bg-muted/30"
                        >
                            <CardContent class="flex items-center gap-4 px-5">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-muted/50"
                                >
                                    <AppWindow
                                        v-if="project.type === 'application'"
                                        class="size-5 text-muted-foreground"
                                    />
                                    <FolderKanban
                                        v-else
                                        class="size-5 text-muted-foreground"
                                    />
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <p class="truncate font-medium">
                                            {{ project.name }}
                                        </p>
                                        <Badge variant="secondary">{{
                                            project.key
                                        }}</Badge>
                                    </div>
                                    <div
                                        class="mt-1 flex flex-wrap gap-2 text-sm text-muted-foreground"
                                    >
                                        <span>{{
                                            typeLabel(project.type)
                                        }}</span>
                                        <span>·</span>
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <LockKeyhole
                                                v-if="
                                                    project.visibility ===
                                                    'private'
                                                "
                                                class="size-3.5"
                                            />
                                            {{
                                                visibilityLabel(
                                                    project.visibility,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                <Card v-else class="border-dashed">
                    <CardContent
                        class="flex flex-col items-center py-10 text-center"
                    >
                        <div
                            class="mb-4 flex size-11 items-center justify-center rounded-full bg-muted"
                        >
                            <FolderKanban
                                class="size-5 text-muted-foreground"
                            />
                        </div>
                        <p class="font-medium">{{ t('stage2.no_projects_yet') }}</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            {{ t('stage2.create_the_first_project_for_this_workspace') }}
                        </p>
                    </CardContent>
                </Card>
            </section>

            <Card v-if="canCreate" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Plus class="size-4" />
                        {{ t('stage2.new_project') }}
                    </CardTitle>
                    <CardDescription>
                        {{ t('stage2.use_application_for_software_projects_that_will_use_releases_changelogs_and') }}
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
                            <Label for="project-name">{{ t('stage2.name') }}</Label>
                            <Input
                                id="project-name"
                                name="name"
                                maxlength="120"
                                required
                                :placeholder="t('audit.customer_portal')"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-key">{{ t('stage2.key') }}</Label>
                            <Input
                                id="project-key"
                                name="key"
                                maxlength="12"
                                required
                                placeholder="APP"
                                class="uppercase"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ t('stage2.used_for_task_identifiers_such_as_app_142') }}
                            </p>
                            <InputError :message="errors.key" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-description"
                                >{{ t('stage2.description') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage2.optional') }}</span
                                ></Label
                            >
                            <textarea
                                id="project-description"
                                name="description"
                                rows="3"
                                maxlength="5000"
                                :placeholder="t('stage2.what_is_this_project_for')"
                                class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-type">{{ t('stage2.type') }}</Label>
                            <select
                                id="project-type"
                                name="type"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="1">{{ t('stage2.standard') }}</option>
                                <option value="2">{{ t('stage2.application') }}</option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="project-visibility">{{ t('stage2.visibility') }}</Label>
                            <select
                                id="project-visibility"
                                name="visibility"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="1">{{ t('stage2.workspace') }}</option>
                                <option value="2">{{ t('stage2.private') }}</option>
                            </select>
                            <InputError :message="errors.visibility" />
                        </div>

                        <Button class="w-full" :disabled="processing">
                            <Plus class="size-4" />
                            {{ t('stage2.create_project') }}
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
