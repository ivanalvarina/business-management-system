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

let search = props.filters.search ?? '';
let status = props.filters.status ?? 'all';

const submitFilters = () => {
    router.get(
        index.url(),
        { search, status },
        { preserveState: true, replace: true },
    );
};

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
            description="Create and approve purchase requests before issuing purchase orders."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">New request</Link>
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
                    placeholder="Search PR, vendor, project"
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
                            <th class="px-3 py-3 font-medium">PR</th>
                            <th class="px-3 py-3 font-medium">Vendor</th>
                            <th class="px-3 py-3 font-medium">Company</th>
                            <th class="px-3 py-3 font-medium">Required</th>
                            <th class="px-3 py-3 font-medium">Status</th>
                            <th class="px-3 py-3 text-right font-medium">
                                Total
                            </th>
                            <th class="px-3 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="pr in purchaseRequests.data" :key="pr.id">
                            <td class="px-3 py-3">
                                <Link
                                    :href="show(pr.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ pr.pr_no }}
                                </Link>
                                <p class="text-muted-foreground">
                                    {{ pr.request_date }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                {{ pr.vendor?.vendor_name ?? 'Not set' }}
                                <p class="text-muted-foreground">
                                    {{ pr.vendor?.vendor_code }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                {{ pr.company.company_name }}
                                <p class="text-muted-foreground">
                                    {{ pr.company.company_code }}
                                </p>
                            </td>
                            <td class="px-3 py-3">
                                {{ pr.date_required ?? '-' }}
                            </td>
                            <td class="px-3 py-3">
                                <StatusBadge>{{
                                    pr.status.replaceAll('_', ' ')
                                }}</StatusBadge>
                            </td>
                            <td class="px-3 py-3 text-right font-medium">
                                {{ money(pr.currency, pr.total_amount) }}
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="show(pr.id)">View</Link>
                                    </Button>
                                    <Button
                                        v-if="can.print"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="printRoute(pr.id)">
                                            <Printer />
                                            Print
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="purchaseRequests.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-10 text-center text-muted-foreground"
                            >
                                No purchase requests found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="mt-4 flex items-center justify-between text-sm text-muted-foreground"
            >
                <span
                    >Showing {{ purchaseRequests.from ?? 0 }} to
                    {{ purchaseRequests.to ?? 0 }} of
                    {{ purchaseRequests.total }} requests</span
                >
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!purchaseRequests.prev_page_url"
                        @click="
                            purchaseRequests.prev_page_url &&
                            router.visit(purchaseRequests.prev_page_url)
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="!purchaseRequests.next_page_url"
                        @click="
                            purchaseRequests.next_page_url &&
                            router.visit(purchaseRequests.next_page_url)
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
