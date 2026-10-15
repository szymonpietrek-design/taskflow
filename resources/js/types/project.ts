export type ProjectType = 'standard' | 'application';
export type ProjectVisibility = 'workspace' | 'private';

export type ProjectSummary = {
    id: number;
    name: string;
    slug: string;
    key: string;
    type: ProjectType;
    visibility: ProjectVisibility;
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
