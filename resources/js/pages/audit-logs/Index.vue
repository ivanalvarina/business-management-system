<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
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
const moduleName = ref(props.filters.module ?? 'all');
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

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            module: moduleName.value === 'all' ? undefined : moduleName.value,
            action: action.value === 'all' ? undefined : action.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            user_id: userId.value === 'all' ? undefined : userId.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch([search, moduleName, action, dateFrom, dateTo, userId], () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const prevLink = computed(() => props.auditLogs.links[0]);
const nextLink = computed(
    () => props.auditLogs.links[props.auditLogs.links.length - 1],
);
const currentPage = computed(
    () => props.auditLogs.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.auditLogs.links.length > 3);
</script>

<template>
    <Head title="Audit Trail" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Audit Trail"
            description="Review system actions, status changes, and administration changes."
        />

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-full md:w-60">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search reference"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="userId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-40"
                    aria-label="Filter by user"
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
                    v-model="moduleName"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-40"
                    aria-label="Filter by module"
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
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-40"
                    aria-label="Filter by action"
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

                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    From
                    <Input v-model="dateFrom" type="date" class="w-40" />
                </label>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    To
                    <Input v-model="dateTo" type="date" class="w-40" />
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">When</th>
                            <th class="px-5 py-3 font-normal">User</th>
                            <th class="px-5 py-3 font-normal">Activity</th>
                            <th class="px-5 py-3 font-normal">Subject</th>
                            <th class="px-5 py-3 font-normal">Changes</th>
                            <th class="px-5 py-3 font-normal">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="log in auditLogs.data"
                            :key="log.id"
                            class="border-t align-top transition-colors hover:bg-muted/40"
                        >
                            <td
                                class="px-5 py-3.5 whitespace-nowrap text-muted-foreground"
                            >
                                {{ log.created_at ?? 'Unknown' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="font-medium">
                                    {{ log.user?.name ?? 'System' }}
                                </p>
                                <p
                                    v-if="log.user"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ log.user.email }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p>{{ label(log.action) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ label(log.module) }}
                                </p>
                            </td>
                            <td
                                class="px-5 py-3.5 font-mono text-xs text-muted-foreground"
                            >
                                <template v-if="log.subject_type">
                                    {{ log.subject_type }}
                                    <span v-if="log.subject_id">
                                        #{{ log.subject_id }}
                                    </span>
                                </template>
                                <template v-else>None</template>
                            </td>
                            <td class="px-5 py-3.5">
                                <details>
                                    <summary
                                        class="cursor-pointer text-xs text-muted-foreground hover:text-foreground"
                                    >
                                        View values
                                    </summary>
                                    <div class="mt-2 grid gap-2 lg:grid-cols-2">
                                        <div>
                                            <p
                                                class="mb-1 text-xs text-muted-foreground"
                                            >
                                                Before
                                            </p>
                                            <pre
                                                class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs"
                                                >{{
                                                    formatValues(log.old_values)
                                                }}</pre>
                                        </div>
                                        <div>
                                            <p
                                                class="mb-1 text-xs text-muted-foreground"
                                            >
                                                After
                                            </p>
                                            <pre
                                                class="max-h-48 overflow-auto rounded-md bg-muted p-3 text-xs"
                                                >{{
                                                    formatValues(log.new_values)
                                                }}</pre>
                                        </div>
                                    </div>
                                </details>
                            </td>
                            <td
                                class="px-5 py-3.5 font-mono text-xs text-muted-foreground"
                            >
                                {{ log.ip_address ?? 'Unknown' }}
                            </td>
                        </tr>
                        <tr v-if="auditLogs.data.length === 0">
                            <td
                                colspan="6"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No audit records match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ auditLogs.total }}
                    {{ auditLogs.total === 1 ? 'record' : 'records' }}
                </p>

                <div v-if="showPagination" class="flex items-center gap-1">
                    <Button
                        v-if="prevLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="prevLink.url" aria-label="Previous page">
                            <ChevronLeft class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Previous page"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>

                    <span class="px-2 text-sm font-medium">
                        {{ currentPage }}
                    </span>

                    <Button
                        v-if="nextLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="nextLink.url" aria-label="Next page">
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Next page"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>