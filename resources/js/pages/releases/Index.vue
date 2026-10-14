<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, CalendarDays, Package, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Release = {
    id: number;
    version: string;
    name: string | null;
    description: string | null;
    status: string;
    status_label: string;
    planned_for: string | null;
    released_at: string | null;
};

const props = defineProps<{
    project: { id: number; name: string; key: string };
    can_create: boolean;
    completed_available_count: number;
    releases: {
        data: Release[];
        total: number;
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const baseUrl = `/projects/${props.project.id}/releases`;
const includeCompleted = ref(true);

defineOptions({
    layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] },
});
const { t, locale, formatText, translateLiteral, countLabel } = useI18n();
</script>

<template>
    <Head :title="`Releases · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link :href="`/projects/${project.id}`"
                    ><ArrowLeft class="size-4" /> {{ t('stage3.back_to_project') }}</Link
                >
            </Button>
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge variant="outline">{{ t('stage3.application') }}</Badge>
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('stage3.releases') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ formatText('audit.releases_track_for', { project: project.name }) }}
                </p>
            </div>
        </div>

        <div
            class="grid items-start gap-6"
            :class="can_create ? 'lg:grid-cols-[minmax(0,1fr)_22rem]' : ''"
        >
            <section class="space-y-3">
                <div>
                    <h2 class="font-medium">{{ t('stage3.version_history') }}</h2>
                    <p class="text-sm text-muted-foreground">
                        {{ formatText('audit.count_releases', { count: releases.total }) }}
                    </p>
                </div>
                <Card v-if="releases.data.length === 0" class="border-dashed">
                    <CardContent
                        class="flex flex-col items-center gap-2 py-10 text-center"
                    >
                        <Package class="size-9 text-muted-foreground" />
                        <p class="font-medium">{{ t('stage3.no_releases_yet') }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ t('audit.create_the_first_application_version_to_get_started') }}
                        </p>
                    </CardContent>
                </Card>
                <div v-else class="space-y-3">
                    <Link
                        v-for="release in releases.data"
                        :key="release.id"
                        :href="`${baseUrl}/${release.id}`"
                        class="block"
                    >
                        <Card
                            class="gap-3 py-4 transition-colors hover:bg-muted/30"
                        >
                            <CardContent class="space-y-2 px-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold"
                                        >v{{ release.version }}</span
                                    >
                                    <Badge
                                        :variant="
                                            release.status === 'released'
                                                ? 'default'
                                                : 'secondary'
                                        "
                                        >{{ translateLiteral(release.status_label) }}</Badge
                                    >
                                </div>
                                <p
                                    v-if="release.name"
                                    class="text-sm font-medium"
                                >
                                    {{ release.name }}
                                </p>
                                <p
                                    v-if="release.description"
                                    class="line-clamp-2 text-sm text-muted-foreground"
                                >
                                    {{ release.description }}
                                </p>
                                <div
                                    v-if="release.planned_for"
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <CalendarDays class="size-3.5" /> {{ formatText('audit.day_planned', { date: release.planned_for }) }}
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>
                <div
                    v-if="releases.last_page > 1"
                    class="flex items-center justify-between gap-3 pt-2"
                >
                    <Button
                        v-if="releases.prev_page_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <Link :href="releases.prev_page_url">{{ t('stage3.previous') }}</Link>
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled
                        >{{ t('stage3.previous') }}</Button
                    >
                    <span class="text-xs text-muted-foreground"
                        >{{ formatText('audit.count_pages', { page: releases.current_page, pages: releases.last_page }) }}</span
                    >
                    <Button
                        v-if="releases.next_page_url"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <Link :href="releases.next_page_url">{{ t('stage3.next') }}</Link>
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled
                        >{{ t('stage3.next') }}</Button
                    >
                </div>
            </section>

            <Card v-if="can_create" class="h-fit">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Plus class="size-4" /> {{ t('stage3.new_release') }}</CardTitle
                    >
                    <CardDescription
                        >{{ t('audit.use_major_minor_patch_for_example_1_0_0_or_1_2_3') }}</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <Form
                        :action="baseUrl"
                        method="post"
                        class="space-y-4"
                        reset-on-success
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="release-version">{{ t('stage3.version') }}</Label>
                            <Input
                                id="release-version"
                                name="version"
                                required
                                maxlength="32"
                                placeholder="1.0.0"
                            />
                            <InputError :message="errors.version" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="release-name"
                                >{{ t('audit.name') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage3.optional') }}</span
                                ></Label
                            >
                            <Input
                                id="release-name"
                                name="name"
                                maxlength="160"
                                :placeholder="t('audit.initial_release')"
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="release-description"
                                >{{ t('audit.description') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage3.optional') }}</span
                                ></Label
                            >
                            <textarea
                                id="release-description"
                                name="description"
                                rows="4"
                                maxlength="10000"
                                :placeholder="t('audit.optional_internal_notes_about_this_version')"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="release-date"
                                >{{ t('audit.planned_date') }}
                                <span class="font-normal text-muted-foreground"
                                    >{{ t('stage3.optional') }}</span
                                ></Label
                            >
                            <Input
                                id="release-date"
                                name="planned_for"
                                type="date"
                            />
                            <InputError :message="errors.planned_for" />
                        </div>
                        <div
                            class="flex items-start gap-3 rounded-md border p-3"
                        >
                            <Checkbox
                                id="include-completed"
                                v-model="includeCompleted"
                                class="mt-0.5"
                            />
                            <input
                                type="hidden"
                                name="include_completed_tasks"
                                :value="includeCompleted ? '1' : '0'"
                            />
                            <label
                                for="include-completed"
                                class="cursor-pointer space-y-1 text-sm"
                            >
                                <span class="block font-medium"
                                    >{{ t('audit.include_completed_tasks') }}
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >{{ t('stage3.optional') }}</span
                                    ></span
                                >
                                <span class="block text-muted-foreground"
                                    >{{ formatText('fix.release_attach_count', { count: completed_available_count, tasks: countLabel('task', completed_available_count) }) }}</span
                                >
                            </label>
                        </div>
                        <InputError :message="errors.include_completed_tasks" />
                        <InputError :message="errors.release" />
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="processing"
                            ><Plus class="size-4" /> {{ t('audit.create_draft_release') }}</Button
                        >
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
