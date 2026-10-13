<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Ban,
    CheckCircle2,
    LoaderCircle,
    MoreHorizontal,
    Plus,
    Search,
    UserMinus,
    Users,
} from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
    role: WorkspaceRole;
    joined_at: string | null;
    is_you: boolean;
    is_suspended: boolean;
    can_manage: boolean;
};

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
const addForm = useForm({ email: '', role: 'member' });
const baseUrl = `/workspaces/${props.workspaceItem.id}/members`;

const labels: Record<WorkspaceRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
};

// Low-saturation tones, consistently derived from the *user* ID rather than membership ID.
const avatarTones = [
    'bg-[#eef1f4] text-[#526173] dark:bg-[#2a3540] dark:text-[#ced7e2]',
    'bg-[#eeedf2] text-[#625e71] dark:bg-[#34313e] dark:text-[#d3cddd]',
    'bg-[#edf1ed] text-[#53685b] dark:bg-[#2c3a33] dark:text-[#cfdbd1]',
    'bg-[#f2efe9] text-[#776b59] dark:bg-[#3d382f] dark:text-[#ded5c4]',
    'bg-[#edf0f1] text-[#58686d] dark:bg-[#2c373a] dark:text-[#d0dbdd]',
] as const;

function avatarTone(userId: number): string {
    return avatarTones[Math.abs(userId) % avatarTones.length] ?? avatarTones[0];
}

function openAddMember(): void {
    addForm.reset();
    addForm.clearErrors();
    addForm.role = 'member';
    addOpen.value = true;
}

function closeAddMember(): void {
    if (addForm.processing) return;
    addOpen.value = false;
    addForm.reset();
    addForm.clearErrors();
}

function saveMember(): void {
    if (addForm.processing) return;
    addForm.email = addForm.email.trim();
    addForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: closeAddMember,
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

function initials(name: string): string {
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.charAt(0) ?? '';
    const last =
        parts.length > 1 ? (parts[parts.length - 1]?.charAt(0) ?? '') : '';

    return (first + last).toLocaleUpperCase() || '?';
}

function formattedJoinDate(value: string): string {
    const date = /^\d{4}-\d{2}-\d{2}/.exec(value)?.[0];
    if (!date) return value;

    const parsed = new Date(`${date}T00:00:00Z`);
    if (Number.isNaN(parsed.getTime())) return value;

    return new Intl.DateTimeFormat('en', {
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
</script>

<template>
    <Head :title="`Members · ${workspaceItem.name}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="space-y-3">
            <Button variant="ghost" size="sm" as-child class="-ml-3 w-fit">
                <Link href="/workspaces">
                    <ArrowLeft class="size-4" /> Back to workspaces
                </Link>
            </Button>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Members</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    People with access to {{ workspaceItem.name }}.
                </p>
            </div>
        </div>

        <Card class="gap-0 py-0">
            <CardHeader
                class="gap-4 border-b py-5 sm:flex sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <Users class="size-4" /> Workspace members
                    </CardTitle>
                    <p class="text-sm text-muted-foreground">
                        {{ members.total }}
                        {{ members.total === 1 ? 'person' : 'people' }}
                        {{ filters.q ? 'found' : 'in this workspace' }}
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
                            aria-label="Search members by name or email"
                            placeholder="Search name or email"
                            maxlength="120"
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            aria-label="Search members"
                        >
                            <Search class="size-4" />
                        </Button>
                    </form>
                    <Button
                        type="button"
                        class="shrink-0"
                        @click="openAddMember"
                    >
                        <Plus class="size-4" /> Add member
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
                        <Avatar
                            class="size-11 ring-1 ring-border/60 sm:size-12"
                        >
                            <AvatarFallback
                                :class="[
                                    avatarTone(member.user_id),
                                    'text-sm font-semibold tracking-tight',
                                ]"
                            >
                                {{ initials(member.name) }}
                            </AvatarFallback>
                        </Avatar>

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
                                    You
                                </Badge>
                                <Badge
                                    v-if="member.is_suspended"
                                    variant="outline"
                                    class="px-2 py-0 text-[11px] text-muted-foreground"
                                >
                                    Suspended
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
                                Joined {{ formattedJoinDate(member.joined_at) }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:gap-5">
                            <span
                                v-if="member.joined_at"
                                class="hidden text-xs text-muted-foreground sm:inline"
                            >
                                Joined {{ formattedJoinDate(member.joined_at) }}
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
                                {{ labels[member.role] }}
                            </Badge>
                            <DropdownMenu v-if="member.can_manage">
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 shrink-0"
                                        :aria-label="`Manage ${member.name}`"
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
                                        <CheckCircle2 class="size-4" /> Restore
                                        access
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-else
                                        @select="
                                            openMemberAction(member, 'suspend')
                                        "
                                    >
                                        <Ban class="size-4" /> Suspend access
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        variant="destructive"
                                        @select="
                                            openMemberAction(member, 'remove')
                                        "
                                    >
                                        <UserMinus class="size-4" /> Remove from
                                        workspace
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
                    <p class="text-sm font-medium">No members found</p>
                    <p class="text-sm text-muted-foreground">
                        {{
                            filters.q
                                ? 'Try another name or email.'
                                : 'This workspace has no members yet.'
                        }}
                    </p>
                    <Button
                        v-if="filters.q"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="baseUrl">Clear search</Link>
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
                <Link :href="members.prev_page_url">Previous</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled
                >Previous</Button
            >
            <span class="text-xs text-muted-foreground"
                >Page {{ members.current_page }} of
                {{ members.last_page }}</span
            >
            <Button
                v-if="members.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="members.next_page_url">Next</Link>
            </Button>
            <Button v-else variant="outline" size="sm" disabled>Next</Button>
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
                                ? 'Remove member?'
                                : memberAction === 'suspend'
                                  ? 'Suspend access?'
                                  : 'Restore access?'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="memberAction === 'remove'">
                            Remove {{ actionTarget?.name }} from
                            {{ workspaceItem.name }}? This revokes workspace and
                            project access, but preserves their tasks, comments
                            and worklog history. They can be added again later.
                        </template>
                        <template v-else-if="memberAction === 'suspend'">
                            Suspend {{ actionTarget?.name }}'s access to
                            {{ workspaceItem.name }}? Their account and project
                            assignments will be preserved until access is
                            restored.
                        </template>
                        <template v-else>
                            Restore {{ actionTarget?.name }}'s previous access
                            and role in {{ workspaceItem.name }}?
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="actionBusy"
                        @click="closeMemberAction"
                        >Cancel</Button
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
                                ? 'Remove member'
                                : memberAction === 'suspend'
                                  ? 'Suspend access'
                                  : 'Restore access'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog
            :open="addOpen"
            @update:open="
                (open) => {
                    if (!open) closeAddMember();
                }
            "
        >
            <DialogContent
                :show-close-button="!addForm.processing"
                class="sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Add member</DialogTitle>
                    <DialogDescription>
                        Add someone who already has a TaskFlow account to
                        {{ workspaceItem.name }}. Enter their exact email
                        address.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="saveMember">
                    <div class="space-y-2">
                        <Label for="new-member-email">Email address</Label>
                        <Input
                            id="new-member-email"
                            v-model="addForm.email"
                            type="email"
                            autocomplete="email"
                            placeholder="person@example.com"
                            maxlength="255"
                            :disabled="addForm.processing"
                            :aria-invalid="Boolean(addForm.errors.email)"
                            required
                        />
                        <InputError :message="addForm.errors.email" />
                    </div>

                    <div class="space-y-2">
                        <Label for="new-member-role">Workspace role</Label>
                        <Select
                            v-model="addForm.role"
                            :disabled="addForm.processing"
                        >
                            <SelectTrigger
                                id="new-member-role"
                                class="w-full"
                                :aria-invalid="Boolean(addForm.errors.role)"
                            >
                                <SelectValue placeholder="Choose role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="member">Member</SelectItem>
                                <SelectItem
                                    v-if="can_assign_admin"
                                    value="admin"
                                    >Admin</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">
                            {{
                                can_assign_admin
                                    ? 'Members can collaborate; admins can also manage workspace members.'
                                    : 'You can add regular members. Only the owner can appoint admins.'
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
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                addForm.processing || !addForm.email.trim()
                            "
                        >
                            <LoaderCircle
                                v-if="addForm.processing"
                                class="size-4 animate-spin"
                            />
                            Add member
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
