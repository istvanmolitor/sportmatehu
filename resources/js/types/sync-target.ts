export type SyncTargetType = 'user' | 'organization';
export type SyncStatus = 'pending' | 'syncing' | 'success' | 'failed';

export type SyncTargetSummary = {
    id: number;
    name: string;
    type: SyncTargetType;
    status: SyncStatus;
    last_synced_at: string | null;
    last_sync_error: string | null;
};

export type GithubRepositoryRow = {
    id: number;
    github_id: number;
    name: string;
    full_name: string;
    description: string | null;
    url: string;
    language: string | null;
    stargazers_count: number;
    open_issues_count: number;
    is_archived: boolean;
    github_updated_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    total: number;
    current_page: number;
    last_page: number;
};

export type RepositoryFilters = {
    search?: string;
    language?: string;
    sort?: string;
    direction?: string;
};
