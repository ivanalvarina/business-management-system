<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ClientPurchaseOrderController from '@/actions/App/Http/Controllers/ClientPurchaseOrderController';
import ActivityHistoryPanel from '@/components/app/ActivityHistoryPanel.vue';
import DocumentsPanel from '@/components/app/DocumentsPanel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import Money from '@/components/Money.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/client-pos';
import { show as quotationShow } from '@/routes/quotations';

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

type ClientPurchaseOrder = {
    id: number;
    client_po_no: string;
    po_date: string;
    currency: string;
    amount: string;
    status: string;
    received_at: string | null;
    notes: string | null;
    company: {
        company_code: string;
        company_name: string;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    client: {
        client_code: string;
        client_name: string;
        email: string | null;
        phone: string | null;
        billing_address: string | null;
        shipping_address: string | null;
    } | null;
    quotations: {
        id: number;
        quotation_no: string;
        quotation_date: string | null;
        status: string;
        currency: string;
        total_amount: string;
    }[];
    receiver: { id: number; name: string } | null;
    documents: DocumentRecord[];
    items: ClientPoItem[];
    inventory_deductions: { description: string; quantity: string }[];
};

type ClientPoItem = {
    id: number;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
    line_total: string;
    product_service: {
        code: string;
        name: string;
        type: 'product' | 'service';
        primary_image: { url: string } | null;
    } | null;
};

type ActivityLog = {
    id: number;
    module: string;
    action: string;
    created_at: string | null;
    user: { id: number; name: string } | null;
};

const props = defineProps<{
    clientPurchaseOrder: ClientPurchaseOrder;
    activityLogs: ActivityLog[];
    can: {
        edit: boolean;
        delete: boolean;
        process: boolean;
        fulfill: boolean;
        complete: boolean;
        cancel: boolean;
        uploadDocuments: boolean;
        downloadDocuments: boolean;
        deleteDocuments: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Client Purchase Orders', href: index() },
            { title: 'View', href: '#' },
        ],
    },
});

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusTone = (value: string) => {
    if (value === 'confirmed') {
        return 'success';
    }

    if (['in_review', 'processing'].includes(value)) {
        return 'info';
    }

    if (['fulfilled', 'completed'].includes(value)) {
        return 'success';
    }

    return value === 'cancelled' ? 'warning' : 'neutral';
};

const updateStatus = (action: 'process' | 'complete' | 'cancel') => {
    const route = ClientPurchaseOrderController[action].url(
        props.clientPurchaseOrder.id,
    );

    router.patch(route, {}, { preserveScroll: true });
};

const fulfillClientPo = () => {
    const deductions = props.clientPurchaseOrder.inventory_deductions
        .map((item) => `${item.description} x ${item.quantity}`)
        .join('\n');

    if (
        !window.confirm(
            `Fulfill Client PO ${props.clientPurchaseOrder.client_po_no}?\n\nThis will deduct the following products from inventory:\n\n${deductions || 'No product stock will be deducted.'}`,
        )
    ) {
        return;
    }

    router.patch(
        ClientPurchaseOrderController.fulfill.url(props.clientPurchaseOrder.id),
        {},
        { preserveScroll: true },
    );
};

const destroyClientPo = () => {
    if (window.confirm('Delete this client purchase order?')) {
        router.delete(
            ClientPurchaseOrderController.destroy.url(
                props.clientPurchaseOrder.id,
            ),
        );
    }
};

const formatMoney = (value: string | number | null | undefined) => {
    const amount = Number(value ?? 0);

    return Number.isNaN(amount)
        ? '0.00'
        : amount.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};

const formatQuantity = (value: string) => {
    const numberValue = parseFloat(value);
    return isNaN(numberValue) ? value : numberValue.toLocaleString();
};

// Breakdown shown under the table; the total itself is the saved `amount`.
const subtotal = computed(() =>
    props.clientPurchaseOrder.items.reduce(
        (total, item) =>
            total + Number(item.quantity || 0) * Number(item.unit_price || 0),
        0,
    ),
);

const discountTotal = computed(() =>
    props.clientPurchaseOrder.items.reduce(
        (total, item) => total + Number(item.discount || 0),
        0,
    ),
);

const taxTotal = computed(() =>
    props.clientPurchaseOrder.items.reduce(
        (total, item) => total + Number(item.tax || 0),
        0,
    ),
);
</script>

<template>
    <Head :title="clientPurchaseOrder.client_po_no" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="clientPurchaseOrder.client_po_no"
            description="Client purchase order received by the company."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back</Link>
                </Button>
                <Button v-if="can.edit" variant="outline" as-child>
                    <Link :href="edit(clientPurchaseOrder.id)">Edit</Link>
                </Button>
                <Button
                    v-if="can.process"
                    variant="outline"
                    @click="updateStatus('process')"
                >
                    Start processing
                </Button>
                <Button v-if="can.fulfill" @click="fulfillClientPo">
                    Fulfill client PO
                </Button>
                <Button
                    v-if="can.complete"
                    variant="outline"
                    @click="updateStatus('complete')"
                >
                    Complete
                </Button>
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    @click="updateStatus('cancel')"
                >
                    Cancel
                </Button>
                <Button
                    v-if="can.delete"
                    variant="outline"
                    @click="destroyClientPo"
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
                            {{ clientPurchaseOrder.company?.company_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ clientPurchaseOrder.company?.company_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Client</dt>
                        <dd class="font-medium">
                            {{ clientPurchaseOrder.client?.client_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ clientPurchaseOrder.client?.client_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-0.5">
                            <StatusBadge
                                :tone="statusTone(clientPurchaseOrder.status)"
                            >
                                {{ statusLabel(clientPurchaseOrder.status) }}
                            </StatusBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">PO date</dt>
                        <dd class="font-medium">
                            {{ clientPurchaseOrder.po_date }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Received at
                        </dt>
                        <dd class="font-medium">
                            {{ clientPurchaseOrder.received_at ?? 'Not set' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Received by
                        </dt>
                        <dd class="font-medium">
                            {{
                                clientPurchaseOrder.receiver?.name ?? 'Not set'
                            }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs text-muted-foreground">
                            Linked quotations
                        </dt>
                        <dd class="mt-1 flex flex-wrap gap-1.5">
                            <Link
                                v-for="quotation in clientPurchaseOrder.quotations"
                                :key="quotation.id"
                                :href="quotationShow(quotation.id)"
                                class="rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-700 hover:bg-blue-500/20 dark:text-blue-400"
                            >
                                {{ quotation.quotation_no }}
                                &middot;
                                {{ quotation.currency }}
                                {{ formatMoney(quotation.total_amount) }}
                            </Link>
                            <span
                                v-if="!clientPurchaseOrder.quotations.length"
                                class="text-muted-foreground"
                            >
                                No linked quotations.
                            </span>
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Items + totals right under the table -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Items</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 px-4">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    Product / Service
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Qty
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Unit price
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Discount
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Tax
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in clientPurchaseOrder.items"
                                :key="item.id"
                                class="align-top"
                            >
                                <!-- Image + description + code in one cell -->
                                <td class="px-3 py-2.5">
                                    <div class="flex items-start gap-3">
                                        <img
                                            v-if="
                                                item.product_service
                                                    ?.primary_image
                                            "
                                            :src="
                                                item.product_service
                                                    .primary_image.url
                                            "
                                            :alt="item.description"
                                            class="size-10 shrink-0 rounded-md object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex size-10 shrink-0 items-center justify-center rounded-md border border-dashed text-muted-foreground"
                                        >
                                            -
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-medium">
                                                {{ item.description }}
                                            </p>
                                            <p
                                                v-if="item.product_service"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ item.product_service.code }}
                                                &middot;
                                                {{
                                                    item.product_service
                                                        .type === 'product'
                                                        ? 'Product'
                                                        : 'Service'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-2.5 text-right whitespace-nowrap tabular-nums"
                                >
                                    {{ formatQuantity(item.quantity) }}
                                    {{ item.unit }}
                                </td>
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
                            <tr v-if="clientPurchaseOrder.items.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-10 text-center text-muted-foreground"
                                >
                                    No items recorded.
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
                                <Money
                                    :amount="subtotal.toFixed(2)"
                                    :currency="clientPurchaseOrder.currency"
                                />
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="tabular-nums">
                                <Money
                                    :amount="discountTotal.toFixed(2)"
                                    :currency="clientPurchaseOrder.currency"
                                />
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">VAT/Tax</dt>
                            <dd class="tabular-nums">
                                <Money
                                    :amount="taxTotal.toFixed(2)"
                                    :currency="clientPurchaseOrder.currency"
                                />
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Client PO total</dt>
                            <dd class="tabular-nums">
                                <Money
                                    :amount="clientPurchaseOrder.amount"
                                    :currency="clientPurchaseOrder.currency"
                                />
                            </dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <!-- Notes -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Notes</CardTitle>
            </CardHeader>
            <CardContent
                class="px-4 text-sm whitespace-pre-line"
                :class="{ 'text-muted-foreground': !clientPurchaseOrder.notes }"
            >
                {{ clientPurchaseOrder.notes || 'No notes.' }}
            </CardContent>
        </Card>

        <DocumentsPanel
            documentable-type="client_purchase_order"
            :documentable-id="clientPurchaseOrder.id"
            :documents="clientPurchaseOrder.documents"
            :can="{
                upload: can.uploadDocuments,
                download: can.downloadDocuments,
                delete: can.deleteDocuments,
            }"
        />

        <ActivityHistoryPanel :logs="activityLogs" />
    </div>
</template>