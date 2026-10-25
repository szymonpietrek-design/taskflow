<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { ChevronsUpDown, LoaderCircle, Search } from '@lucide/vue';
import UserAvatar from '@/components/UserAvatar.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Candidate = { id: number; name: string; email: string };

const props = defineProps<{ projectId: number; canManageManagers: boolean }>();
const open = defineModel<boolean>('open', { required: true });
const { t } = useI18n();
const baseUrl = computed(() => `/projects/${props.projectId}/members`);
const query = ref('');
const selected = ref<Candidate | null>(null);
const candidates = ref<Candidate[]>([]);
const busy = ref(false);
const failed = ref(false);
const hasMore = ref(false);
const showOptions = ref(false);
const form = useForm({ user_id: null as number | null, role: 'member' });
let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;
let generation = 0;

function stopSearch(): void {
    ++generation;
    if (timer !== null) clearTimeout(timer);
    timer = null;
    controller?.abort();
    controller = null;
}
async function searchUsers(text: string): Promise<void> {
    const id = ++generation;
    controller?.abort();
    const abort = new AbortController();
    controller = abort;
    busy.value = true;
    failed.value = false;
    try {
        const q = text.trim();
        const url = `${baseUrl.value}/candidates${q ? `?q=${encodeURIComponent(q)}` : ''}`;
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: abort.signal });
        if (!response.ok) throw new Error(String(response.status));
        const data = (await response.json()) as { users: Candidate[]; has_more: boolean };
        if (id !== generation) return;
        candidates.value = data.users;
        hasMore.value = data.has_more;
    } catch {
        if (id !== generation || abort.signal.aborted) return;
        failed.value = true;
        candidates.value = [];
    } finally {
        if (id === generation) busy.value = false;
    }
}
function onQuery(value: string | number): void {
    query.value = String(value);
    showOptions.value = true;
    stopSearch();
    busy.value = true;
    timer = setTimeout(() => { timer = null; void searchUsers(query.value); }, 200);
}
function editSelection(): void {
    if (form.processing) return;
    query.value = '';
    showOptions.value = true;
    stopSearch();
    void searchUsers('');
    void nextTick(() => { document.getElementById('project-member-search')?.focus(); });
}
function cancelChoosing(): void {
    showOptions.value = false;
    query.value = '';
    stopSearch();
}
function choose(user: Candidate): void {
    selected.value = user;
    form.user_id = user.id;
    query.value = '';
    form.clearErrors('user_id');
    showOptions.value = false;
    stopSearch();
}
function resetForm(): void {
    stopSearch();
    form.reset();
    form.clearErrors();
    query.value = '';
    selected.value = null;
    candidates.value = [];
    showOptions.value = false;
    busy.value = false;
    failed.value = false;
    hasMore.value = false;
}
function beginAdd(): void {
    resetForm();
    showOptions.value = true;
    void searchUsers('');
    void nextTick(() => { document.getElementById('project-member-search')?.focus(); });
}
function closeAdd(): void {
    if (form.processing) return;
    open.value = false;
}
function submit(): void {
    if (form.processing || !selected.value || showOptions.value) return;
    form.user_id = selected.value.id;
    form.post(baseUrl.value, {
        preserveScroll: true,
        onSuccess: () => {
            // Inertia can invoke onSuccess before form.processing becomes false.
            open.value = false;
        },
    });
}
watch(open, value => {
    if (value) beginAdd();
    else resetForm();
});
onBeforeUnmount(stopSearch);
</script>

<template>
    <Dialog :open="open" @update:open="value => { if (!value) closeAdd(); }">
            <DialogContent class="sm:max-w-md" :show-close-button="!form.processing">
                <DialogHeader><DialogTitle>{{ t('team.add') }}</DialogTitle><DialogDescription>{{ t('team.addDescription') }}</DialogDescription></DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="relative space-y-2">
                        <Label for="project-member-search">{{ t('membersPicker.chooseUser') }}</Label>
                        <button
                            v-if="selected && !showOptions"
                            type="button"
                            class="flex w-full min-w-0 items-center gap-3 rounded-md border p-2 text-left transition-colors hover:bg-accent/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            :aria-label="t('membersPicker.changeUser')"
                            :disabled="form.processing"
                            @click="editSelection"
                        >
                            <UserAvatar :user-id="selected.id" :name="selected.name" compact />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ selected.name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ selected.email }}</span>
                            </span>
                            <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        </button>
                        <template v-else>
                            <div class="relative"><Search class="pointer-events-none absolute left-3 top-2.5 size-4 text-muted-foreground" />
                                <Input id="project-member-search" :model-value="query" :placeholder="t('team.search')" class="pl-9" autocomplete="off" @update:model-value="onQuery" @focus="showOptions = true" @keydown.escape.stop.prevent="cancelChoosing" />
                            </div>
                            <div v-if="showOptions" class="absolute inset-x-0 top-full z-50 mt-1 max-h-56 overflow-y-auto rounded-md border bg-popover shadow-lg">
                                <div v-if="busy" class="flex items-center gap-2 p-3 text-sm text-muted-foreground"><LoaderCircle class="size-4 animate-spin" /> {{ t('membersPicker.loading') }}</div>
                                <p v-else-if="failed" class="p-3 text-sm text-destructive">{{ t('membersPicker.error') }} <Button type="button" variant="ghost" size="sm" @click="searchUsers(query)">{{ t('membersPicker.retry') }}</Button></p>
                                <template v-else-if="candidates.length">
                                    <button v-for="user in candidates" :key="user.id" type="button" class="flex w-full min-w-0 items-center gap-3 px-3 py-2 text-left hover:bg-accent focus:bg-accent focus:outline-none" @click="choose(user)">
                                        <UserAvatar :user-id="user.id" :name="user.name" />
                                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-medium">{{ user.name }}</span><span class="block truncate text-xs text-muted-foreground">{{ user.email }}</span></span>
                                    </button>
                                    <p v-if="hasMore" class="border-t p-2 text-xs text-muted-foreground">{{ t('membersPicker.moreResults') }}</p>
                                </template>
                                <p v-else class="p-3 text-sm text-muted-foreground">{{ t('team.noResults') }}</p>
                            </div>
                        </template>
                        <InputError :message="form.errors.user_id" />
                    </div>
                    <div class="space-y-2"><Label>{{ t('team.role') }}</Label>
                        <Select v-model="form.role"><SelectTrigger><SelectValue /></SelectTrigger><SelectContent>
                            <SelectItem value="member">{{ t('team.member') }}</SelectItem>
                            <SelectItem v-if="canManageManagers" value="manager">{{ t('team.manager') }}</SelectItem>
                        </SelectContent></Select><InputError :message="form.errors.role" />
                    </div>
                    <DialogFooter class="gap-2"><Button type="button" variant="outline" :disabled="form.processing" @click="closeAdd">{{ t('stage2.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing || !selected || showOptions"><LoaderCircle v-if="form.processing" class="size-4 animate-spin" />{{ t('team.add') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
    </Dialog>
</template>
