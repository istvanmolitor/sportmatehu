<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import SyncTargetController from '@/actions/App/Http/Controllers/SyncTargetController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as syncTargetsIndex, show, sync } from '@/routes/sync-targets';
import type { SyncStatus, SyncTargetSummary } from '@/types';

defineProps<{
    syncTargets: SyncTargetSummary[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Sync targets', href: syncTargetsIndex() }],
    },
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
</script>

<template>
    <Head title="Sync targets" />

    <div class="flex flex-col gap-8 p-4">
        <Heading
            title="Sync targets"
            description="GitHub felhasználók és organizációk, amelyeket szinkronizálsz."
        />

        <div
            class="max-w-md rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
        >
            <h2 class="mb-4 text-base font-medium">Új target hozzáadása</h2>

            <Form
                v-bind="SyncTargetController.store.form()"
                reset-on-success
                class="space-y-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name"
                        >GitHub felhasználó vagy organizáció neve</Label
                    >
                    <Input
                        id="name"
                        name="name"
                        placeholder="pl. laravel"
                        autocomplete="off"
                    />
                    <InputError :message="errors.name" />
                </div>

                <fieldset class="grid gap-2">
                    <legend class="text-sm font-medium">Típus</legend>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="type"
                                value="user"
                                checked
                            />
                            Felhasználó
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="type"
                                value="organization"
                            />
                            Organizáció
                        </label>
                    </div>
                    <InputError :message="errors.type" />
                </fieldset>

                <Button type="submit" :disabled="processing">Hozzáadás</Button>
            </Form>
        </div>

        <div
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-sidebar-border/70 text-left dark:border-sidebar-border"
                    >
                        <th class="p-3 font-medium">Név</th>
                        <th class="p-3 font-medium">Típus</th>
                        <th class="p-3 font-medium">Státusz</th>
                        <th class="p-3 font-medium">Utolsó sikeres szinkron</th>
                        <th class="p-3 font-medium">Hiba</th>
                        <th class="p-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="syncTargets.length === 0">
                        <td class="p-3 text-muted-foreground" colspan="6">
                            Még nincs hozzáadott target.
                        </td>
                    </tr>
                    <tr
                        v-for="target in syncTargets"
                        :key="target.id"
                        class="border-b border-sidebar-border/70 last:border-b-0 dark:border-sidebar-border"
                    >
                        <td class="p-3">
                            <Link
                                :href="show(target.id).url"
                                class="font-medium hover:underline"
                            >
                                {{ target.name }}
                            </Link>
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{
                                target.type === 'organization'
                                    ? 'Organizáció'
                                    : 'Felhasználó'
                            }}
                        </td>
                        <td class="p-3">
                            <Badge :variant="statusVariants[target.status]">
                                {{ statusLabels[target.status] }}
                            </Badge>
                        </td>
                        <td class="p-3 text-muted-foreground">
                            {{ target.last_synced_at ?? '-' }}
                        </td>
                        <td
                            class="max-w-xs truncate p-3 text-muted-foreground"
                            :title="target.last_sync_error ?? ''"
                        >
                            {{ target.last_sync_error ?? '-' }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                v-if="target.status === 'syncing'"
                                size="sm"
                                variant="outline"
                                disabled
                            >
                                Szinkronizálás indítása
                            </Button>
                            <Button v-else size="sm" variant="outline" as-child>
                                <Link :href="sync(target.id).url" method="post">
                                    Szinkronizálás indítása
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
