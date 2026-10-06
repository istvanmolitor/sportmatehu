<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as syncTargetsIndex, show, sync } from '@/routes/sync-targets';
import type {
    GithubRepositoryRow,
    Paginator,
    RepositoryFilters,
    SyncStatus,
    SyncTargetSummary,
} from '@/types';

const props = defineProps<{
    syncTarget: SyncTargetSummary;
    repositories: Paginator<GithubRepositoryRow>;
    languages: string[];
    filters: RepositoryFilters;
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Sync targets', href: syncTargetsIndex() },
        { title: props.syncTarget.name, href: show(props.syncTarget.id) },
    ],
});

const statusLabels: Record<SyncStatus, string> = {
    pending: 'Függőben',
    syncing: 'Szinkronizálás...',
    success: 'Sikeres',
    failed: 'Sikertelen',
};

const statusVariants: Record<
    SyncStatus,
    'secondary' | 'outline' | 'destructive'
> = {
    pending: 'secondary',
    syncing: 'outline',
    success: 'secondary',
    failed: 'destructive',
};

const search = ref(props.filters.search ?? '');
const language = ref(props.filters.language ?? '');
const sort = ref(props.filters.sort ?? 'github_updated_at');
const direction = ref(props.filters.direction ?? 'desc');

function applyFilters() {
    router.get(
        show(props.syncTarget.id).url,
        {
            search: search.value,
            language: language.value,
            sort: sort.value,
            direction: direction.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head :title="`${syncTarget.name} repói`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                :title="syncTarget.name"
                :description="
                    syncTarget.type === 'organization'
                        ? 'Organizáció'
                        : 'Felhasználó'
                "
            />

            <div class="flex items-center gap-3">
                <Badge :variant="statusVariants[syncTarget.status]">
                    {{ statusLabels[syncTarget.status] }}
                </Badge>

                <Button
                    v-if="syncTarget.status === 'syncing'"
                    size="sm"
                    variant="outline"
                    disabled
                >
                    Szinkronizálás indítása
                </Button>
                <Button v-else size="sm" variant="outline" as-child>
                    <Link :href="sync(syncTarget.id).url" method="post">
                        Szinkronizálás indítása
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="syncTarget.last_synced_at"
            class="text-sm text-muted-foreground"
        >
            Utolsó sikeres szinkron: {{ syncTarget.last_synced_at }}
        </p>
        <p v-if="syncTarget.last_sync_error" class="text-sm text-destructive">
            Utolsó hiba: {{ syncTarget.last_sync_error }}
        </p>

        <form
            class="flex flex-wrap items-end gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-2">
                <Label for="search">Keresés</Label>
                <Input
                    id="search"
                    v-model="search"
                    placeholder="Név vagy leírás"
                    class="w-56"
                />
            </div>

            <div class="grid gap-2">
                <Label for="language">Nyelv</Label>
                <select
                    id="language"
                    v-model="language"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">Összes nyelv</option>
                    <option v-for="item in languages" :key="item" :value="item">
                        {{ item }}
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="sort">Rendezés</Label>
                <select
                    id="sort"
                    v-model="sort"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="github_updated_at">
                        Utolsó GitHub módosítás
                    </option>
                    <option value="stargazers_count">Csillagok száma</option>
                    <option value="open_issues_count">
                        Nyitott issue-k száma
                    </option>
                </select>
            </div>

            <div class="grid gap-2">
                <Label for="direction">Irány</Label>
                <select
                    id="direction"
                    v-model="direction"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="desc">Csökkenő</option>
                    <option value="asc">Növekvő</option>
                </select>
            </div>

            <Button type="submit" size="sm">Szűrés</Button>
        </form>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 text-left dark:border-sidebar-border"
                    >
                        <th class="p-3 font-medium">Név</th>
                        <th class="p-3 font-medium">Leírás</th>
                        <th class="p-3 font-medium">Nyelv</th>
                        <th class="p-3 font-medium">Csillagok</th>
                        <th class="p-3 font-medium">Nyitott issue-k</th>
                        <th class="p-3 font-medium">GitHub módosítva</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="repositories.data.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="6">
                            Nincs a szűrésnek megfelelő repository.
                        </td>
                    </tr>
                    <tr
                        v-for="repository in repositories.data"
                        :key="repository.id"
                        class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                    >
                        <td class="p-3">
                            <a
                                :href="repository.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium hover:underline"
                            >
                                {{ repository.name }}
                            </a>
                            <Badge
                                v-if="repository.is_archived"
                                variant="outline"
                                class="ml-2"
                                >Archivált</Badge
                            >
                        </td>
                        <td
                            class="max-w-sm truncate p-3 text-muted-foreground"
                            :title="repository.description ?? ''"
                        >
                            {{ repository.description ?? '-' }}
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ repository.language ?? '-' }}
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ repository.stargazers_count }}
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ repository.open_issues_count }}
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ repository.github_updated_at ?? '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav v-if="repositories.last_page > 1" class="flex flex-wrap gap-1">
            <template v-for="(link, index) in repositories.links" :key="index">
                <span
                    v-if="!link.url"
                    class="rounded-md px-3 py-1 text-sm text-muted-foreground"
                    v-html="link.label"
                />
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    preserve-state
                    class="rounded-md px-3 py-1 text-sm"
                    :class="
                        link.active
                            ? 'bg-primary text-primary-foreground'
                            : 'hover:bg-accent'
                    "
                    v-html="link.label"
                />
            </template>
        </nav>
    </div>
</template>
