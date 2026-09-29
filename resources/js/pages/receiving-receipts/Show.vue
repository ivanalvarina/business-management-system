<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ReceivingReceiptController from '@/actions/App/Http/Controllers/ReceivingReceiptController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index, print as printRoute } from '@/routes/receiving-receipts';

type Item = { id: number; quantity: string; description: string };
type Receipt = {
    id: number;
    rr_no: string;
    invoice_no: string | null;
    received_date: string;
    received_by_name: string | null;
    checked_by_name: string | null;
    status: string;
    company: { company_code: string; company_name: string };
    vendor: {
        vendor_code: string;
        vendor_name: string;
        address: string | null;
    };
    purchase_order: { id: number; po_no: string };
    items: Item[];
};

const props = defineProps<{
    receivingReceipt: Receipt;
    can: { edit: boolean; delete: boolean; print: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Receiving Receipts', href: index() },
            { title: 'View', href: '#' },
        ],
    },
});

const destroy = () => {
    if (!confirm('Delete this receiving receipt?')) {
        return;
    }

    router.visit(ReceivingReceiptController.destroy(props.receivingReceipt.id));
};
</script>

<template>
    <Head :title="receivingReceipt.rr_no" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="receivingReceipt.rr_no"
            description="Receiving receipt details."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back</Link>
                </Button>
                <Button v-if="can.print" variant="outline" as-child>
                    <Link :href="printRoute(receivingReceipt.id)">Print</Link>
                </Button>
                <Button v-if="can.edit" variant="outline" as-child>
                    <Link :href="edit(receivingReceipt.id)">Edit</Link>
                </Button>
                <Button v-if="can.delete" variant="destructive" @click="destroy"
                    >Delete</Button
                >
            </template>
        </PageHeader>

        <Card>
            <CardHeader><CardTitle>Header</CardTitle></CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">Purchase order</p>
                    <p class="font-medium">
                        {{ receivingReceipt.purchase_order.po_no }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Invoice / DR</p>
                    <p class="font-medium">
                        {{ receivingReceipt.invoice_no ?? '-' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Vendor</p>
                    <p class="font-medium">
                        {{ receivingReceipt.vendor.vendor_name }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Company</p>
                    <p class="font-medium">
                        {{ receivingReceipt.company.company_name }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Received date</p>
                    <p class="font-medium">
                        {{ receivingReceipt.received_date }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <StatusBadge>{{ receivingReceipt.status }}</StatusBadge>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>Items</CardTitle></CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-3 text-right">Quantity</th>
                                <th class="px-3 py-3">Particulars</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="item in receivingReceipt.items"
                                :key="item.id"
                            >
                                <td class="w-32 px-3 py-3 text-right">
                                    {{ Number(item.quantity) }}
                                </td>
                                <td class="px-3 py-3 whitespace-pre-line">
                                    {{ item.description }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
