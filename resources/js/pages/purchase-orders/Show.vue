<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/PurchaseOrderController';
import ActivityHistoryPanel from '@/components/app/ActivityHistoryPanel.vue';
import DocumentsPanel from '@/components/app/DocumentsPanel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index, print as printRoute } from '@/routes/purchase-orders';

type PurchaseOrderItem = {
    id: number;
    product_service: { code: string; name: string; type: string } | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
    line_total: string;
};

type DocumentRecord = {
    id: number;
    document_type: string;
    original_filename: string;
    mime_type: string;
    file_size: number;
    expiration_date: string | null;
    created_at: string | null;
    uploader: { id: number; name: string } | null;
};

type PurchaseOrder = {
    id: number;
    po_no: string;
    po_date: string;
    expected_delivery: string | null;
    currency: string;
    status: string;
    notes: string | null;
    terms_conditions: string | null;
    subtotal: string;
    discount: string;
    tax_amount: string;
    total_amount: string;
    company: {
        company_code: string;
        company_name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    vendor: {
        vendor_code: string;
        vendor_name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    purchase_requests: {
        id: number;
        pr_no: string;
    }[];
    items: PurchaseOrderItem[];
    documents: DocumentRecord[];
};

type ActivityLog = {
    id: number;
    module: string;
    action: string;
    created_at: string | null;
    user: { id: number; name: string } | null;
};

const props = defineProps<{
    purchaseOrder: PurchaseOrder;
    activityLogs: ActivityLog[];
    can: {
        edit: boolean;
        delete: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
        send: boolean;
        complete: boolean;
        cancel: boolean;
        print: boolean;
        uploadDocuments: boolean;
        downloadDocuments: boolean;
        deleteDocuments: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase Orders', href: index() },
            { title: 'View', href: '#' },
        ],
    },
});

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

const runAction = (url: string) => {
    router.patch(url, {}, { preserveScroll: true });
};

const destroyPurchaseOrder = () => {
    if (window.confirm('Delete this purchase order?')) {
        router.delete(
            PurchaseOrderController.destroy.url(props.purchaseOrder.id),
        );
    }
};

const formatQuantity = (value: string) => {
    const number = parseFloat(value);
    return isNaN(number)
        ? value
        : number.toLocaleString(undefined, {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          });
};

const formatMoney = (value: string) => {
    const number = parseFloat(value);
    return isNaN(number)
        ? value
        : number.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};
</script>

<template>
    <Head :title="purchaseOrder.po_no" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="purchaseOrder.po_no"
            description="Vendor purchase order details, line snapshots, and controlled status actions."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back</Link>
                </Button>
                <Button v-if="can.print" variant="outline" as-child>
                    <Link :href="printRoute(purchaseOrder.id)">
                        <Printer />
                        Print
                    </Link>
                </Button>
                <Button v-if="can.edit" variant="outline" as-child>
                    <Link :href="edit(purchaseOrder.id)">Edit</Link>
                </Button>
                <Button
                    v-if="can.submit"
                    @click="
                        runAction(
                            PurchaseOrderController.submit.url(
                                purchaseOrder.id,
                            ),
                        )
                    "
                >
                    Submit
                </Button>
                <Button
                    v-if="can.approve"
                    @click="
                        runAction(
                            PurchaseOrderController.approve.url(
                                purchaseOrder.id,
                            ),
                        )
                    "
                >
                    Approve
                </Button>
                <Button
                    v-if="can.reject"
                    variant="outline"
                    @click="
                        runAction(
                            PurchaseOrderController.reject.url(
                                purchaseOrder.id,
                            ),
                        )
                    "
                >
                    Reject
                </Button>
                <Button
                    v-if="can.send"
                    variant="outline"
                    @click="
                        runAction(
                            PurchaseOrderController.send.url(purchaseOrder.id),
                        )
                    "
                >
                    Mark sent
                </Button>
                <Button
                    v-if="can.complete"
                    variant="outline"
                    @click="
                        runAction(
                            PurchaseOrderController.complete.url(
                                purchaseOrder.id,
                            ),
                        )
                    "
                >
                    Complete
                </Button>
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    @click="
                        runAction(
                            PurchaseOrderController.cancel.url(
                                purchaseOrder.id,
                            ),
                        )
                    "
                >
                    Cancel
                </Button>
                <Button
                    v-if="can.delete"
                    variant="outline"
                    @click="destroyPurchaseOrder"
                >
                    Delete
                </Button>
            </template>
        </PageHeader>

        <!-- Details: compact 3-column grid -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Details</CardTitle>
            </CardHeader>
            <CardContent class="px-4">
                <dl
                    class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">Company</dt>
                        <dd class="font-medium">
                            {{ purchaseOrder.company?.company_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ purchaseOrder.company?.company_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Vendor</dt>
                        <dd class="font-medium">
                            {{ purchaseOrder.vendor?.vendor_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ purchaseOrder.vendor?.vendor_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-0.5">
                            <StatusBadge
                                :tone="statusTone(purchaseOrder.status)"
                            >
                                {{ statusLabel(purchaseOrder.status) }}
                            </StatusBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">PO date</dt>
                        <dd class="font-medium">{{ purchaseOrder.po_date }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Expected delivery
                        </dt>
                        <dd class="font-medium">
                            {{ purchaseOrder.expected_delivery ?? 'Not set' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Currency</dt>
                        <dd class="font-medium">{{ purchaseOrder.currency }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs text-muted-foreground">Linked PR</dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="purchaseRequest in purchaseOrder.purchase_requests"
                                :key="purchaseRequest.id"
                                class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400"
                            >
                                {{ purchaseRequest.pr_no }}
                            </span>
                            <span
                                v-if="!purchaseOrder.purchase_requests.length"
                                class="font-medium"
                            >
                                Not linked
                            </span>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Line items + totals right under the table -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Line items</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 px-4">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">Item</th>
                                <th class="px-3 py-2 font-medium">
                                    Description
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Qty
                                </th>
                                <th class="px-3 py-2 font-medium">Unit</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Unit price
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Discount
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    VAT/Tax
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in purchaseOrder.items"
                                :key="item.id"
                                class="align-top"
                            >
                                <td class="px-3 py-2.5 whitespace-nowrap">
                                    <span
                                        v-if="item.product_service"
                                        class="font-medium"
                                    >
                                        {{ item.product_service.code }}
                                    </span>
                                    <span v-else class="text-muted-foreground"
                                        >Manual</span
                                    >
                                </td>
                                <td class="px-3 py-2.5 whitespace-pre-line">
                                    {{ item.description }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    {{ formatQuantity(item.quantity) }}
                                </td>
                                <td class="px-3 py-2.5">{{ item.unit }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    {{ formatMoney(item.unit_price) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    {{ formatMoney(item.discount) }}
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">
                                    {{ formatMoney(item.tax) }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right font-medium tabular-nums"
                                >
                                    {{ formatMoney(item.line_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <dl class="w-full max-w-xs space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">
                                {{ purchaseOrder.currency }}
                                {{ formatMoney(purchaseOrder.subtotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="tabular-nums">
                                {{ purchaseOrder.currency }}
                                {{ formatMoney(purchaseOrder.discount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">VAT/Tax</dt>
                            <dd class="tabular-nums">
                                {{ purchaseOrder.currency }}
                                {{ formatMoney(purchaseOrder.tax_amount) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Total</dt>
                            <dd class="tabular-nums">
                                {{ purchaseOrder.currency }}
                                {{ formatMoney(purchaseOrder.total_amount) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <!-- Notes & terms: one card, side by side -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Notes &amp; terms</CardTitle>
            </CardHeader>
            <CardContent class="px-4">
                <div class="grid gap-4 text-sm md:grid-cols-2">
                    <div>
                        <p class="mb-1 text-xs text-muted-foreground">Notes</p>
                        <p
                            class="whitespace-pre-line"
                            :class="{
                                'text-muted-foreground': !purchaseOrder.notes,
                            }"
                        >
                            {{ purchaseOrder.notes || 'No notes.' }}
                        </p>
                    </div>
                    <div>
                        <p class="mb-1 text-xs text-muted-foreground">
                            Terms &amp; conditions
                        </p>
                        <p
                            class="whitespace-pre-line"
                            :class="{
                                'text-muted-foreground':
                                    !purchaseOrder.terms_conditions,
                            }"
                        >
                            {{
                                purchaseOrder.terms_conditions ||
                                'No terms added.'
                            }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <DocumentsPanel
            documentable-type="purchase_order"
            :documentable-id="purchaseOrder.id"
            :documents="purchaseOrder.documents"
            :can="{
                upload: can.uploadDocuments,
                download: can.downloadDocuments,
                delete: can.deleteDocuments,
            }"
        />

        <ActivityHistoryPanel :logs="activityLogs" />
    </div>
</template>