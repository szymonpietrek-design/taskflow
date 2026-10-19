export type ProjectType = 'standard' | 'application';
export type ProjectVisibility = 'workspace' | 'private';
export type ProjectIcon = 'folder-kanban' | 'code-2' | 'shopping-bag' | 'building-2' | 'package' | 'globe' | 'server' | 'database' | 'rocket' | 'book-open' | 'smartphone' | 'wrench'
    | 'monitor' | 'terminal' | 'cloud' | 'git-branch' | 'workflow' | 'cpu' | 'shield-check' | 'chart-no-axes-combined' | 'calendar-days' | 'message-square' | 'users' | 'briefcase-business' | 'graduation-cap' | 'palette' | 'camera' | 'mail' | 'bot' | 'heart';
export type ProjectColor = 'slate' | 'blue' | 'emerald' | 'violet' | 'amber' | 'rose' | 'cyan' | 'orange'
    | 'indigo' | 'teal' | 'lime' | 'fuchsia' | 'pink' | 'red' | 'sky' | 'stone';

export type ProjectSummary = {
    id: number;
    name: string;
    slug: string;
    key: string;
    type: ProjectType;
    visibility: ProjectVisibility;
    icon: ProjectIcon | null;
    color: ProjectColor | null;
    logo_url?: string | null;
};

export type ProjectContext = {
    items: ProjectSummary[];
    can_create: boolean;
};

export type ProjectDetail = ProjectSummary & {
    description: string | null;
    can_manage: boolean;
    is_archived: boolean;
};

export type WorkspaceProjectSummary = ProjectSummary & {
    description: string | null;
    can_edit: boolean;
    is_archived: boolean;
    can_archive: boolean;
};
