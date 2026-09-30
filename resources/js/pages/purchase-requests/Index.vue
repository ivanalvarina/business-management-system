<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Plus,
    Printer,
    Search,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    index,
    print as printRoute,
    show,
} from '@/routes/purchase-requests';

type PurchaseRequest = {
    id: number;
    pr_no: string;
    request_date: string;
    date_required: string | null;
    currency: string;
    status: string;
    total_amount: string;
    company: { company_code: string; company_name: string };
    vendor: { vendor_code: string; vendor_name: string } | null;
};

const props = defineProps<{
    filters: { search: string; status: string };
    purchaseRequests: {
        data: PurchaseRequest[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    statuses: string[];
    can: { create: boolean; edit: boolean; print: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Purchase Requests', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusDot = (value: string) => {
    if (['approved', 'completed', 'ordered'].includes(value)) {
        return 'bg-emerald-500';
    }

    if (['for_approval', 'submitted'].includes(value)) {
        return 'bg-blue-500';
    }

    if (['rejected', 'cancelled'].includes(value)) {
        return 'bg-amber-500';
    }

    return 'bg-muted-foreground/40';
};

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const money = (currency: string, value: string) =>
    `${currency} ${Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
</script>

<template>
    <Head title="Purchase Requests" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Purchase Requests"
            description="Create and approve requests before issuing purchase orders."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New request
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
                        placeholder="Search PR, vendor, or project"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-48"
                    aria-label="Filter by status"
                    @change="applyFilters"
                >
                    <option value="all">All statuses</option>
                    <option v-for="item in statuses" :key="item" :value="item">
                        {{ statusLabel(item) }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">PR</th>
                            <th class="px-5 py-3 font-normal">Vendor</th>
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Required</th>
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
                            v-for="pr in purchaseRequests.data"
                            :key="pr.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(pr.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ pr.pr_no }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ pr.request_date }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <template v-if="pr.vendor">
                                    <p class="truncate">
                                        {{ pr.vendor.vendor_name }}
                                    </p>
                                    <p
                                        class="font-mono text-xs text-muted-foreground"
                                    >
                                        {{ pr.vendor.vendor_code }}
                                    </p>
                                </template>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    Not set
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :title="pr.company.company_name"
                                    class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ pr.company.company_code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-muted-foreground">
                                {{ pr.date_required ?? 'Not set' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="statusDot(pr.status)"
                                    />
                                    {{ statusLabel(pr.status) }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium tabular-nums"
                            >
                                {{ money(pr.currency, pr.total_amount) }}
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
                                            :href="show(pr.id)"
                                            aria-label="View purchase request"
                                            title="View"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="can.print"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="printRoute(pr.id)"
                                            aria-label="Print purchase request"
                                            title="Print"
                                        >
                                            <Printer class="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="purchaseRequests.data.length === 0">
                            <td
                                colspan="7"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No purchase requests match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ purchaseRequests.total }}
                    {{
                        purchaseRequests.total === 1 ? 'request' : 'requests'
                    }}
                </p>

                <div
                    v-if="purchaseRequests.last_page > 1"
                    class="flex items-center gap-1"
                >
                    <Button
                        v-if="purchaseRequests.prev_page_url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="purchaseRequests.prev_page_url"
                            aria-label="Previous page"
                        >
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
                        {{ purchaseRequests.current_page }}
                        <span class="font-normal text-muted-foreground">
                            / {{ purchaseRequests.last_page }}
                        </span>
                    </span>

                    <Button
                        v-if="purchaseRequests.next_page_url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="purchaseRequests.next_page_url"
                            aria-label="Next page"
                        >
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