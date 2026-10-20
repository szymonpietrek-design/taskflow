<script setup lang="ts">
import { useI18n } from '@/composables/useI18n';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { ArrowLeft, Plus, Trash2 } from '@lucide/vue';
import UserAvatar from '@/components/UserAvatar.vue';
import AddProjectMemberDialog from '@/components/projects/AddProjectMemberDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Member = { id: number; user_id: number; name: string; email: string; role: 'manager' | 'member'; is_disabled: boolean; can_manage: boolean };
type Administrator = { user_id: number; name: string; email: string; role: 'owner' | 'admin' };

const props = defineProps<{
    project: { id: number; name: string; key: string; is_archived: boolean };
    members: Member[];
    administrators: Administrator[];
    can_manage: boolean;
    can_manage_managers: boolean;
}>();
const { t } = useI18n();
const baseUrl = computed(() => `/projects/${props.project.id}/members`);
const isAddMemberDialogOpen = ref(false);
const removing = ref<Member | null>(null);
const actionBusy = ref(false);

function changeRole(member: Member, value: string | number): void {
    if (actionBusy.value || !props.can_manage_managers || !member.can_manage || !['manager','member'].includes(String(value))) return;
    actionBusy.value = true;
    router.patch(`${baseUrl.value}/${member.id}`, { role: String(value) }, {
        preserveScroll: true, onFinish: () => { actionBusy.value = false; },
    });
}
function remove(): void {
    if (!removing.value || actionBusy.value) return;
    actionBusy.value = true;
    router.delete(`${baseUrl.value}/${removing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { removing.value = null; },
        onFinish: () => { actionBusy.value = false; },
    });
}
defineOptions({ layout: { breadcrumbs: [{ title: 'Projects', href: '/projects' }] } });
</script>

<template>
    <Head :title="`${t('team.title')} · ${project.name}`" />
    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="space-y-2">
                <Link :href="`/projects/${project.id}`" class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground">
                    <ArrowLeft class="size-4" /> {{ t('team.back') }}
                </Link>
                <h1 class="text-2xl font-semibold tracking-tight">{{ t('team.title') }}</h1>
                <p class="text-sm text-muted-foreground">{{ project.name }} · {{ t('team.description') }}</p>
            </div>
            <Button v-if="can_manage && !project.is_archived" @click="isAddMemberDialogOpen = true"><Plus class="size-4" /> {{ t('team.add') }}</Button>
        </div>
        <Card>
            <CardHeader><CardTitle class="text-base">{{ t('team.inherited') }}</CardTitle>
                <p class="text-sm text-muted-foreground">{{ t('team.inheritedInfo') }}</p>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="person in administrators" :key="person.user_id" class="flex items-center gap-3 rounded-lg border px-4 py-3">
                    <UserAvatar :user-id="person.user_id" :name="person.name" />
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-medium">{{ person.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ person.email }}</div>
                    </div>
                    <Badge variant="secondary">{{ person.role === 'owner' ? t('audit.owner') : t('stage2.admin') }}</Badge>
                </div>
            </CardContent>
        </Card>
        <Card>
            <CardHeader><CardTitle class="text-base">{{ t('team.projectMembers') }}</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div v-for="person in members" :key="person.id" class="flex items-center gap-3 rounded-lg border px-4 py-3">
                    <UserAvatar :user-id="person.user_id" :name="person.name" />
                    <div class="min-w-0 flex-1"><div class="truncate text-sm font-medium">{{ person.name }}</div>
                        <div class="truncate text-xs text-muted-foreground">{{ person.email }}</div>
                        <span v-if="person.is_disabled" class="text-xs text-destructive">{{ t('team.disabled') }}</span>
                    </div>
                    <Select v-if="can_manage_managers && person.can_manage" :model-value="person.role" :disabled="actionBusy" @update:model-value="value => changeRole(person, String(value))">
                        <SelectTrigger class="w-32"><SelectValue /></SelectTrigger>
                        <SelectContent><SelectItem value="member">{{ t('team.member') }}</SelectItem><SelectItem value="manager">{{ t('team.manager') }}</SelectItem></SelectContent>
                    </Select>
                    <Badge v-else variant="outline">{{ person.role === 'manager' ? t('team.manager') : t('team.member') }}</Badge>
                    <Button v-if="person.can_manage" variant="ghost" size="icon" :aria-label="t('team.remove')" @click="removing = person"><Trash2 class="size-4" /></Button>
                </div>
                <div v-if="!members.length" class="py-6 text-center text-sm text-muted-foreground">{{ t('team.none') }}</div>
            </CardContent>
        </Card>
        <AddProjectMemberDialog
            v-model:open="isAddMemberDialogOpen"
            :project-id="project.id"
            :can-manage-managers="can_manage_managers"
        />
        <Dialog :open="removing !== null" @update:open="value => { if (!value && !actionBusy) removing = null; }">
            <DialogContent class="sm:max-w-md"><DialogHeader><DialogTitle>{{ t('team.remove') }}</DialogTitle><DialogDescription>{{ t('team.removeConfirm') }}</DialogDescription></DialogHeader>
                <DialogFooter><Button variant="outline" :disabled="actionBusy" @click="removing = null">{{ t('stage2.cancel') }}</Button><Button variant="destructive" :disabled="actionBusy" @click="remove">{{ t('team.remove') }}</Button></DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
