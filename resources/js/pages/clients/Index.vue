<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/clients';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type Client = {
    id: number;
    client_code: string;
    client_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    status: 'active' | 'inactive';
    contacts_count: number;
    companies: CompanyOption[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string; status: string; company_id: number | null };
    companies: CompanyOption[];
    clients: {
        data: Client[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        create: boolean;
        edit: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | ''>(props.filters.company_id ?? '');

const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const setStatus = (value: string) => {
    status.value = value;
    applyFilters();
};

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

const prevLink = computed(() => props.clients.links[0]);
const nextLink = computed(
    () => props.clients.links[props.clients.links.length - 1],
);
const currentPage = computed(
    () => props.clients.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.clients.links.length > 3);
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Clients"
            description="Manage customer records and company assignments."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New client
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <div class="relative w-full sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search code, name, or TIN"
                            class="pl-9"
                        />
                    </div>
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground sm:w-52"
                        aria-label="Filter by company"
                        @change="applyFilters"
                    >
                        <option value="">All companies</option>
                        <option
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.id"
                        >
                            {{ company.company_name }}
                        </option>
                    </select>
                </div>

                <div class="flex gap-1" role="group" aria-label="Filter by status">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm transition-colors"
                        :class="
                            status === option.value
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="status === option.value"
                        @click="setStatus(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">Client</th>
                            <th class="px-5 py-3 font-normal">Companies</th>
                            <th class="px-5 py-3 font-normal">Contact</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="w-16 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="client in clients.data"
                            :key="client.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ initials(client.client_name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="show(client.id)"
                                            class="block truncate font-medium hover:underline"
                                        >
                                            {{ client.client_name }}
                                        </Link>
                                        <p
                                            class="truncate font-mono text-xs text-muted-foreground"
                                        >
                                            {{ client.client_code }}
                                            <span v-if="client.tin">
                                                · {{ client.tin }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="company in client.companies"
                                        :key="company.id"
                                        :title="company.company_name"
                                        class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ company.company_code }}
                                    </span>
                                    <span
                                        v-if="client.companies.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        None assigned
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ client.email ?? 'No email' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ client.phone ?? 'No phone' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ client.contacts_count }}
                                    {{
                                        client.contacts_count === 1
                                            ? 'contact'
                                            : 'contacts'
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            client.status === 'active'
                                                ? 'bg-emerald-500'
                                                : 'bg-amber-500'
                                        "
                                    />
                                    {{
                                        client.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Button
                                    v-if="can.edit"
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:text-foreground"
                                    as-child
                                >
                                    <Link
                                        :href="edit(client.id)"
                                        aria-label="Edit client"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="clients.data.length === 0">
                            <td
                                colspan="5"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No clients match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ clients.total }}
                    {{ clients.total === 1 ? 'client' : 'clients' }}
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