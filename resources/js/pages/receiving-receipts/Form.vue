<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import ReceivingReceiptController from '@/actions/App/Http/Controllers/ReceivingReceiptController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/receiving-receipts';

type PoItem = {
    id: number;
    description: string;
    quantity: string;
    unit: string;
};
type PurchaseOrderOption = {
    id: number;
    po_no: string;
    company: { company_code: string; company_name: string };
    vendor: { vendor_code: string; vendor_name: string };
    items: PoItem[];
};
type Line = {
    purchase_order_item_id: number | null;
    quantity: string;
    description: string;
};
type FormData = {
    purchase_order_id: number | null;
    invoice_no: string;
    received_date: string;
    received_by_name: string;
    checked_by_name: string;
    notes: string;
    items: Line[];
};
type Existing = Partial<FormData> & {
    id: number;
    rr_no: string;
    items: Line[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    purchaseOrders: PurchaseOrderOption[];
    receivingReceipt?: Existing;
}>();

const blankLine = (): Line => ({
    purchase_order_item_id: null,
    quantity: '1',
    description: '',
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm<FormData>({
    purchase_order_id: props.receivingReceipt?.purchase_order_id ?? null,
    invoice_no: props.receivingReceipt?.invoice_no ?? '',
    received_date: props.receivingReceipt?.received_date ?? today,
    received_by_name: props.receivingReceipt?.received_by_name ?? '',
    checked_by_name: props.receivingReceipt?.checked_by_name ?? '',
    notes: props.receivingReceipt?.notes ?? '',
    items: props.receivingReceipt?.items?.length
        ? props.receivingReceipt.items
        : [blankLine()],
});

const selectedPurchaseOrder = computed(() =>
    props.purchaseOrders.find((po) => po.id === Number(form.purchase_order_id)),
);
const error = (key: string) => (form.errors as Record<string, string>)[key];

watch(
    () => form.purchase_order_id,
    () => {
        if (props.mode === 'edit') {
            return;
        }

        form.items = selectedPurchaseOrder.value?.items.length
            ? selectedPurchaseOrder.value.items.map((item) => ({
                  purchase_order_item_id: item.id,
                  quantity: String(Number(item.quantity)),
                  description: item.description,
              }))
            : [blankLine()];
    },
);

const applyPoItem = (line: Line) => {
    const item = selectedPurchaseOrder.value?.items.find(
        (item) => item.id === Number(line.purchase_order_item_id),
    );

    if (!item) {
        return;
    }

    line.quantity = String(Number(item.quantity));
    line.description = item.description;
};
const addLine = () => form.items.push(blankLine());
const removeLine = (index: number) => {
    form.items.splice(index, 1);

    if (form.items.length === 0) {
        addLine();
    }
};
const submit = () => {
    if (props.mode === 'create') {
        form.post(ReceivingReceiptController.store.url());

        return;
    }

    if (props.receivingReceipt) {
        form.put(
            ReceivingReceiptController.update.url(props.receivingReceipt.id),
        );
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Receiving details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="purchase_order_id">Purchase order</Label>
                    <select
                        id="purchase_order_id"
                        v-model="form.purchase_order_id"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option :value="null">Select purchase order</option>
                        <option
                            v-for="po in purchaseOrders"
                            :key="po.id"
                            :value="po.id"
                        >
                            {{ po.po_no }} - {{ po.vendor.vendor_name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.purchase_order_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="invoice_no">Invoice / DR</Label>
                    <Input id="invoice_no" v-model="form.invoice_no" />
                    <InputError :message="form.errors.invoice_no" />
                </div>
                <div class="grid gap-2">
                    <Label for="received_date">Received date</Label>
                    <Input
                        id="received_date"
                        v-model="form.received_date"
                        type="date"
                    />
                    <InputError :message="form.errors.received_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="received_by_name">Received by</Label>
                    <Input
                        id="received_by_name"
                        v-model="form.received_by_name"
                    />
                    <InputError :message="form.errors.received_by_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="checked_by_name">Checked by</Label>
                    <Input
                        id="checked_by_name"
                        v-model="form.checked_by_name"
                    />
                    <InputError :message="form.errors.checked_by_name" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="notes">Notes</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        class="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <CardTitle>Received items</CardTitle>
                <Button type="button" variant="outline" @click="addLine">
                    <Plus />
                    Add line
                </Button>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-3 font-medium">PO item</th>
                                <th class="px-3 py-3 font-medium">Quantity</th>
                                <th class="px-3 py-3 font-medium">
                                    Particulars
                                </th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(line, indexLine) in form.items"
                                :key="indexLine"
                                class="align-top"
                            >
                                <td class="w-64 px-3 py-3">
                                    <select
                                        v-model="line.purchase_order_item_id"
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        @change="applyPoItem(line)"
                                    >
                                        <option :value="null">
                                            Manual line
                                        </option>
                                        <option
                                            v-for="item in selectedPurchaseOrder?.items ??
                                            []"
                                            :key="item.id"
                                            :value="item.id"
                                        >
                                            {{ item.description }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            error(
                                                `items.${indexLine}.purchase_order_item_id`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-32 px-3 py-3">
                                    <Input
                                        v-model="line.quantity"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${indexLine}.quantity`)
                                        "
                                    />
                                </td>
                                <td class="px-3 py-3">
                                    <textarea
                                        v-model="line.description"
                                        class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${indexLine}.description`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-14 px-3 py-3 text-right">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        @click="removeLine(indexLine)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError :message="form.errors.items" />
            </CardContent>
        </Card>

        <div class="flex gap-2">
            <Button :disabled="form.processing">
                {{ mode === 'create' ? 'Record receipt' : 'Save receipt' }}
            </Button>
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
