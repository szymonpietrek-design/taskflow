<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    LoaderCircle,
    MoreHorizontal,
    Plus,
    Search,
    X,
    UserMinus,
    Users,
} from '@lucide/vue';
import { onBeforeUnmount, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { WorkspaceRole } from '@/types/workspace';

type Member = {
    id: number;
    user_id: number;
    name: string;
    email: string;
    role: Exclude<WorkspaceRole, 'member'>;
    joined_at: string | null;
    is_you: boolean;
    is_suspended: boolean;
    can_manage: boolean;
};

type Candidate = { id: number; name: string; email: string };
type CandidateResponse = { users: Candidate[]; has_more: boolean };

type MemberPage = {
    data: Member[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    workspaceItem: { id: number; name: string };
    filters: { q: string };
    members: MemberPage;
    can_assign_admin: boolean;
}>();

const search = ref(props.filters.q);
const addOpen = ref(false);
type MemberAction = 'suspend' | 'restore' | 'remove';
const actionTarget = ref<Member | null>(null);
const memberAction = ref<MemberAction>('suspend');
const actionBusy = ref(false);
const addForm = useForm({ email: '', role: 'admin' });
const baseUrl = `/workspaces/${props.workspaceItem.id}/members`;

// The form posts the selected account email to the existing membership endpoint.
const candidateQuery = ref('');
const selectedCandidate = ref<Candidate | null>(null);
const candidates = ref<Candidate[]>([]);
const candidatesOpen = ref(false);
const candidatesBusy = ref(false);
const candidatesFailed = ref(false);
const hasMoreCandidates = ref(false);
const activeCandidate = ref(0);
const pickerRoot = ref<HTMLElement | null>(null);
let candidateTimer: ReturnType<typeof setTimeout> | null = null;
let candidateAbort: AbortController | null = null;
let candidateRequestId = 0;

function cancelCandidateSearch(): void {
    candidateRequestId += 1;
    if (candidateTimer !== null) clearTimeout(candidateTimer);
    candidateTimer = null;
    candidateAbort?.abort();
    candidateAbort = null;
}

async function fetchCandidates(query: string): Promise<void> {
    const requestId = ++candidateRequestId;
    candidateAbort?.abort();
    const controller = new AbortController();
    candidateAbort = controller;
    candidatesBusy.value = true;
    candidatesFailed.value = false;
    try {
        // An empty query means "show available accounts"; omit q rather than sending ?q=.
        // Laravel converts empty query values to null before request validation.
        const search = query.trim();
        const url = `${baseUrl}/candidates${search ? `?q=${encodeURIComponent(search)}` : ''}`;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const result = (await response.json()) as CandidateResponse;
        if (requestId !== candidateRequestId) return;
        candidates.value = result.users;
        hasMoreCandidates.value = result.has_more;
        activeCandidate.value = 0;
    } catch {
        if (requestId !== candidateRequestId || controller.signal.aborted) return;
        candidatesFailed.value = true;
        candidates.value = [];
    } finally {
        if (requestId === candidateRequestId) candidatesBusy.value = false;
    }
}

function scheduleCandidates(query: string, immediate = false): void {
    cancelCandidateSearch();
    candidatesBusy.value = true;
    candidatesFailed.value = false;
    candidates.value = [];
    candidateTimer = setTimeout(() => {
        candidateTimer = null;
        void fetchCandidates(query.trim());
    }, immediate ? 0 : 250);
}

function updateCandidateQuery(value: string | number): void {
    candidateQuery.value = String(value);
    selectedCandidate.value = null;
    addForm.email = '';
    candidatesOpen.value = true;
    activeCandidate.value = 0;
    scheduleCandidates(candidateQuery.value);
}

function chooseCandidate(candidate: Candidate): void {
    selectedCandidate.value = candidate;
    addForm.email = candidate.email;
    addForm.clearErrors('email');
    candidatesOpen.value = false;
    cancelCandidateSearch();
}

function clearCandidate(): void {
    selectedCandidate.value = null;
    addForm.email = '';
    addForm.clearErrors('email');
    candidateQuery.value = '';
    candidatesOpen.value = true;
    scheduleCandidates('', true);
}

function onPickerFocusOut(event: FocusEvent): void {
    const next = event.relatedTarget;
    if (next instanceof Node && pickerRoot.value?.contains(next)) return;
    candidatesOpen.value = false;
}

function showCandidates(): void {
    if (!addForm.processing) candidatesOpen.value = true;
}

function onCandidateKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && candidatesOpen.value) {
        event.preventDefault();
        event.stopPropagation();
        candidatesOpen.value = false;
        return;
    }
    if (!candidatesOpen.value && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        showCandidates();
        activeCandidate.value = event.key === 'ArrowUp' ? Math.max(candidates.value.length - 1, 0) : 0;
        return;
    }
    if (!candidatesOpen.value || !candidates.value.length) return;
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeCandidate.value = (activeCandidate.value + 1) % candidates.value.length;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeCandidate.value = (activeCandidate.value - 1 + candidates.value.length) % candidates.value.length;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const candidate = candidates.value[activeCandidate.value];
        if (candidate) chooseCandidate(candidate);
    }
}

onBeforeUnmount(cancelCandidateSearch);

const roleLabelKeys: Record<Member['role'], string> = {
    owner: 'audit.owner',
    admin: 'stage2.admin',
};

function openAddMember(): void {
    addForm.reset();
    addForm.clearErrors();
    addForm.role = 'admin';
    candidateQuery.value = '';
    selectedCandidate.value = null;
    // The dialog autofocuses the search field; offer available users immediately.
    candidatesOpen.value = true;
    addOpen.value = true;
    scheduleCandidates('', true);
}

// Inertia calls onSuccess before it has necessarily cleared form.processing.
// A successful save must always close the dialog, even during that callback.
function finishAddMember(): void {
    addOpen.value = false;
    cancelCandidateSearch();
    candidatesOpen.value = false;
    selectedCandidate.value = null;
    addForm.reset();
    addForm.clearErrors();
}

function closeAddMember(): void {
    if (addForm.processing) return;
    finishAddMember();
}

function saveMember(): void {
    if (addForm.processing || !selectedCandidate.value) return;
    addForm.email = selectedCandidate.value.email;
    addForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: finishAddMember,
    });
}

function openMemberAction(member: Member, action: MemberAction): void {
    if (actionBusy.value || !member.can_manage) return;
    actionTarget.value = member;
    memberAction.value = action;
}

function closeMemberAction(): void {
    if (actionBusy.value) return;
    actionTarget.value = null;
}

function submitMemberAction(): void {
    const member = actionTarget.value;
    if (!member || !member.can_manage || actionBusy.value) return;
    actionBusy.value = true;
    const url = `${baseUrl}/${member.id}`;
    const options = {
        preserveScroll: true,
        onFinish: () => {
            actionBusy.value = false;
            actionTarget.value = null;
        },
    };
    if (memberAction.value === 'remove') {
        router.delete(url, options);
    } else {
        router.patch(
            `${url}/${memberAction.value === 'suspend' ? 'suspend' : 'restore'}`,
            {},
            options,
        );
    }
}

function formattedJoinDate(value: string): string {
    const date = /^\d{4}-\d{2}-\d{2}/.exec(value)?.[0];
    if (!date) return value;

    const parsed = new Date(`${date}T00:00:00Z`);
    if (Number.isNaN(parsed.getTime())) return value;

    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(parsed);
}

function submitSearch(): void {
    const q = search.value.trim();
    router.get(baseUrl, q ? { q } : {}, {
        preserveState: true,
        preserveScroll: true,
    });
}

defineOptions({
    layout: { breadcrumbs: [{ title: 'Workspaces', href: '/workspaces' }] },
});
const { t, locale, formatText } = useI18n();
</script>

<template>
    <Head :title="formatText('audit.members_of', { workspace: workspaceItem.name })" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link href="/workspaces">
                    <ArrowLeft class="size-4" /> {{ t('stage2.back_to_workspaces') }}
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('team.workspaceAdmins') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ formatText('audit.workspace_people', { workspace: workspaceItem.name }) }}
                </p>
            </div>
        </div>

        <Card class="gap-0 py-0">
            <CardHeader
                class="gap-4 border-b py-5 sm:flex sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Users class="size-4" /> {{ t('stage2.workspace_members') }}
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ formatText(filters.q ? 'audit.members_found' : 'audit.members_total', { count: members.total }) }}
                    </p>
                </div>
                <div
                    class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center"
                >
                    <form
                        class="flex w-full gap-2 sm:w-64 lg:w-72"
                        role="search"
                        @submit.prevent="submitSearch"
                    >
                        <Input
                            v-model="search"
                            name="q"
                            type="search"
                            :aria-label="t('stage2.search_members_by_name_or_email')"
                            :placeholder="t('stage2.search_name_or_email')"
                            maxlength="120"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :aria-label="t('stage2.search_members')"
                        >
                            <Search class="size-4" />
                        </Button>
                    </form>
                    <Button
                        v-if="can_assign_admin"
                        type="button"
                        class="shrink-0"
                        @click="openAddMember"
                    >
                        <Plus class="size-4" /> {{ t('team.workspaceAdmins') }}
                    </Button>
                </div>
            </CardHeader>

            <CardContent class="px-0">
                <div v-if="members.data.length" class="divide-y">
                    <div
                        v-for="member in members.data"
                        :key="member.id"
                        class="flex items-center gap-3 px-4 py-4 transition-colors hover:bg-muted/30 sm:gap-4 sm:px-6"
                    >
                        <UserAvatar :user-id="member.user_id" :name="member.name" />

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <span
                                    class="min-w-0 truncate text-sm font-semibold sm:text-base"
                                >
                                    {{ member.name }}
                                </span>
                                <Badge
                                    v-if="member.is_you"
                                    variant="secondary"
                                    class="px-2 py-0 text-[11px]"
                                >
                                    {{ t('stage2.you') }}
                                </Badge>
                                <Badge
                                    v-if="member.is_suspended"
                                    variant="outline"
                                    class="px-2 py-0 text-[11px] text-muted-foreground"
                                >
                                    {{ t('stage2.suspended') }}
                                </Badge>
                            </div>
                            <p
                                class="mt-0.5 truncate text-sm text-muted-foreground"
                                :title="member.email"
                            >
                                {{ member.email }}
                            </p>
                            <p
                                v-if="member.joined_at"
                                class="mt-1 text-xs text-muted-foreground sm:hidden"
                            >
                                {{ t('audit.joined') }} {{ formattedJoinDate(member.joined_at) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:gap-5">
                            <span
                                v-if="member.joined_at"
                                class="hidden text-xs text-muted-foreground sm:inline"
                            >
                                {{ t('audit.joined') }} {{ formattedJoinDate(member.joined_at) }}
                            </span>
                            <Badge
                                :variant="
                                    member.role === 'owner'
                                        ? 'default'
                                        : member.role === 'admin'
                                          ? 'secondary'
                                          : 'outline'
                                "
                                class="min-w-17 justify-center"
                            >
                                {{ t(roleLabelKeys[member.role]) }}
                            </Badge>
                            <DropdownMenu v-if="member.can_manage">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 shrink-0"
                                        :aria-label="formatText('audit.manage_member', { name: member.name })"
                                    >
                                        <MoreHorizontal class="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuItem
                                        v-if="member.is_suspended"
                                        @select="
                                            openMemberAction(member, 'restore')
                                        "
                                    >
                                        <CheckCircle2 class="size-4" /> {{ t('audit.restore_access') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else
                                        @select="
                                            openMemberAction(member, 'suspend')
                                        "
                                    >
                                        <Ban class="size-4" /> {{ t('stage2.suspend_access') }}
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @select="
                                            openMemberAction(member, 'remove')
                                        "
                                    >
                                        <UserMinus class="size-4" /> {{ t('audit.remove_from_workspace') }}
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 px-5 py-12 text-center"
                >
                    <Users class="size-8 text-muted-foreground" />
                    <p class="text-sm font-medium">{{ t('stage2.no_members_found') }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            filters.q
                                ? t('audit.try_another_name_or_email')
                                : t('audit.this_workspace_has_no_members_yet')
                        }}
                    </p>
                    <Button
                        v-if="filters.q"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="baseUrl">{{ t('stage2.clear_search') }}</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="members.last_page > 1"
            class="flex items-center justify-between gap-3"
        >
            <Button
                v-if="members.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="members.prev_page_url">{{ t('stage2.previous') }}</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled
                >{{ t('stage2.previous') }}</Button
            >
            <span class="text-xs text-muted-foreground"
                >{{ formatText('audit.count_pages', { page: members.current_page, pages: members.last_page }) }}</span
            >
            <Button
                v-if="members.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="members.next_page_url">{{ t('stage2.next') }}</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>{{ t('stage2.next') }}</Button>
        </div>
        <Dialog
            :open="actionTarget !== null"
            @update:open="
                (open) => {
                    if (!open) closeMemberAction();
                }
            "
        >
            <DialogContent :show-close-button="!actionBusy" class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            memberAction === 'remove'
                                ? t('audit.remove_member')
                                : memberAction === 'suspend'
                                  ? t('audit.suspend_access')
                                  : t('audit.restore_access_196')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            formatText(
                                memberAction === 'remove'
                                    ? 'audit.workspace_remove_confirm'
                                    : memberAction === 'suspend'
                                      ? 'audit.workspace_suspend_confirm'
                                      : 'audit.workspace_restore_confirm',
                                {
                                    name: actionTarget?.name ?? '',
                                    workspace: workspaceItem.name,
                                },
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="actionBusy"
                        @click="closeMemberAction"
                        >{{ t('stage2.cancel') }}</Button
                    >
                    <Button
                        type="button"
                        :variant="
                            memberAction === 'restore'
                                ? 'default'
                                : 'destructive'
                        "
                        :disabled="actionBusy"
                        @click="submitMemberAction"
                    >
                        <LoaderCircle
                            v-if="actionBusy"
                            class="size-4 animate-spin"
                        />
                        {{
                            memberAction === 'remove'
                                ? t('audit.remove_member_197')
                                : memberAction === 'suspend'
                                  ? t('audit.suspend_access_198')
                                  : t('audit.restore_access')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog
            v-if="can_assign_admin"
            :open="addOpen"
            @update:open="
                (open) => {
                    if (!open) closeAddMember();
                }
            "
        >
            <DialogContent
                :show-close-button="!addForm.processing"
                class="sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>{{ t('team.workspaceAdmins') }}</DialogTitle>
                    <DialogDescription>
                        {{ formatText('team.workspaceInvitation', { workspace: workspaceItem.name }) }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="saveMember">
                    <div class="space-y-2">
                        <Label :for="selectedCandidate ? undefined : 'new-member-search'">{{ t('membersPicker.chooseUser') }}</Label>
                        <div v-if="selectedCandidate" class="flex items-center gap-3 rounded-md border bg-muted/20 px-3 py-2">
                            <UserAvatar :user-id="selectedCandidate.id" :name="selectedCandidate.name" compact />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">{{ selectedCandidate.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ selectedCandidate.email }}</p>
                            </div>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8 shrink-0"
                                :disabled="addForm.processing"
                                :aria-label="t('membersPicker.changeUser')"
                                @click="clearCandidate"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                        <div v-else ref="pickerRoot" class="relative" @focusout="onPickerFocusOut">
                            <Input
                                id="new-member-search"
                                :model-value="candidateQuery"
                                role="combobox"
                                aria-autocomplete="list"
                                :aria-controls="candidatesOpen ? 'member-candidates-list' : undefined"
                                :aria-expanded="candidatesOpen"
                                :aria-activedescendant="candidatesOpen && candidates.length ? `candidate-option-${candidates[activeCandidate]?.id}` : undefined"
                                :placeholder="t('membersPicker.searchPlaceholder')"
                                autocomplete="off"
                                maxlength="100"
                                :disabled="addForm.processing"
                                :aria-invalid="Boolean(addForm.errors.email)"
                                @update:model-value="updateCandidateQuery"
                                @focus="showCandidates"
                                @click="showCandidates"
                                @keydown="onCandidateKeydown"
                            />
                            <div
                                v-if="candidatesOpen"
                                id="member-candidates-list"
                                role="listbox"
                                :aria-label="t('membersPicker.availableUsers')"
                                class="absolute inset-x-0 top-full z-50 mt-1 max-h-56 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-lg"
                            >
                                <p v-if="candidatesBusy" class="flex items-center gap-2 px-3 py-3 text-sm text-muted-foreground" role="status">
                                    <LoaderCircle class="size-4 animate-spin" /> {{ t('membersPicker.loading') }}
                                </p>
                                <div v-else-if="candidatesFailed" class="space-y-2 px-3 py-3 text-sm">
                                    <p class="text-destructive">{{ t('membersPicker.error') }}</p>
                                    <Button type="button" variant="outline" size="sm" @click="scheduleCandidates(candidateQuery, true)">
                                        {{ t('membersPicker.retry') }}
                                    </Button>
                                </div>
                                <p v-else-if="!candidates.length" class="px-3 py-3 text-sm text-muted-foreground">
                                    {{ t('membersPicker.noResults') }}
                                </p>
                                <template v-else>
                                    <button
                                        v-for="(candidate, index) in candidates"
                                        :id="`candidate-option-${candidate.id}`"
                                        :key="candidate.id"
                                        type="button"
                                        role="option"
                                        :aria-selected="index === activeCandidate"
                                        class="flex w-full items-center gap-3 rounded-sm px-2 py-2 text-left hover:bg-accent hover:text-accent-foreground"
                                        :class="index === activeCandidate ? 'bg-accent text-accent-foreground' : ''"
                                        @mouseenter="activeCandidate = index"
                                        @click="chooseCandidate(candidate)"
                                    >
                                        <UserAvatar :user-id="candidate.id" :name="candidate.name" compact />
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium">{{ candidate.name }}</span>
                                            <span class="block truncate text-xs text-muted-foreground">{{ candidate.email }}</span>
                                        </span>
                                    </button>
                                    <p v-if="hasMoreCandidates" class="border-t px-3 py-2 text-xs text-muted-foreground">
                                        {{ t('membersPicker.moreResults') }}
                                    </p>
                                </template>
                            </div>
                        </div>
                        <p class="text-xs text-muted-foreground">{{ t('team.onlyExistingAdmins') }}</p>
                        <InputError :message="addForm.errors.email" />
                    </div>

                    <div class="space-y-2">
                        <Label for="new-member-role">{{ t('stage2.workspace_role') }}</Label>
                        <Select
                            v-model="addForm.role"
                            :disabled="addForm.processing"
                        >
                            <SelectTrigger
                                id="new-member-role"
                                class="w-full"
                                :aria-invalid="Boolean(addForm.errors.role)"
                            >
                                <SelectValue :placeholder="t('stage2.choose_role')" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="admin">{{ t('stage2.admin') }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            {{
                                t('team.ownerControlsAdmins')
                            }}
                        </p>
                        <InputError :message="addForm.errors.role" />
                    </div>

                    <DialogFooter class="gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="addForm.processing"
                            @click="closeAddMember"
                        >
                            {{ t('stage2.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                addForm.processing || !selectedCandidate
                            "
                        >
                            <LoaderCircle
                                v-if="addForm.processing"
                                class="size-4 animate-spin"
                            />
                            {{ t('team.workspaceAdmins') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
