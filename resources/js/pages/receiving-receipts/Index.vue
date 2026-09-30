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
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    create,
    index,
    print as printRoute,
    show,
} from '@/routes/receiving-receipts';

type Receipt = {
    id: number;
    rr_no: string;
    invoice_no: string | null;
    received_date: string;
    status: string;
    purchase_order: { id: number; po_no: string };
    company: { company_code: string; company_name: string };
    vendor: { vendor_code: string; vendor_name: string };
};

const props = defineProps<{
    filters: { search: string; status: string };
    receivingReceipts: {
        data: Receipt[];
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
        breadcrumbs: [{ title: 'Receiving Receipts', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusDot = (value: string) => {
    if (['completed', 'confirmed', 'posted', 'received'].includes(value)) {
        return 'bg-emerald-500';
    }

    if (['partial', 'for_approval'].includes(value)) {
        return 'bg-blue-500';
    }

    if (['cancelled', 'voided', 'rejected'].includes(value)) {
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

const showPagination = computed(
    () =>
        props.receivingReceipts.prev_page_url !== null ||
        props.receivingReceipts.next_page_url !== null,
);
</script>

<template>
    <Head title="Receiving Receipts" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Receiving Receipts"
            description="Record goods or services received from vendors."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New receiving receipt
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
                        placeholder="Search RR, PO, vendor, or invoice"
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
                <table class="w-full min-w-[800px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">RR</th>
                            <th class="px-5 py-3 font-normal">PO</th>
                            <th class="px-5 py-3 font-normal">Vendor</th>
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="w-28 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="receipt in receivingReceipts.data"
                            :key="receipt.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(receipt.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ receipt.rr_no }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ receipt.received_date }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p>{{ receipt.purchase_order.po_no }}</p>
                                <p
                                    v-if="receipt.invoice_no"
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    Inv. {{ receipt.invoice_no }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ receipt.vendor.vendor_name }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ receipt.vendor.vendor_code }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :title="receipt.company.company_name"
                                    class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ receipt.company.company_code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="statusDot(receipt.status)"
                                    />
                                    {{ statusLabel(receipt.status) }}
                                </span>
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
                                            :href="show(receipt.id)"
                                            aria-label="View receiving receipt"
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
                                            :href="printRoute(receipt.id)"
                                            aria-label="Print receiving receipt"
                                            title="Print"
                                        >
                                            <Printer class="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="receivingReceipts.data.length === 0">
                            <td
                                colspan="6"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No receiving receipts match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ receivingReceipts.total }}
                    {{ receivingReceipts.total === 1 ? 'receipt' : 'receipts' }}
                </p>

                <div v-if="showPagination" class="flex items-center gap-1">
                    <Button
                        v-if="receivingReceipts.prev_page_url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="receivingReceipts.prev_page_url"
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

                    <Button
                        v-if="receivingReceipts.next_page_url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link
                            :href="receivingReceipts.next_page_url"
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