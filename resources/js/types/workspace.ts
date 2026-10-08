export type WorkspaceRole = 'owner' | 'admin' | 'member';

export type WorkspaceSummary = {
    id: number;
    name: string;
    slug: string;
    role: WorkspaceRole;
    is_current: boolean;
};
