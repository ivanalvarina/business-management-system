<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import PurchaseRequestController from '@/actions/App/Http/Controllers/PurchaseRequestController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/purchase-requests';

type CompanyOption = { id: number; company_code: string; company_name: string };
type VendorOption = {
    id: number;
    vendor_code: string;
    vendor_name: string;
    company_ids: number[];
};
type ProductOption = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
};
type Line = {
    product_service_id: number | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    tax: string;
};
type FormData = {
    company_id: number | null;
    vendor_id: number | null;
    request_date: string;
    date_required: string;
    client_project: string;
    requested_by_name: string;
    checked_by_name: string;
    noted_by_name: string;
    currency: string;
    notes: string;
    items: Line[];
};
type Existing = Partial<FormData> & {
    id: number;
    pr_no: string;
    items: Line[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    companies: CompanyOption[];
    vendors: VendorOption[];
    products: ProductOption[];
    purchaseRequest?: Existing;
}>();

const blankLine = (): Line => ({
    product_service_id: null,
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    tax: '0.00',
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm<FormData>({
    company_id:
        props.purchaseRequest?.company_id ?? props.companies[0]?.id ?? null,
    vendor_id: props.purchaseRequest?.vendor_id ?? null,
    request_date: props.purchaseRequest?.request_date ?? today,
    date_required: props.purchaseRequest?.date_required ?? '',
    client_project: props.purchaseRequest?.client_project ?? '',
    requested_by_name: props.purchaseRequest?.requested_by_name ?? '',
    checked_by_name: props.purchaseRequest?.checked_by_name ?? '',
    noted_by_name: props.purchaseRequest?.noted_by_name ?? '',
    currency: props.purchaseRequest?.currency ?? 'PHP',
    notes: props.purchaseRequest?.notes ?? '',
    items: props.purchaseRequest?.items?.length
        ? props.purchaseRequest.items
        : [blankLine()],
});

const filteredVendors = computed(() =>
    props.vendors.filter(
        (vendor) =>
            form.company_id !== null &&
            vendor.company_ids.includes(form.company_id),
    ),
);

const money = (value: number) =>
    value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const asNumber = (value: string | number | null | undefined) => {
    const number = Number(value ?? 0);

    return Number.isFinite(number) ? number : 0;
};

const lineSubtotal = (line: Line) =>
    asNumber(line.quantity) * asNumber(line.unit_price);
const lineTotal = (line: Line) => lineSubtotal(line) + asNumber(line.tax);
const subtotal = computed(() =>
    form.items.reduce((total, line) => total + lineSubtotal(line), 0),
);
const taxAmount = computed(() =>
    form.items.reduce((total, line) => total + asNumber(line.tax), 0),
);
const totalAmount = computed(() => subtotal.value + taxAmount.value);
const error = (key: string) => (form.errors as Record<string, string>)[key];

const addLine = () => form.items.push(blankLine());
const removeLine = (index: number) => {
    form.items.splice(index, 1);

    if (form.items.length === 0) {
        addLine();
    }
};
const applyProduct = (line: Line) => {
    const product = props.products.find(
        (product) => product.id === Number(line.product_service_id),
    );

    if (!product) {
        return;
    }

    line.description = product.description || product.name;
    line.unit = product.unit;
    line.unit_price = product.default_price;
};
const onCompanyChange = () => {
    if (
        form.vendor_id !== null &&
        !filteredVendors.value.some((vendor) => vendor.id === form.vendor_id)
    ) {
        form.vendor_id = null;
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(PurchaseRequestController.store.url());

        return;
    }

    if (props.purchaseRequest) {
        form.put(
            PurchaseRequestController.update.url(props.purchaseRequest.id),
        );
    }
};
</script>

<template>
    <form class="grid gap-6" @submit.prevent="submit">
        <!-- Details -->
        <Card>
            <CardHeader>
                <CardTitle>Details</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <!-- Row 1 -->
                    <div class="grid gap-2">
                        <Label for="company_id">Company</Label>
                        <select
                            id="company_id"
                            v-model="form.company_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            @change="onCompanyChange"
                        >
                            <option :value="null">Select company</option>
                            <option
                                v-for="company in companies"
                                :key="company.id"
                                :value="company.id"
                            >
                                {{ company.company_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.company_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="vendor_id">Vendor</Label>
                        <select
                            id="vendor_id"
                            v-model="form.vendor_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option :value="null">Select vendor</option>
                            <option
                                v-for="vendor in filteredVendors"
                                :key="vendor.id"
                                :value="vendor.id"
                            >
                                {{ vendor.vendor_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.vendor_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="client_project">Client / project</Label>
                        <Input
                            id="client_project"
                            v-model="form.client_project"
                        />
                        <InputError :message="form.errors.client_project" />
                    </div>

                    <!-- Row 2 -->
                    <div class="grid gap-2">
                        <Label for="request_date">Request date</Label>
                        <Input
                            id="request_date"
                            v-model="form.request_date"
                            type="date"
                        />
                        <InputError :message="form.errors.request_date" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="date_required">Date required</Label>
                        <Input
                            id="date_required"
                            v-model="form.date_required"
                            type="date"
                        />
                        <InputError :message="form.errors.date_required" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="currency">Currency</Label>
                        <Input
                            id="currency"
                            v-model="form.currency"
                            maxlength="3"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>

                    <!-- Row 3 -->
                    <div class="grid gap-2">
                        <Label for="requested_by_name">Requested by</Label>
                        <Input
                            id="requested_by_name"
                            v-model="form.requested_by_name"
                        />
                        <InputError :message="form.errors.requested_by_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="checked_by_name">Checked by</Label>
                        <Input
                            id="checked_by_name"
                            v-model="form.checked_by_name"
                        />
                        <InputError :message="form.errors.checked_by_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="noted_by_name">Noted by</Label>
                        <Input
                            id="noted_by_name"
                            v-model="form.noted_by_name"
                        />
                        <InputError :message="form.errors.noted_by_name" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Items + totals -->
        <Card>
            <CardHeader
                class="flex flex-row items-center justify-between gap-3"
            >
                <CardTitle>Items</CardTitle>
                <Button type="button" variant="outline" @click="addLine">
                    <Plus />
                    Add line
                </Button>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-3 py-3 font-medium">Item</th>
                                <th class="px-3 py-3 font-medium">
                                    Description
                                </th>
                                <th class="px-3 py-3 font-medium">Qty</th>
                                <th class="px-3 py-3 font-medium">Unit</th>
                                <th class="px-3 py-3 font-medium">Price</th>
                                <th class="px-3 py-3 font-medium">VAT/Tax</th>
                                <th class="px-3 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(line, lineIndex) in form.items"
                                :key="lineIndex"
                                class="align-top"
                            >
                                <td class="w-52 px-3 py-3">
                                    <select
                                        v-model="line.product_service_id"
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        @change="applyProduct(line)"
                                    >
                                        <option :value="null">
                                            Manual line
                                        </option>
                                        <option
                                            v-for="product in products"
                                            :key="product.id"
                                            :value="product.id"
                                        >
                                            {{ product.code }} -
                                            {{ product.name }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.product_service_id`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="min-w-64 px-3 py-3">
                                    <Input
                                        v-model="line.description"
                                        placeholder="Description"
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.description`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-24 px-3 py-3">
                                    <Input
                                        v-model="line.quantity"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.quantity`)
                                        "
                                    />
                                </td>
                                <td class="w-24 px-3 py-3">
                                    <Input v-model="line.unit" />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.unit`)
                                        "
                                    />
                                </td>
                                <td class="w-32 px-3 py-3">
                                    <Input
                                        v-model="line.unit_price"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.unit_price`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-32 px-3 py-3">
                                    <Input
                                        v-model="line.tax"
                                        inputmode="decimal"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.tax`)
                                        "
                                    />
                                </td>
                                <td
                                    class="w-32 px-3 py-3 text-right font-medium"
                                >
                                    <span class="inline-block pt-2">
                                        {{ money(lineTotal(line)) }}
                                    </span>
                                </td>
                                <td class="w-14 px-3 py-3 text-right">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        @click="removeLine(lineIndex)"
                                    >
                                        <Trash2 />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError :message="form.errors.items" />

                <!-- Totals: sa ilalim ng table, nasa kanan -->
                <div class="ml-auto w-full space-y-2 text-sm md:w-80">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span>{{ form.currency }} {{ money(subtotal) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">VAT/Tax</span>
                        <span>{{ form.currency }} {{ money(taxAmount) }}</span>
                    </div>
                    <div
                        class="flex justify-between border-t pt-3 text-base font-semibold"
                    >
                        <span>Total</span>
                        <span>{{ form.currency }} {{ money(totalAmount) }}</span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Notes -->
        <Card>
            <CardHeader>
                <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-2">
                <textarea
                    id="notes"
                    v-model="form.notes"
                    class="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </CardContent>
        </Card>

        <!-- Sticky action bar -->
        <div
            class="sticky bottom-0 z-10 -mb-4 flex items-center justify-between gap-3 border-t bg-background py-4"
        >
            <div class="text-sm text-muted-foreground">
                Purchase request total
                <span class="ml-1 text-lg font-semibold text-foreground">
                    {{ form.currency }} {{ money(totalAmount) }}
                </span>
            </div>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
                <Button :disabled="form.processing">
                    {{ mode === 'create' ? 'Create request' : 'Save request' }}
                </Button>
            </div>
        </div>
    </form>
</template>