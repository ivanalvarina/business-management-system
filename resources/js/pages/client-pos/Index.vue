<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Money from '@/components/Money.vue';
import { create, edit, index, show } from '@/routes/client-pos';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type ClientOption = {
    id: number;
    client_code: string;
    client_name: string;
    company_ids: number[];
};

type ClientPurchaseOrder = {
    id: number;
    client_po_no: string;
    po_date: string;
    currency: string;
    amount: string;
    status: string;
    received_at: string | null;
    company: { company_code: string; company_name: string };
    client: { client_code: string; client_name: string };
    quotations: { id: number; quotation_no: string }[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        status: string;
        company_id: number | null;
        client_id: number | null;
    };
    companies: CompanyOption[];
    clients: ClientOption[];
    statuses: string[];
    clientPurchaseOrders: {
        data: ClientPurchaseOrder[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Client Purchase Orders', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | 'all'>(props.filters.company_id ?? 'all');
const clientId = ref<number | 'all'>(props.filters.client_id ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusDot = (value: string) => {
    if (value === 'confirmed') {
        return 'bg-emerald-500';
    }

    if (value === 'in_review') {
        return 'bg-blue-500';
    }

    return value === 'cancelled' ? 'bg-amber-500' : 'bg-muted-foreground/40';
};

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
            client_id: clientId.value === 'all' ? undefined : clientId.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const prevLink = computed(() => props.clientPurchaseOrders.links[0]);
const nextLink = computed(
    () =>
        props.clientPurchaseOrders.links[
            props.clientPurchaseOrders.links.length - 1
        ],
);
const currentPage = computed(
    () =>
        props.clientPurchaseOrders.links.find((link) => link.active)?.label ??
        '1',
);
const showPagination = computed(
    () => props.clientPurchaseOrders.links.length > 3,
);
</script>

<template>
    <Head title="Client Purchase Orders" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Client Purchase Orders"
            description="Track purchase orders received from clients."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New client PO
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div class="flex flex-col gap-2 lg:flex-row">
                <div class="relative w-full lg:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search PO, client, or quotation"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="companyId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground lg:w-44"
                    aria-label="Filter by company"
                    @change="applyFilters"
                >
                    <option value="all">All companies</option>
                    <option
                        v-for="company in companies"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.company_name }}
                    </option>
                </select>
                <select
                    v-model="clientId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground lg:w-44"
                    aria-label="Filter by client"
                    @change="applyFilters"
                >
                    <option value="all">All clients</option>
                    <option
                        v-for="client in clients"
                        :key="client.id"
                        :value="client.id"
                    >
                        {{ client.client_name }}
                    </option>
                </select>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground lg:w-40"
                    aria-label="Filter by status"
                    @change="applyFilters"
                >
                    <option value="all">All statuses</option>
                    <option
                        v-for="statusOption in statuses"
                        :key="statusOption"
                        :value="statusOption"
                    >
                        {{ statusLabel(statusOption) }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[920px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">Client PO</th>
                            <th class="px-5 py-3 font-normal">Client</th>
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Linked quote</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="px-5 py-3 text-right font-normal">
                                Amount
                            </th>
                            <th class="w-28 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="clientPo in clientPurchaseOrders.data"
                            :key="clientPo.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(clientPo.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ clientPo.client_po_no }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ clientPo.po_date }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ clientPo.client.client_name }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ clientPo.client.client_code }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :title="clientPo.company.company_name"
                                    class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ clientPo.company.company_code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p
                                    v-if="clientPo.quotations.length"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        clientPo.quotations
                                            .map(
                                                (quotation) =>
                                                    quotation.quotation_no,
                                            )
                                            .join(', ')
                                    }}
                                </p>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    None linked
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="statusDot(clientPo.status)"
                                    />
                                    {{ statusLabel(clientPo.status) }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium tabular-nums"
                            >
                                <Money
                                    :amount="clientPo.amount"
                                    :currency="clientPo.currency"
                                />
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="show(clientPo.id)"
                                            aria-label="View client PO"
                                            title="View"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="
                                            can.edit &&
                                            clientPo.status !== 'fulfilled'
                                        "
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="edit(clientPo.id)"
                                            aria-label="Edit client PO"
                                            title="Edit"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <span v-else class="size-9" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="clientPurchaseOrders.data.length === 0">
                            <td
                                colspan="7"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No client POs match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ clientPurchaseOrders.total }}
                    {{
                        clientPurchaseOrders.total === 1
                            ? 'client PO'
                            : 'client POs'
                    }}
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