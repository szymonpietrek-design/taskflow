import type { TaskPriorityValue, TaskStatusSummary } from '@/types/task';

type StatusCategory = TaskStatusSummary['category'];

// Statuses are configurable by project. Style by category instead of name/ID,
// so translated or custom statuses remain visually consistent.
const statusBadgeClasses: Record<StatusCategory, string> = {
    todo: 'border-slate-200 bg-slate-100/80 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
    in_progress: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/50 dark:text-blue-300',
    done: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300',
    canceled: 'border-zinc-200 bg-zinc-100/70 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400',
};

const priorityDotClasses: Record<TaskPriorityValue, string> = {
    1: 'bg-slate-400 dark:bg-slate-500',
    2: 'bg-slate-500 dark:bg-slate-400',
    3: 'bg-orange-500 dark:bg-orange-400',
    4: 'bg-red-600 dark:bg-red-400',
};

export function taskStatusBadgeClass(category: StatusCategory): string {
    return statusBadgeClasses[category] ?? statusBadgeClasses.todo;
}

export function taskPriorityDotClass(priority: TaskPriorityValue): string {
    return priorityDotClasses[priority] ?? priorityDotClasses[2];
}
