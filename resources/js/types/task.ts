export type TaskTypeValue = 1 | 2 | 3 | 4;
export type TaskPriorityValue = 1 | 2 | 3 | 4;

export type TaskStatusSummary = {
    id: number;
    name: string;
    category: 'todo' | 'in_progress' | 'done' | 'canceled';
};

export type TaskUserSummary = {
    id: number;
    name: string;
};

export type TaskProjectSummary = {
    id: number;
    name: string;
    key: string;
};

export type TaskParentSummary = {
    id: number;
    reference: string;
    title: string;
};

export type TaskSummary = {
    id: number;
    reference: string;
    title: string;
    type: TaskTypeValue;
    type_label: string;
    priority: TaskPriorityValue;
    priority_label: string;
    status: TaskStatusSummary;
    assignee: TaskUserSummary | null;
    parent: TaskParentSummary | null;
    due_date: string | null;
    completed_at: string | null;
};

export type TaskDetail = TaskSummary & {
    description: string | null;
    creator: TaskUserSummary;
    subtasks: Array<{ id: number; reference: string; title: string; status: string }>;
};

export type TaskMessageSummary = {
    id: number;
    body: string;
    author: TaskUserSummary | null;
    created_at: string;
    updated_at: string;
    can_update: boolean;
    can_delete: boolean;
};
