<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const statusTone = (value: string) => {
    if (['approved', 'completed'].includes(value)) {
        return 'success';
    }

    if (['for_approval', 'sent'].includes(value)) {
        return 'info';
    }

    return value === 'draft' ? 'neutral' : 'warning';
};

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        },
        { preserveState: true, replace: true },
    );
};

const formatMoney = (value: string) => {
    const numberValue = parseFloat(value);
    return numberValue.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};
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

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 xl:grid-cols-[1fr_220px_180px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input v-model="search" placeholder="Search PO or vendor" />
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">PO</th>
                                <th class="px-4 py-3 font-medium">Vendor</th>
                                <th class="px-4 py-3 font-medium">Company</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="purchaseOrder in purchaseOrders.data"
                                :key="purchaseOrder.id"
                            >
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(purchaseOrder.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ purchaseOrder.po_no }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        Expected
                                        {{
                                            purchaseOrder.expected_delivery ??
                                            'not set'
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            purchaseOrder.purchase_requests
                                                .length
                                        "
                                        class="text-muted-foreground"
                                    >
                                        PR
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
                                <td class="px-4 py-4">
                                    {{ purchaseOrder.vendor.vendor_name }}
                                    <p class="text-muted-foreground">
                                        {{ purchaseOrder.vendor.vendor_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ purchaseOrder.company.company_name }}
                                    <p class="text-muted-foreground">
                                        {{ purchaseOrder.company.company_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ purchaseOrder.po_date }}
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="statusTone(purchaseOrder.status)"
                                    >
                                        {{ statusLabel(purchaseOrder.status) }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right font-medium">
                                    ₱{{
                                        formatMoney(purchaseOrder.total_amount)
                                    }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="show(purchaseOrder.id)"
                                            >
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>

                                        <Button
                                            v-if="
                                                can.edit &&
                                                purchaseOrder.status !==
                                                    'completed' &&
                                                purchaseOrder.status !==
                                                    'for_approval' &&
                                                purchaseOrder.status !==
                                                    'approved'
                                            "
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="edit(purchaseOrder.id)"
                                                >Edit</Link
                                            >
                                        </Button>

                                        <!-- //add a print button for purchase orders that are approved or completed -->
                                        <Button
                                            v-if="
                                                purchaseOrder.status ===
                                                    'approved' ||
                                                purchaseOrder.status ===
                                                    'completed'
                                            "
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="`/purchase-orders/${purchaseOrder.id}/print`"
                                                target="_blank"
                                                >Print</Link
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="purchaseOrders.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No purchase orders found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ purchaseOrders.from ?? 0 }} to
                        {{ purchaseOrders.to ?? 0 }} of
                        {{ purchaseOrders.total }} purchase orders
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in purchaseOrders.links"
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
