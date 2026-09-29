<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Printer, Search } from '@lucide/vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
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

let search = props.filters.search ?? '';
let status = props.filters.status ?? 'all';

const submitFilters = () => {
    router.get(
        index.url(),
        { search, status },
        { preserveState: true, replace: true },
    );
};
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
                    <Link :href="create()">New receiving receipt</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="rounded-lg border bg-card p-4 shadow-sm">
            <form
                class="mb-4 flex flex-col gap-3 md:flex-row"
                @submit.prevent="submitFilters"
            >
                <Input
                    v-model="search"
                    placeholder="Search RR, PO, vendor, invoice"
                />
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm md:w-56"
                >
                    <option value="all">All statuses</option>
                    <option v-for="item in statuses" :key="item" :value="item">
                        {{ item.replaceAll('_', ' ') }}
                    </option>
                </select>
                <Button type="submit" variant="outline">
                    <Search />
                    Search
                </Button>
            </form>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-3 font-medium">RR</th>
                            <th class="px-3 py-3 font-medium">PO</th>
                            <th class="px-3 py-3 font-medium">Vendor</th>
                            <th class="px-3 py-3 font-medium">Company</th>
                            <th class="px-3 py-3 font-medium">Status</th>
                            <th class="px-3 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="receipt in receivingReceipts.data"
                            :key="receipt.id"
                        >
                            <td class="px-3 py-3">
                                <Link
                                    :href="show(receipt.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ receipt.rr_no }}
                                </Link>
                                <p class="text-muted-foreground">
                                    {{ receipt.received_date }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                {{ receipt.purchase_order.po_no }}
                            </td>
                            <td class="px-3 py-3">
                                {{ receipt.vendor.vendor_name }}
                                <p class="text-muted-foreground">
                                    {{ receipt.vendor.vendor_code }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                {{ receipt.company.company_name }}
                                <p class="text-muted-foreground">
                                    {{ receipt.company.company_code }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                <StatusBadge>{{ receipt.status }}</StatusBadge>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="show(receipt.id)"
                                            >View</Link
                                        >
                                    </Button>
                                    <Button
                                        v-if="can.print"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="printRoute(receipt.id)">
                                            <Printer />
                                            Print
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="receivingReceipts.data.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                No receiving receipts found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 flex items-center justify-between text-sm text-muted-foreground"
            >
                <span
                    >Showing {{ receivingReceipts.from ?? 0 }} to
                    {{ receivingReceipts.to ?? 0 }} of
                    {{ receivingReceipts.total }} receipts</span
                >
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!receivingReceipts.prev_page_url"
                        @click="
                            receivingReceipts.prev_page_url &&
                            router.visit(receivingReceipts.prev_page_url)
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!receivingReceipts.next_page_url"
                        @click="
                            receivingReceipts.next_page_url &&
                            router.visit(receivingReceipts.next_page_url)
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
