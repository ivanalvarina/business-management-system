<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    Plus,
    Printer,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/purchase-orders';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type PurchaseOrder = {
    id: number;
    po_no: string;
    po_date: string;
    expected_delivery: string | null;
    currency: string;
    status: string;
    total_amount: string;
    company: { company_code: string; company_name: string };
    vendor: { vendor_code: string; vendor_name: string };
    purchase_requests: { id: number; pr_no: string }[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; status: string; company_id: number | null };
    companies: CompanyOption[];
    statuses: string[];
    purchaseOrders: {
        data: PurchaseOrder[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Purchase Orders', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | 'all'>(props.filters.company_id ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusDot = (value: string) => {
    if (['approved', 'completed'].includes(value)) {
        return 'bg-emerald-500';
    }

    if (['for_approval', 'sent'].includes(value)) {
        return 'bg-blue-500';
    }

    return value === 'draft' ? 'bg-muted-foreground/40' : 'bg-amber-500';
};

const canEditOrder = (purchaseOrder: PurchaseOrder) =>
    props.can.edit &&
    !['completed', 'for_approval', 'approved'].includes(purchaseOrder.status);

const canPrintOrder = (purchaseOrder: PurchaseOrder) =>
    ['approved', 'completed'].includes(purchaseOrder.status);

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const formatMoney = (value: string) => {
    const numberValue = parseFloat(value);

    return numberValue.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const prevLink = computed(() => props.purchaseOrders.links[0]);
const nextLink = computed(
    () => props.purchaseOrders.links[props.purchaseOrders.links.length - 1],
);
const currentPage = computed(
    () => props.purchaseOrders.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.purchaseOrders.links.length > 3);
</script>

<template>
    <Head title="Purchase Orders" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Purchase Orders"
            description="Create and manage purchase orders issued to vendors."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New purchase order
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div class="flex flex-col gap-2 md:flex-row">
                <div class="relative w-full md:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search PO or vendor"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="companyId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-52"
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
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
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
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">PO</th>
                            <th class="px-5 py-3 font-normal">Vendor</th>
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Date</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="px-5 py-3 text-right font-normal">
                                Total
                            </th>
                            <th class="w-28 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="purchaseOrder in purchaseOrders.data"
                            :key="purchaseOrder.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(purchaseOrder.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ purchaseOrder.po_no }}
                                </Link>
                                <p
                                    v-if="purchaseOrder.purchase_requests.length"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{
                                        purchaseOrder.purchase_requests
                                            .map(
                                                (purchaseRequest) =>
                                                    purchaseRequest.pr_no,
                                            )
                                            .join(', ')
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ purchaseOrder.vendor.vendor_name }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ purchaseOrder.vendor.vendor_code }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :title="purchaseOrder.company.company_name"
                                    class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ purchaseOrder.company.company_code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p>{{ purchaseOrder.po_date }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        purchaseOrder.expected_delivery
                                            ? `Expected ${purchaseOrder.expected_delivery}`
                                            : 'No delivery date'
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="statusDot(purchaseOrder.status)"
                                    />
                                    {{ statusLabel(purchaseOrder.status) }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium tabular-nums"
                            >
                                ₱{{ formatMoney(purchaseOrder.total_amount) }}
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
                                            :href="show(purchaseOrder.id)"
                                            aria-label="View purchase order"
                                            title="View"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="canEditOrder(purchaseOrder)"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="edit(purchaseOrder.id)"
                                            aria-label="Edit purchase order"
                                            title="Edit"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-else-if="canPrintOrder(purchaseOrder)"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="`/purchase-orders/${purchaseOrder.id}/print`"
                                            target="_blank"
                                            aria-label="Print purchase order"
                                            title="Print"
                                        >
                                            <Printer class="size-4" />
                                        </Link>
                                    </Button>
                                    <span v-else class="size-9" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="purchaseOrders.data.length === 0">
                            <td
                                colspan="7"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No purchase orders match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ purchaseOrders.total }}
                    {{
                        purchaseOrders.total === 1
                            ? 'purchase order'
                            : 'purchase orders'
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