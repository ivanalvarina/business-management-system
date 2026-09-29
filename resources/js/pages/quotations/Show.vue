<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import QuotationController from '@/actions/App/Http/Controllers/QuotationController';
import ActivityHistoryPanel from '@/components/app/ActivityHistoryPanel.vue';
import DocumentsPanel from '@/components/app/DocumentsPanel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index, print as printRoute } from '@/routes/quotations';

type QuotationItem = {
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

type Quotation = {
    id: number;
    quotation_no: string;
    quotation_date: string;
    valid_until: string | null;
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
    client: {
        client_code: string;
        client_name: string;
        email: string | null;
        phone: string | null;
        billing_address: string | null;
        shipping_address: string | null;
    } | null;
    items: QuotationItem[];
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
    quotation: Quotation;
    activityLogs: ActivityLog[];
    can: {
        edit: boolean;
        delete: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
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
            { title: 'Quotations', href: index() },
            { title: 'View', href: '#' },
        ],
    },
});

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusTone = (value: string) => {
    if (['approved', 'accepted'].includes(value)) {
        return 'success';
    }

    if (['for_approval', 'sent'].includes(value)) {
        return 'info';
    }

    return value === 'draft' ? 'neutral' : 'warning';
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

const runAction = (url: string) => {
    router.patch(url, {}, { preserveScroll: true });
};

const destroyQuotation = () => {
    if (window.confirm('Delete this quotation?')) {
        router.delete(QuotationController.destroy.url(props.quotation.id));
    }
};

const formatQuantity = (value: string | number | null | undefined) => {
    const quantity = Number(value ?? 0);

    return Number.isNaN(quantity)
        ? '0'
        : quantity.toLocaleString(undefined, {
              minimumFractionDigits: 0,
              maximumFractionDigits: 2,
          });
};
</script>

<template>
    <Head :title="quotation.quotation_no" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="quotation.quotation_no"
            description="Quotation details, historical line snapshots, and controlled status actions."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()"> Back </Link>
                </Button>
                <Button v-if="can.print" variant="outline" as-child>
                    <Link :href="printRoute(quotation.id)">
                        <Printer />
                        Print
                    </Link>
                </Button>
                <Button v-if="can.edit" variant="outline" as-child>
                    <Link :href="edit(quotation.id)">Edit</Link>
                </Button>
                <Button
                    v-if="can.submit"
                    @click="
                        runAction(QuotationController.submit.url(quotation.id))
                    "
                >
                    Submit
                </Button>
                <Button
                    v-if="can.approve"
                    @click="
                        runAction(QuotationController.approve.url(quotation.id))
                    "
                >
                    Approve
                </Button>
                <Button
                    v-if="can.reject"
                    variant="outline"
                    @click="
                        runAction(QuotationController.reject.url(quotation.id))
                    "
                >
                    Reject
                </Button>
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    @click="
                        runAction(QuotationController.cancel.url(quotation.id))
                    "
                >
                    Cancel
                </Button>
                <Button
                    v-if="can.delete"
                    variant="outline"
                    @click="destroyQuotation"
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
                            {{ quotation.company?.company_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ quotation.company?.company_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Client</dt>
                        <dd class="font-medium">
                            {{ quotation.client?.client_name }}
                        </dd>
                        <dd class="text-xs text-muted-foreground">
                            {{ quotation.client?.client_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-0.5">
                            <StatusBadge :tone="statusTone(quotation.status)">
                                {{ statusLabel(quotation.status) }}
                            </StatusBadge>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Quotation date
                        </dt>
                        <dd class="font-medium">
                            {{ quotation.quotation_date }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Valid until
                        </dt>
                        <dd class="font-medium">
                            {{ quotation.valid_until ?? 'Not set' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Currency</dt>
                        <dd class="font-medium">{{ quotation.currency }}</dd>
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
                                v-for="item in quotation.items"
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
                                    <span v-else class="text-muted-foreground">
                                        Manual
                                    </span>
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
                                {{ quotation.currency }}
                                {{ formatMoney(quotation.subtotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="tabular-nums">
                                {{ quotation.currency }}
                                {{ formatMoney(quotation.discount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">VAT/Tax</dt>
                            <dd class="tabular-nums">
                                {{ quotation.currency }}
                                {{ formatMoney(quotation.tax_amount) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Total</dt>
                            <dd class="tabular-nums">
                                {{ quotation.currency }}
                                {{ formatMoney(quotation.total_amount) }}
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
                                'text-muted-foreground': !quotation.notes,
                            }"
                        >
                            {{ quotation.notes || 'No notes.' }}
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
                                    !quotation.terms_conditions,
                            }"
                        >
                            {{
                                quotation.terms_conditions || 'No terms added.'
                            }}
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <DocumentsPanel
            documentable-type="quotation"
            :documentable-id="quotation.id"
            :documents="quotation.documents"
            :can="{
                upload: can.uploadDocuments,
                download: can.downloadDocuments,
                delete: can.deleteDocuments,
            }"
        />

        <ActivityHistoryPanel :logs="activityLogs" />
    </div>
</template>