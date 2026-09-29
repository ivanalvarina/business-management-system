<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/audit-logs';

type AuditLog = {
    id: number;
    module: string;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
    user: { id: number; name: string; email: string } | null;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        module: string;
        action: string;
        date_from: string | null;
        date_to: string | null;
        user_id: number | null;
    };
    users: UserOption[];
    modules: string[];
    actions: string[];
    auditLogs: {
        data: AuditLog[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Audit Trail', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const module = ref(props.filters.module ?? 'all');
const action = ref(props.filters.action ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const userId = ref<number | 'all'>(props.filters.user_id ?? 'all');

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatValues = (values: Record<string, unknown> | null) =>
    values === null || Object.keys(values).length === 0
        ? 'None'
        : JSON.stringify(values, null, 2);

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            module: module.value === 'all' ? undefined : module.value,
            action: action.value === 'all' ? undefined : action.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            user_id: userId.value === 'all' ? undefined : userId.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Audit Trail" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Audit Trail"
            description="Review important system actions, status changes, document activity, and administration changes."
        />

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-3 xl:grid-cols-[1fr_180px_180px_180px_150px_150px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input v-model="search" placeholder="Search reference" />
                    <select
                        v-model="userId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All users</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.name }}
                        </option>
                    </select>
                    <select
                        v-model="module"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All modules</option>
                        <option
                            v-for="moduleOption in modules"
                            :key="moduleOption"
                            :value="moduleOption"
                        >
                            {{ label(moduleOption) }}
                        </option>
                    </select>
                    <select
                        v-model="action"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All actions</option>
                        <option
                            v-for="actionOption in actions"
                            :key="actionOption"
                            :value="actionOption"
                        >
                            {{ label(actionOption) }}
                        </option>
                    </select>
                    <Input v-model="dateFrom" type="date" />
                    <Input v-model="dateTo" type="date" />
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">When</th>
                                <th class="px-4 py-3 font-medium">User</th>
                                <th class="px-4 py-3 font-medium">Module</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                                <th class="px-4 py-3 font-medium">Subject</th>
                                <th class="px-4 py-3 font-medium">Changes</th>
                                <th class="px-4 py-3 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="log in auditLogs.data" :key="log.id">
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ log.created_at ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{ log.user?.name ?? 'System' }}
                                    </p>
                                    <p
                                        v-if="log.user"
                                        class="text-muted-foreground"
                                    >
                                        {{ log.user.email }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ label(log.module) }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ label(log.action) }}
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ log.subject_type ?? 'None' }}
                                    <span v-if="log.subject_id">
                                        #{{ log.subject_id }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <details>
                                        <summary
                                            class="cursor-pointer text-muted-foreground"
                                        >
                                            View values
                                        </summary>
                                        <div
                                            class="mt-2 grid gap-2 lg:grid-cols-2"
                                        >
                                            <pre
                                                class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs"
                                                >{{
                                                    formatValues(log.old_values)
                                                }}</pre>
                                            <pre
                                                class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs"
                                                >{{
                                                    formatValues(log.new_values)
                                                }}</pre>
                                        </div>
                                    </details>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ log.ip_address ?? 'Unknown' }}
                                </td>
                            </tr>
                            <tr v-if="auditLogs.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No audit records found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ auditLogs.from ?? 0 }} to
                        {{ auditLogs.to ?? 0 }} of {{ auditLogs.total }} records
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in auditLogs.links"
                            :key="link.label"
                        >
                            <Button
                                v-if="link.url"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                as-child
                            >
                                <Link :href="link.url" v-html="link.label" />
                            </Button>
                            <Button v-else variant="outline" size="sm" disabled>
                                <span v-html="link.label" />
                            </Button>
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
