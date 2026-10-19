<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, CheckCircle2, KeyRound, MoreHorizontal, Pencil, Plus, Search, ShieldCheck, ShieldOff, UsersRound } from '@lucide/vue';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import PasswordStrengthMeter from '@/components/PasswordStrengthMeter.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useI18n } from '@/composables/useI18n';

type Person = {
    id: number; name: string; email: string; locale: string;
    is_system_admin: boolean; disabled_at: string | null;
    workspace_memberships_count: number; created_at: string;
};
type Paginator = {
    data: Person[]; total: number; current_page: number; last_page: number;
    prev_page_url: string | null; next_page_url: string | null;
};
const props = defineProps<{
    users: Paginator;
    filters: { q: string; status: string };
    currentUserId: number;
}>();
const { t } = useI18n();
const search = ref(props.filters.q);
const status = ref(props.filters.status);
const userDialog = ref(false);
const editing = ref<Person | null>(null);
const userForm = useForm({ name: '', email: '', locale: 'pl', password: '', password_confirmation: '', is_system_admin: false });
const passwordDialog = ref(false);
const passwordUser = ref<Person | null>(null);
const passwordForm = useForm({ password: '', password_confirmation: '' });
const confirmationUser = ref<Person | null>(null);
const confirmationType = ref<'status' | 'role'>('status');
const actionBusy = ref(false);
const actionError = ref('');

function applyFilters(): void {
    router.get('/admin/users', { ...(search.value.trim() ? { q: search.value.trim() } : {}), ...(status.value !== 'all' ? { status: status.value } : {}) }, { preserveState: true, replace: true });
}
function openCreate(): void {
    editing.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.locale = 'pl';
    userDialog.value = true;
}
function openEdit(user: Person): void {
    editing.value = user;
    userForm.reset();
    userForm.clearErrors();
    userForm.name = user.name;
    userForm.email = user.email;
    userForm.locale = user.locale;
    userDialog.value = true;
}
function saveUser(): void {
    if (userForm.processing) return;
    if (editing.value) {
        userForm.transform(data => ({ name: data.name, email: data.email, locale: data.locale })).patch(`/admin/users/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (userDialog.value = false), onFinish: () => userForm.transform(data => data) });
    } else {
        userForm.post('/admin/users', { preserveScroll: true, onSuccess: () => { userDialog.value = false; userForm.reset(); } });
    }
}
function openPassword(user: Person): void {
    passwordUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    passwordDialog.value = true;
}
function savePassword(): void {
    if (!passwordUser.value || passwordForm.processing) return;
    passwordForm.put(`/admin/users/${passwordUser.value.id}/password`, { preserveScroll: true, onSuccess: () => { passwordDialog.value = false; passwordForm.reset(); } });
}
function confirm(user: Person, action: 'role' | 'status'): void {
    actionError.value = '';
    confirmationUser.value = user;
    confirmationType.value = action;
}
function executeAction(): void {
    const user = confirmationUser.value;
    if (!user || actionBusy.value) return;
    actionBusy.value = true;
    actionError.value = '';
    const isRole = confirmationType.value === 'role';
    router.patch(`/admin/users/${user.id}/${isRole ? 'role' : 'status'}`,
        isRole ? { is_system_admin: !user.is_system_admin } : { disabled: user.disabled_at === null },
        { preserveScroll: true,
          onSuccess: () => (confirmationUser.value = null),
          onError: errors => { actionError.value = Object.values(errors)[0] ?? t('adminUsers.error'); },
          onFinish: () => (actionBusy.value = false),
        });
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Users', href: '/admin/users' }] } });
</script>

<template>
    <Head :title="t('adminUsers.title')" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('adminUsers.title') }}</h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ t('adminUsers.subtitle') }}</p>
            </div>
            <Button @click="openCreate"><Plus class="size-4" /> {{ t('adminUsers.create') }}</Button>
        </div>

        <Card class="gap-0 py-0">
            <CardHeader class="gap-4 border-b py-5 sm:flex sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <CardTitle class="flex items-center gap-2 text-base"><UsersRound class="size-4" /> {{ t('adminUsers.accounts') }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ users.total }} {{ t('adminUsers.results') }}</p>
                </div>
                <form class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row" @submit.prevent="applyFilters">
                    <Input v-model="search" type="search" class="w-full sm:w-56" maxlength="120" :placeholder="t('adminUsers.search')" :aria-label="t('adminUsers.search')" />
                    <Select v-model="status">
                        <SelectTrigger class="w-full sm:w-36"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">{{ t('adminUsers.all') }}</SelectItem>
                            <SelectItem value="active">{{ t('adminUsers.active') }}</SelectItem>
                            <SelectItem value="disabled">{{ t('adminUsers.disabled') }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button type="submit" variant="outline" :aria-label="t('adminUsers.search')"><Search class="size-4" /></Button>
                </form>
            </CardHeader>
            <CardContent class="px-0">
                <div v-if="!users.data.length" class="py-14 text-center text-sm text-muted-foreground">{{ t('adminUsers.empty') }}</div>
                <div v-for="user in users.data" :key="user.id" class="flex items-center gap-3 border-b px-4 py-3 last:border-b-0 sm:px-6">
                    <UserAvatar :user-id="user.id" :name="user.name" compact />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate text-sm font-medium">{{ user.name }}</span>
                            <Badge v-if="user.id === currentUserId" variant="secondary">{{ t('adminUsers.you') }}</Badge>
                            <Badge v-if="user.is_system_admin" variant="secondary">{{ t('adminUsers.administrator') }}</Badge>
                            <Badge v-if="user.disabled_at" variant="destructive">{{ t('adminUsers.disabled') }}</Badge>
                        </div>
                        <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                    </div>
                    <span class="hidden text-xs text-muted-foreground sm:block">{{ user.workspace_memberships_count }} {{ t('adminUsers.workspaces') }}</span>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child><Button variant="ghost" size="icon" :aria-label="t('adminUsers.actions')"><MoreHorizontal class="size-4" /></Button></DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem @select="openEdit(user)"><Pencil class="size-4" /> {{ t('adminUsers.edit') }}</DropdownMenuItem>
                            <DropdownMenuItem @select="openPassword(user)"><KeyRound class="size-4" /> {{ t('adminUsers.changePassword') }}</DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem v-if="user.id !== currentUserId" @select="confirm(user, 'role')">
                                <ShieldOff v-if="user.is_system_admin" class="size-4" /><ShieldCheck v-else class="size-4" />
                                {{ t(user.is_system_admin ? 'adminUsers.removeAdmin' : 'adminUsers.makeAdmin') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem v-if="user.id !== currentUserId" @select="confirm(user, 'status')">
                                <CheckCircle2 v-if="user.disabled_at" class="size-4" /><Ban v-else class="size-4" />
                                {{ t(user.disabled_at ? 'adminUsers.enable' : 'adminUsers.disable') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
                <div v-if="users.last_page > 1" class="flex items-center justify-between gap-3 border-t px-4 py-3 text-xs text-muted-foreground sm:px-6">
                    <span>{{ users.current_page }} / {{ users.last_page }}</span>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="!users.prev_page_url" @click="users.prev_page_url && router.get(users.prev_page_url)">{{ t('adminUsers.previous') }}</Button>
                        <Button variant="outline" size="sm" :disabled="!users.next_page_url" @click="users.next_page_url && router.get(users.next_page_url)">{{ t('adminUsers.next') }}</Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Dialog v-model:open="userDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t(editing ? 'adminUsers.edit' : 'adminUsers.create') }}</DialogTitle>
                    <DialogDescription>{{ t(editing ? 'adminUsers.editHelp' : 'adminUsers.createHelp') }}</DialogDescription>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="saveUser">
                    <div class="grid gap-2"><Label for="system-user-name">{{ t('adminUsers.name') }}</Label><Input id="system-user-name" v-model="userForm.name" required maxlength="255" autocomplete="off" /><InputError :message="userForm.errors.name" /></div>
                    <div class="grid gap-2"><Label for="system-user-email">{{ t('adminUsers.email') }}</Label><Input id="system-user-email" v-model="userForm.email" type="email" required maxlength="255" autocomplete="off" /><InputError :message="userForm.errors.email" /></div>
                    <div class="grid gap-2"><Label for="system-user-locale">{{ t('adminUsers.language') }}</Label>
                        <Select v-model="userForm.locale"><SelectTrigger id="system-user-locale"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="pl">Polski</SelectItem><SelectItem value="en">English</SelectItem></SelectContent></Select>
                        <InputError :message="userForm.errors.locale" />
                    </div>
                    <template v-if="!editing">
                        <div class="grid gap-2"><Label for="system-user-password">{{ t('adminUsers.initialPassword') }}</Label><PasswordInput id="system-user-password" v-model="userForm.password" required minlength="6" autocomplete="new-password" /><PasswordStrengthMeter :password="userForm.password" /><InputError :message="userForm.errors.password" /></div>
                        <div class="grid gap-2"><Label for="system-user-confirm">{{ t('adminUsers.confirmPassword') }}</Label><PasswordInput id="system-user-confirm" v-model="userForm.password_confirmation" required autocomplete="new-password" /></div>
                        <div class="flex items-center gap-3"><Checkbox id="system-user-admin" :model-value="userForm.is_system_admin" @update:model-value="checked => userForm.is_system_admin = checked === true" /><Label for="system-user-admin" class="cursor-pointer">{{ t('adminUsers.administrator') }}</Label></div>
                        <InputError :message="userForm.errors.is_system_admin" />
                    </template>
                    <DialogFooter><Button type="button" variant="outline" @click="userDialog = false">{{ t('adminUsers.cancel') }}</Button><Button type="submit" :disabled="userForm.processing">{{ t('adminUsers.save') }}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="passwordDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader><DialogTitle>{{ t('adminUsers.changePassword') }}</DialogTitle><DialogDescription>{{ passwordUser?.name }} — {{ t('adminUsers.passwordHelp') }}</DialogDescription></DialogHeader>
                <form class="grid gap-4" @submit.prevent="savePassword">
                    <div class="grid gap-2"><Label for="system-new-password">{{ t('adminUsers.newPassword') }}</Label><PasswordInput id="system-new-password" v-model="passwordForm.password" required minlength="6" autocomplete="new-password" /><PasswordStrengthMeter :password="passwordForm.password" /><InputError :message="passwordForm.errors.password" /></div>
                    <div class="grid gap-2"><Label for="system-repeat-password">{{ t('adminUsers.confirmPassword') }}</Label><PasswordInput id="system-repeat-password" v-model="passwordForm.password_confirmation" required autocomplete="new-password" /></div>
                    <DialogFooter><Button type="button" variant="outline" @click="passwordDialog = false">{{ t('adminUsers.cancel') }}</Button><Button type="submit" :disabled="passwordForm.processing">{{ t('adminUsers.save') }}</Button></DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="confirmationUser !== null" @update:open="open => { if (!open && !actionBusy) confirmationUser = null; }">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t(confirmationType === 'role' ? (confirmationUser?.is_system_admin ? 'adminUsers.removeAdmin' : 'adminUsers.makeAdmin') : (confirmationUser?.disabled_at ? 'adminUsers.enable' : 'adminUsers.disable')) }}</DialogTitle>
                    <DialogDescription>{{ confirmationUser?.name }} — {{ t(confirmationType === 'role' ? 'adminUsers.roleConfirm' : 'adminUsers.statusConfirm') }}</DialogDescription>
                </DialogHeader>
                <InputError :message="actionError" />
                <DialogFooter><Button variant="outline" :disabled="actionBusy" @click="confirmationUser = null">{{ t('adminUsers.cancel') }}</Button><Button :disabled="actionBusy" @click="executeAction">{{ t('adminUsers.confirm') }}</Button></DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
