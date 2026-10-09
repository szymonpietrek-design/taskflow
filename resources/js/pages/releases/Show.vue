<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock3, FileText, Package, Plus, X } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';

type Release = {
    id: number;
    version: string;
    name: string | null;
    description: string | null;
    status: string;
    status_label: string;
    planned_for: string | null;
    released_at: string | null;
    created_at: string | null;
};

type Changelog = {
    draft_body: string;
    draft_html: string | null;
    published_html: string | null;
    published_at: string | null;
    has_unpublished_changes: boolean;
};

type ReleaseTask = { id: number; reference: string; title: string; status: string; completed: boolean };

const props = defineProps<{
    project: { id: number; name: string; key: string };
    release: Release;
    can_manage_release: boolean;
    can_manage_tasks: boolean;
    can_edit_changelog: boolean;
    changelog: Changelog;
    tasks: {
        data: ReleaseTask[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    task_count: number;
    available_tasks: ReleaseTask[];
    available_count: number;
    task_search: string;
}>();

const draftBody = ref(props.changelog.draft_body);
watch(() => props.changelog.draft_body, (body) => { draftBody.value = body; });

const releaseUrl = `/projects/${props.project.id}/releases/${props.release.id}`;

const confirmFinalAction = (event: MouseEvent, message: string) => {
    if (!window.confirm(message)) {
        event.preventDefault();
    }
};

defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`v${release.version} · ${project.name}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Button as-child size="sm" variant="ghost" class="-ml-3 w-fit">
            <Link :href="`/projects/${project.id}/releases`"><ArrowLeft class="size-4" /> All releases</Link>
        </Button>
        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="secondary">{{ project.key }}</Badge>
                <Badge :variant="release.status === 'released' ? 'default' : 'outline'">{{ release.status_label }}</Badge>
            </div>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">v{{ release.version }}</h1>
                <p v-if="release.name" class="mt-1 text-base text-muted-foreground">{{ release.name }}</p>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <Card>
                <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><CalendarDays class="size-4" /> Planned date</CardTitle></CardHeader>
                <CardContent class="font-medium">{{ release.planned_for ?? 'Not scheduled' }}</CardContent>
            </Card>
            <Card>
                <CardHeader class="pb-2"><CardTitle class="flex items-center gap-2 text-sm"><Clock3 class="size-4" /> Released at</CardTitle></CardHeader>
                <CardContent class="font-medium">{{ release.released_at ? new Date(release.released_at).toLocaleString() : 'Not released' }}</CardContent>
            </Card>
        </div>
        <Card>
            <CardHeader>
                <CardTitle>Release lifecycle</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="release.status === 'released'" class="text-sm text-muted-foreground">
                    This release has been published and is now locked. Its tasks cannot be reassigned.
                </p>
                <p v-else-if="release.status === 'canceled'" class="text-sm text-muted-foreground">
                    This release was canceled and is now locked.
                </p>
                <p v-else-if="!can_manage_release" class="text-sm text-muted-foreground">
                    Only project managers can change the release status.
                </p>
                <template v-else>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="release.status === 'draft'">Plan this draft or cancel it. Planning does not publish the release.</template>
                        <template v-else>Publish this planned release or cancel it. Both actions are final.</template>
                    </p>
                    <Form v-if="release.status === 'draft'" :action="`${releaseUrl}/status`" method="patch" class="space-y-3" v-slot="{ errors, processing }">
                        <input type="hidden" name="status" value="2" />
                        <div class="space-y-1.5">
                            <label for="release-planned-date" class="text-sm font-medium">Planned date (optional)</label>
                            <Input id="release-planned-date" name="planned_for" type="date" :default-value="release.planned_for ?? ''" class="max-w-xs" />
                            <InputError :message="errors.planned_for" />
                        </div>
                        <InputError :message="errors.status" />
                        <Button type="submit" :disabled="processing"><CalendarDays class="size-4" /> Mark as planned</Button>
                    </Form>
                    <Form v-if="release.status === 'planned'" :action="`${releaseUrl}/status`" method="patch" v-slot="{ errors, processing }" class="space-y-2">
                        <input type="hidden" name="status" value="3" />
                        <InputError :message="errors.status" />
                        <Button type="submit" :disabled="processing" @click="confirmFinalAction($event, 'Publish this release? You will not be able to change its status or task assignments.')"><Package class="size-4" /> Publish release</Button>
                    </Form>
                    <Form :action="`${releaseUrl}/status`" method="patch" v-slot="{ errors, processing }" class="space-y-2">
                        <input type="hidden" name="status" value="4" />
                        <InputError :message="errors.status" />
                        <Button type="submit" variant="outline" :disabled="processing" @click="confirmFinalAction($event, 'Cancel this release? This cannot be undone.')"><X class="size-4" /> Cancel release</Button>
                    </Form>
                </template>
            </CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle class="flex items-center gap-2"><Package class="size-4" /> Release notes</CardTitle></CardHeader>
            <CardContent>
                <p v-if="release.description" class="whitespace-pre-wrap text-sm leading-relaxed">{{ release.description }}</p>
                <p v-else class="text-sm text-muted-foreground">No internal release notes provided.</p>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2"><FileText class="size-4" /> Release changelog</CardTitle>
            </CardHeader>
            <CardContent class="space-y-6">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :variant="changelog.published_at ? 'secondary' : 'outline'">{{ changelog.published_at ? 'Published' : 'Not published' }}</Badge>
                        <span v-if="changelog.published_at" class="text-xs text-muted-foreground">{{ new Date(changelog.published_at).toLocaleString() }}</span>
                    </div>
                    <template v-if="changelog.published_html">
                        <p class="text-sm font-medium">Published content</p>
                        <div class="max-w-none break-words rounded-md border p-4 text-sm [&_a]:underline [&_h1]:text-lg [&_h2]:text-base [&_ul]:list-disc [&_ol]:list-decimal [&_li]:ml-5 [&_p]:mb-2 [&_strong]:font-semibold [&_code]:font-mono" v-html="changelog.published_html"></div>
                    </template>
                    <p v-else class="text-sm text-muted-foreground">No published changelog yet.</p>
                </div>
                <template v-if="can_edit_changelog">
                    <div class="space-y-3 border-t pt-5">
                        <h3 class="text-sm font-semibold">Edit draft (Markdown)</h3>
                        <p class="text-xs text-muted-foreground">Saving a draft never changes published content. Save before previewing.</p>
                        <Form :action="`${releaseUrl}/changelog`" method="put" v-slot="{ errors, processing }" class="space-y-3">
                            <textarea v-model="draftBody" name="draft_body" rows="9" maxlength="50000" class="w-full rounded-md border border-input bg-background px-3 py-2 font-mono text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" placeholder="## Added
- New feature ..."></textarea>
                            <InputError :message="errors.draft_body" />
                            <Button type="submit" :disabled="processing">Save changelog draft</Button>
                        </Form>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-sm font-semibold">Saved draft preview</h3>
                        <div v-if="changelog.draft_body.trim()" class="max-w-none break-words rounded-md border bg-muted/20 p-4 text-sm [&_a]:underline [&_h1]:text-lg [&_h2]:text-base [&_ul]:list-disc [&_ol]:list-decimal [&_li]:ml-5 [&_p]:mb-2 [&_strong]:font-semibold [&_code]:font-mono" v-html="changelog.draft_html"></div>
                        <p v-else class="text-sm text-muted-foreground">Save some Markdown to see its preview here.</p>
                    </div>
                    <div v-if="release.status === 'released'" class="space-y-2 border-t pt-4">
                        <Form :action="`${releaseUrl}/changelog/publish`" method="post" v-slot="{ errors, processing }">
                            <InputError :message="errors.changelog" class="mb-2" />
                            <Button type="submit" :disabled="processing || !changelog.has_unpublished_changes" @click="confirmFinalAction($event, 'Publish the saved changelog? This updates the published snapshot.')">
                                <Package class="size-4" /> {{ changelog.published_at ? 'Publish changes' : 'Publish changelog' }}
                            </Button>
                        </Form>
                        <p class="text-xs text-muted-foreground">Only a saved draft can be published. Later edits remain private until published again.</p>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">Publish the Release before publishing its changelog.</p>
                </template>
            </CardContent>
        </Card>
        <Card>
            <CardHeader>
                <CardTitle>Included tasks ({{ task_count }})</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <p v-if="tasks.data.length === 0" class="text-sm text-muted-foreground">No tasks assigned to this release yet.</p>
                <div v-for="task in tasks.data" :key="task.id" class="flex items-center justify-between gap-3 border-b pb-3 last:border-0">
                    <div class="min-w-0 space-y-1">
                        <Link :href="`/projects/${project.id}/tasks/${task.id}`" class="text-sm font-medium hover:underline">{{ task.reference }} · {{ task.title }}</Link>
                        <p class="text-xs text-muted-foreground">{{ task.status }} <span v-if="task.completed">· Completed</span></p>
                    </div>
                    <Form v-if="can_manage_tasks" :action="`${releaseUrl}/tasks/${task.id}`" method="delete" v-slot="{ processing }">
                        <Button type="submit" size="sm" variant="outline" :disabled="processing"><X class="size-4" /> Remove</Button>
                    </Form>
                </div>
                <div v-if="tasks.last_page > 1" class="flex items-center justify-between gap-2">
                    <Button v-if="tasks.prev_page_url" as-child size="sm" variant="outline"><Link :href="tasks.prev_page_url">Previous</Link></Button>
                    <span v-else></span>
                    <span class="text-xs text-muted-foreground">Page {{ tasks.current_page }} / {{ tasks.last_page }}</span>
                    <Button v-if="tasks.next_page_url" as-child size="sm" variant="outline"><Link :href="tasks.next_page_url">Next</Link></Button>
                </div>
            </CardContent>
        </Card>
        <Card v-if="can_manage_tasks">
            <CardHeader><CardTitle>Add existing task</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <Form :action="releaseUrl" method="get" class="flex flex-wrap gap-2">
                    <Input name="q" type="search" :default-value="task_search" placeholder="Search title or APP-12" class="min-w-48 flex-1" />
                    <Button type="submit" variant="outline">Search</Button>
                </Form>
                <p class="text-xs text-muted-foreground">{{ available_count }} available tasks<span v-if="available_count > available_tasks.length"> · showing latest {{ available_tasks.length }} — narrow your search</span></p>
                <Form :action="`${releaseUrl}/tasks`" method="post" v-slot="{ errors, processing }" class="space-y-3">
                    <select name="task_id" required :disabled="available_tasks.length === 0" class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm">
                        <option value="" disabled selected>Select unassigned task</option>
                        <option v-for="task in available_tasks" :key="task.id" :value="task.id">{{ task.reference }} · {{ task.title }}</option>
                    </select>
                    <InputError :message="errors.task_id" />
                    <Button type="submit" :disabled="processing || available_tasks.length === 0"><Plus class="size-4" /> Add to release</Button>
                </Form>
            </CardContent>
        </Card>
        <p class="text-xs text-muted-foreground">AI-assisted changelog generation will be added in a later stage.</p>
    </div>
</template>
