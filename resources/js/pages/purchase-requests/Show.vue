<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import PurchaseRequestController from '@/actions/App/Http/Controllers/PurchaseRequestController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index, print as printRoute } from '@/routes/purchase-requests';

type Item = {
    id: number;
    product_service: { code: string; name: string } | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    tax: string;
    line_total: string;
};

type PurchaseRequest = {
    id: number;
    pr_no: string;
    request_date: string;
    date_required: string | null;
    client_project: string | null;
    requested_by_name: string | null;
    checked_by_name: string | null;
    noted_by_name: string | null;
    currency: string;
    status: string;
    notes: string | null;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    company: { company_code: string; company_name: string } | null;
    vendor: {
        vendor_code: string;
        vendor_name: string;
        address: string | null;
    } | null;
    items: Item[];
};

const props = defineProps<{
    purchaseRequest: PurchaseRequest;
    can: {
        edit: boolean;
        delete: boolean;
        submit: boolean;
        approve: boolean;
        reject: boolean;
        cancel: boolean;
        print: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase Requests', href: index() },
            { title: 'View', href: '#' },
        ],
    },
});

const money = (value: string) =>
    `${props.purchaseRequest.currency} ${Number(value).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    )}`;

const visitAction = (
    action: ReturnType<typeof PurchaseRequestController.submit>,
) => {
    router.visit(action, { preserveScroll: true });
};

const destroy = () => {
    if (!confirm('Delete this purchase request?')) {
        return;
    }

    router.visit(PurchaseRequestController.destroy(props.purchaseRequest.id));
};
</script>

<template>
    <Head :title="purchaseRequest.pr_no" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="purchaseRequest.pr_no"
            description="Purchase request details and approval actions."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back</Link>
                </Button>
                <Button v-if="can.print" variant="outline" as-child>
                    <Link :href="printRoute(purchaseRequest.id)">Print</Link>
                </Button>
                <Button v-if="can.edit" variant="outline" as-child>
                    <Link :href="edit(purchaseRequest.id)">Edit</Link>
                </Button>
                <Button
                    v-if="can.submit"
                    @click="
                        visitAction(
                            PurchaseRequestController.submit(
                                purchaseRequest.id,
                            ),
                        )
                    "
                    >Submit</Button
                >
                <Button
                    v-if="can.approve"
                    @click="
                        visitAction(
                            PurchaseRequestController.approve(
                                purchaseRequest.id,
                            ),
                        )
                    "
                    >Approve</Button
                >
                <Button
                    v-if="can.reject"
                    variant="outline"
                    @click="
                        visitAction(
                            PurchaseRequestController.reject(
                                purchaseRequest.id,
                            ),
                        )
                    "
                    >Reject</Button
                >
                <Button
                    v-if="can.cancel"
                    variant="outline"
                    @click="
                        visitAction(
                            PurchaseRequestController.cancel(
                                purchaseRequest.id,
                            ),
                        )
                    "
                    >Cancel</Button
                >
                <Button v-if="can.delete" variant="destructive" @click="destroy"
                    >Delete</Button
                >
            </template>
        </PageHeader>

        <!-- Details -->
        <Card>
            <CardHeader><CardTitle>Details</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <!-- Row 1 -->
                <div>
                    <p class="text-sm text-muted-foreground">Company</p>
                    <p class="font-medium">
                        {{ purchaseRequest.company?.company_name }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ purchaseRequest.company?.company_code }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Vendor</p>
                    <p class="font-medium">
                        {{ purchaseRequest.vendor?.vendor_name ?? 'Not set' }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ purchaseRequest.vendor?.vendor_code }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">
                        Client / project
                    </p>
                    <p class="font-medium">
                        {{ purchaseRequest.client_project ?? '-' }}
                    </p>
                </div>

                <!-- Row 2 -->
                <div>
                    <p class="text-sm text-muted-foreground">Request date</p>
                    <p class="font-medium">{{ purchaseRequest.request_date }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Date required</p>
                    <p class="font-medium">
                        {{ purchaseRequest.date_required ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <StatusBadge>{{
                        purchaseRequest.status.replaceAll('_', ' ')
                    }}</StatusBadge>
                </div>

                <!-- Row 3 -->
                <div>
                    <p class="text-sm text-muted-foreground">Requested by</p>
                    <p class="font-medium">
                        {{ purchaseRequest.requested_by_name ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Checked by</p>
                    <p class="font-medium">
                        {{ purchaseRequest.checked_by_name ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Noted by</p>
                    <p class="font-medium">
                        {{ purchaseRequest.noted_by_name ?? '-' }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Items + totals -->
        <Card>
            <CardHeader><CardTitle>Items</CardTitle></CardHeader>
            <CardContent class="space-y-4">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-3 font-medium">Item</th>
                                <th class="px-3 py-3 font-medium">
                                    Description
                                </th>
                                <th class="px-3 py-3 text-right font-medium">
                                    Qty
                                </th>
                                <th class="px-3 py-3 font-medium">Unit</th>
                                <th class="px-3 py-3 text-right font-medium">
                                    Price
                                </th>
                                <th class="px-3 py-3 text-right font-medium">
                                    VAT/Tax
                                </th>
                                <th class="px-3 py-3 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in purchaseRequest.items"
                                :key="item.id"
                            >
                                <td class="px-3 py-3">
                                    {{ item.product_service?.code ?? '-' }}
                                </td>
                                <td class="px-3 py-3 whitespace-pre-line">
                                    {{ item.description }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ Number(item.quantity) }}
                                </td>
                                <td class="px-3 py-3">{{ item.unit }}</td>
                                <td class="px-3 py-3 text-right">
                                    {{ money(item.unit_price) }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    {{ money(item.tax) }}
                                </td>
                                <td class="px-3 py-3 text-right font-medium">
                                    {{ money(item.line_total) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Totals: sa ilalim ng table, nasa kanan -->
                <div class="ml-auto w-full space-y-2 text-sm md:w-80">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span>{{ money(purchaseRequest.subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">VAT/Tax</span>
                        <span>{{ money(purchaseRequest.tax_amount) }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t pt-3 text-base font-semibold"
                    >
                        <span>Total</span>
                        <span>{{ money(purchaseRequest.total_amount) }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Notes -->
        <Card>
            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
            <CardContent>
                <p
                    class="text-sm whitespace-pre-line"
                    :class="{ 'text-muted-foreground': !purchaseRequest.notes }"
                >
                    {{ purchaseRequest.notes || 'No notes.' }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>