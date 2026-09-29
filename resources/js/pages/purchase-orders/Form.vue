<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import PurchaseOrderController from '@/actions/App/Http/Controllers/PurchaseOrderController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/purchase-orders';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type VendorOption = {
    id: number;
    vendor_code: string;
    vendor_name: string;
    company_ids: number[];
};

type ProductOption = {
    id: number;
    code: string;
    type: string;
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
};

type PurchaseRequestOption = {
    id: number;
    pr_no: string;
    company_id: number;
    vendor_id: number | null;
    currency: string;
    request_date: string;
    company: {
        company_code: string | null;
        company_name: string | null;
    };
    vendor: {
        vendor_code: string | null;
        vendor_name: string | null;
    } | null;
    items: {
        id: number;
        product_service_id: number | null;
        description: string;
        quantity: string;
        unit: string;
        unit_price: string;
        tax: string;
    }[];
};

type PurchaseOrderLine = {
    product_service_id: number | null;
    purchase_request_item_id: number | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
};

type PurchaseOrderFormData = {
    company_id: number | null;
    vendor_id: number | null;
    purchase_request_ids: number[];
    po_date: string;
    expected_delivery: string;
    currency: string;
    notes: string;
    terms_conditions: string;
    items: PurchaseOrderLine[];
};

type ExistingPurchaseOrder = Omit<
    Partial<PurchaseOrderFormData>,
    'expected_delivery' | 'notes' | 'terms_conditions'
> & {
    id: number;
    po_no: string;
    expected_delivery?: string | null;
    notes?: string | null;
    terms_conditions?: string | null;
    items: PurchaseOrderLine[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    companies: CompanyOption[];
    vendors: VendorOption[];
    products: ProductOption[];
    purchaseRequests: PurchaseRequestOption[];
    purchaseOrder?: ExistingPurchaseOrder;
}>();

const blankLine = (): PurchaseOrderLine => ({
    product_service_id: null,
    purchase_request_item_id: null,
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount: '0.00',
    tax: '0.00',
});

const today = new Date().toISOString().slice(0, 10);

const form = useForm<PurchaseOrderFormData>({
    company_id:
        props.purchaseOrder?.company_id ?? props.companies[0]?.id ?? null,
    vendor_id: props.purchaseOrder?.vendor_id ?? null,
    purchase_request_ids: props.purchaseOrder?.purchase_request_ids ?? [],
    po_date: props.purchaseOrder?.po_date ?? today,
    expected_delivery: props.purchaseOrder?.expected_delivery ?? '',
    currency: props.purchaseOrder?.currency ?? 'PHP',
    notes: props.purchaseOrder?.notes ?? '',
    terms_conditions: props.purchaseOrder?.terms_conditions ?? '',
    items: props.purchaseOrder?.items?.length
        ? props.purchaseOrder.items
        : [blankLine()],
});

const filteredVendors = computed(() =>
    props.vendors.filter(
        (vendor) =>
            form.company_id !== null &&
            vendor.company_ids.includes(form.company_id),
    ),
);

const approvedPurchaseRequests = computed(() =>
    props.purchaseRequests.filter(
        (purchaseRequest) =>
            (form.company_id === null ||
                purchaseRequest.company_id === form.company_id) &&
            (form.vendor_id === null ||
                purchaseRequest.vendor_id === null ||
                purchaseRequest.vendor_id === form.vendor_id),
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

const lineSubtotal = (line: PurchaseOrderLine) =>
    asNumber(line.quantity) * asNumber(line.unit_price);

const lineTotal = (line: PurchaseOrderLine) =>
    lineSubtotal(line) - asNumber(line.discount) + asNumber(line.tax);

const subtotal = computed(() =>
    form.items.reduce((total, line) => total + lineSubtotal(line), 0),
);

const discount = computed(() =>
    form.items.reduce((total, line) => total + asNumber(line.discount), 0),
);

const taxAmount = computed(() =>
    form.items.reduce((total, line) => total + asNumber(line.tax), 0),
);

const totalAmount = computed(
    () => subtotal.value - discount.value + taxAmount.value,
);

const error = (key: string) => (form.errors as Record<string, string>)[key];

const addLine = () => form.items.push(blankLine());

const removeLine = (index: number) => {
    form.items.splice(index, 1);

    if (form.items.length === 0) {
        addLine();
    }
};

const selectedProduct = (productId: number | null) =>
    props.products.find((product) => product.id === Number(productId));

const applyProduct = (line: PurchaseOrderLine) => {
    const product = selectedProduct(line.product_service_id);

    line.purchase_request_item_id = null;

    if (!product) {
        return;
    }

    line.description = product.description || product.name;
    line.unit = product.unit;
    line.unit_price = product.default_price;
};

// Shows which PR a copied line came from (display only).
const prNoForLine = (line: PurchaseOrderLine) => {
    if (line.purchase_request_item_id === null) {
        return null;
    }

    return (
        props.purchaseRequests.find((purchaseRequest) =>
            purchaseRequest.items.some(
                (item) => item.id === line.purchase_request_item_id,
            ),
        )?.pr_no ?? null
    );
};

const selectedPurchaseRequests = () =>
    props.purchaseRequests.filter((purchaseRequest) =>
        form.purchase_request_ids.includes(purchaseRequest.id),
    );

const applyPurchaseRequests = () => {
    let purchaseRequests = selectedPurchaseRequests();
    const manualLines = form.items.filter(
        (line) => line.purchase_request_item_id === null,
    );

    if (!purchaseRequests.length) {
        form.items = manualLines.length ? manualLines : [blankLine()];
        return;
    }

    const firstPurchaseRequest = purchaseRequests[0];
    form.company_id = firstPurchaseRequest.company_id;
    form.currency = firstPurchaseRequest.currency || form.currency;

    if (firstPurchaseRequest.vendor_id !== null) {
        form.vendor_id = firstPurchaseRequest.vendor_id;
    }

    purchaseRequests = purchaseRequests.filter(
        (purchaseRequest) =>
            purchaseRequest.company_id === form.company_id &&
            (form.vendor_id === null ||
                purchaseRequest.vendor_id === null ||
                purchaseRequest.vendor_id === form.vendor_id),
    );
    form.purchase_request_ids = purchaseRequests.map(
        (purchaseRequest) => purchaseRequest.id,
    );

    const purchaseRequestLines = purchaseRequests.flatMap((purchaseRequest) =>
        purchaseRequest.items.map((item) => ({
            product_service_id: item.product_service_id,
            purchase_request_item_id: item.id,
            description: item.description,
            quantity: item.quantity,
            unit: item.unit,
            unit_price: item.unit_price,
            discount: '0.00',
            tax: item.tax ?? '0.00',
        })),
    );

    form.items = [...purchaseRequestLines, ...manualLines];
};

const onCompanyChange = () => {
    if (
        form.vendor_id !== null &&
        !filteredVendors.value.some((vendor) => vendor.id === form.vendor_id)
    ) {
        form.vendor_id = null;
    }

    form.purchase_request_ids = form.purchase_request_ids.filter(
        (purchaseRequestId) =>
            approvedPurchaseRequests.value.some(
                (purchaseRequest) => purchaseRequest.id === purchaseRequestId,
            ),
    );

    applyPurchaseRequests();
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(PurchaseOrderController.store.url());

        return;
    }

    if (props.purchaseOrder) {
        form.put(PurchaseOrderController.update.url(props.purchaseOrder.id));
    }
};
</script>

<template>
    <form class="flex flex-col gap-4" @submit.prevent="submit">
        <!-- Details: compact 3-column grid -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Details</CardTitle>
            </CardHeader>
            <CardContent class="px-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
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
                    <div class="grid gap-1.5">
                        <Label for="vendor_id">Vendor</Label>
                        <select
                            id="vendor_id"
                            v-model="form.vendor_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            @change="onCompanyChange"
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
                    <div class="grid gap-1.5">
                        <Label for="currency">Currency</Label>
                        <Input
                            id="currency"
                            v-model="form.currency"
                            maxlength="3"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="po_date">PO date</Label>
                        <Input
                            id="po_date"
                            v-model="form.po_date"
                            type="date"
                        />
                        <InputError :message="form.errors.po_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="expected_delivery">Expected delivery</Label>
                        <Input
                            id="expected_delivery"
                            v-model="form.expected_delivery"
                            type="date"
                        />
                        <InputError :message="form.errors.expected_delivery" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Linked purchase requests: whole card is the checkbox -->
        <Card class="gap-4 py-4">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3 px-4"
            >
                <CardTitle class="text-base">
                    Link approved purchase requests
                </CardTitle>
                <span class="text-sm text-muted-foreground">
                    {{
                        form.purchase_request_ids.length
                            ? `${form.purchase_request_ids.length} selected`
                            : 'None selected'
                    }}
                </span>
            </CardHeader>
            <CardContent class="space-y-2 px-4">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="purchaseRequest in approvedPurchaseRequests"
                        :key="purchaseRequest.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:border-foreground/30 has-[:checked]:border-foreground has-[:checked]:bg-muted/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/50"
                    >
                        <input
                            v-model="form.purchase_request_ids"
                            type="checkbox"
                            :value="purchaseRequest.id"
                            class="peer sr-only"
                            @change="applyPurchaseRequests"
                        />
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border border-input text-transparent peer-checked:border-foreground peer-checked:bg-foreground peer-checked:text-background"
                        >
                            <Check class="size-3" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ purchaseRequest.pr_no }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                {{
                                    purchaseRequest.vendor?.vendor_name ??
                                    'Vendor to select'
                                }}
                                &middot; {{ purchaseRequest.request_date }}
                                &middot; {{ purchaseRequest.items.length }}
                                item(s)
                            </span>
                        </span>
                    </label>
                    <p
                        v-if="approvedPurchaseRequests.length === 0"
                        class="text-sm text-muted-foreground sm:col-span-2 lg:col-span-3"
                    >
                        No approved purchase requests match the selected
                        company/vendor.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    Only approved PRs are available. Selecting PRs copies all PR
                    lines into this PO.
                </p>
                <InputError :message="form.errors.purchase_request_ids" />
            </CardContent>
        </Card>

        <!-- Line items + inline totals -->
        <Card class="gap-4 py-4">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3 px-4"
            >
                <CardTitle class="text-base">Line items</CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addLine"
                >
                    <Plus />
                    Add line
                </Button>
            </CardHeader>
            <CardContent class="space-y-4 px-4">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-2 py-2 font-medium">Item</th>
                                <th class="px-2 py-2 font-medium">
                                    Description
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Qty
                                </th>
                                <th class="px-2 py-2 font-medium">Unit</th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Unit price
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Discount
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    VAT/Tax
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Total
                                </th>
                                <th class="px-2 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(line, lineIndex) in form.items"
                                :key="lineIndex"
                                class="align-top"
                            >
                                <td class="w-44 px-2 py-2">
                                    <select
                                        v-model="line.product_service_id"
                                        class="h-8 w-full rounded-md border border-input bg-background px-2 text-sm"
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
                                    <span
                                        v-if="prNoForLine(line)"
                                        class="mt-1 inline-block rounded-full bg-blue-500/10 px-2 py-0.5 text-[11px] font-medium text-blue-700 dark:text-blue-400"
                                    >
                                        {{ prNoForLine(line) }}
                                    </span>
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.product_service_id`,
                                            )
                                        "
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.purchase_request_item_id`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="min-w-64 px-2 py-2">
                                    <textarea
                                        v-model="line.description"
                                        rows="1"
                                        placeholder="Description"
                                        class="field-sizing-content min-h-8 w-full resize-y rounded-md border border-input bg-background px-2 py-1.5 text-sm"
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.description`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-20 px-2 py-2">
                                    <Input
                                        v-model="line.quantity"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.quantity`)
                                        "
                                    />
                                </td>
                                <td class="w-20 px-2 py-2">
                                    <Input v-model="line.unit" class="h-8 px-2" />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.unit`)
                                        "
                                    />
                                </td>
                                <td class="w-28 px-2 py-2">
                                    <Input
                                        v-model="line.unit_price"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.unit_price`,
                                            )
                                        "
                                    />
                                </td>
                                <td class="w-24 px-2 py-2">
                                    <Input
                                        v-model="line.discount"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.discount`)
                                        "
                                    />
                                </td>
                                <td class="w-24 px-2 py-2">
                                    <Input
                                        v-model="line.tax"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            error(`items.${lineIndex}.tax`)
                                        "
                                    />
                                </td>
                                <td
                                    class="w-28 px-2 py-2 pt-3 text-right font-medium tabular-nums"
                                >
                                    {{ money(lineTotal(line)) }}
                                </td>
                                <td class="w-10 px-2 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Remove line"
                                        @click="removeLine(lineIndex)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <InputError :message="form.errors.items" />

                <!-- Totals sit right under the lines -->
                <div class="flex justify-end">
                    <dl class="w-full max-w-xs space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(subtotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Discount</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(discount) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">VAT/Tax</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(taxAmount) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Total</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(totalAmount) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <!-- Notes & terms: side by side, short -->
        <Card class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Notes &amp; terms</CardTitle>
            </CardHeader>
            <CardContent class="px-4">
                <div class="grid gap-3 md:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="notes">Notes</Label>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            class="min-h-16 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="terms_conditions">Terms & conditions</Label>
                        <textarea
                            id="terms_conditions"
                            v-model="form.terms_conditions"
                            class="min-h-16 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="form.errors.terms_conditions" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Sticky action bar -->
        <div
            class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <p class="text-sm text-muted-foreground">
                Total
                <span class="ml-1 text-base font-semibold text-foreground">
                    {{ form.currency }} {{ money(totalAmount) }}
                </span>
            </p>
            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    {{
                        mode === 'create'
                            ? 'Create purchase order'
                            : 'Save purchase order'
                    }}
                </Button>
            </div>
        </div>
    </form>
</template>