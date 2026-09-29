<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Check, Plus, Trash2 } from '@lucide/vue';
import { computed, watch } from 'vue';
import ClientPurchaseOrderController from '@/actions/App/Http/Controllers/ClientPurchaseOrderController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/client-pos';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type ClientOption = {
    id: number;
    client_code: string;
    client_name: string;
    company_ids: number[];
};

type QuotationOption = {
    id: number;
    quotation_no: string;
    company_id: number;
    client_id: number;
    quotation_date: string | null;
    currency: string;
    total_amount: string;
    status: string;
    items: QuotationLineItem[];
};

type ProductServiceOption = {
    id: number;
    code: string;
    type: 'product' | 'service';
    name: string;
    unit: string;
    default_price: string;
    quantity: number;
    primary_image: { url: string } | null;
};

type LineItem = {
    product_service_id: number | null;
    quotation_item_id: number | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
};

type QuotationLineItem = LineItem & {
    id: number;
};

type ClientPurchaseOrder = {
    id: number;
    company_id: number;
    client_id: number;
    quotation_ids: number[];
    client_po_no: string;
    po_date: string;
    currency: string;
    amount: string;
    status: string;
    received_at: string | null;
    notes: string | null;
    items: LineItem[];
};

type ClientPurchaseOrderForm = {
    company_id: number | null;
    client_id: number | null;
    quotation_ids: number[];
    client_po_no: string;
    po_date: string;
    currency: string;
    amount: string;
    status: string;
    received_at: string;
    notes: string;
    items: LineItem[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    companies: CompanyOption[];
    clients: ClientOption[];
    quotations: QuotationOption[];
    productServices: ProductServiceOption[];
    statuses: string[];
    clientPurchaseOrder?: ClientPurchaseOrder;
}>();

// Local YYYY-MM-DD (toISOString() is UTC and can be a day behind in PH mornings).
const toDateString = (date: Date) => {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const today = toDateString(new Date());

const trimDecimal = (value: string | number | null | undefined) => {
    const numberValue = Number(value ?? 0);

    if (!Number.isFinite(numberValue)) {
        return '';
    }

    return numberValue.toString();
};

const normalizeLineItem = (line: LineItem): LineItem => ({
    ...line,
    quantity: trimDecimal(line.quantity),
});

const blankLine = (): LineItem => ({
    product_service_id: null,
    quotation_item_id: null,
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount: '0.00',
    tax: '0.00',
});

const form = useForm<ClientPurchaseOrderForm>({
    company_id:
        props.clientPurchaseOrder?.company_id ?? props.companies[0]?.id ?? null,
    client_id: props.clientPurchaseOrder?.client_id ?? null,
    quotation_ids: props.clientPurchaseOrder?.quotation_ids ?? [],
    client_po_no: props.clientPurchaseOrder?.client_po_no ?? '',
    po_date: props.clientPurchaseOrder?.po_date ?? today,
    currency: props.clientPurchaseOrder?.currency ?? 'PHP',
    amount: props.clientPurchaseOrder?.amount ?? '0.00',
    status: props.clientPurchaseOrder?.status ?? 'received',
    received_at: props.clientPurchaseOrder?.received_at ?? '',
    notes: props.clientPurchaseOrder?.notes ?? '',
    items: props.clientPurchaseOrder?.items?.map(normalizeLineItem) ?? [
        blankLine(),
    ],
});

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const money = (value: number | string) => {
    const number = Number(value);

    return Number.isFinite(number)
        ? number.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : String(value);
};

const filteredClients = computed(() =>
    props.clients.filter(
        (client) =>
            form.company_id !== null &&
            client.company_ids.includes(form.company_id),
    ),
);

const filteredQuotations = computed(() =>
    props.quotations.filter(
        (quotation) =>
            quotation.company_id === form.company_id &&
            quotation.client_id === form.client_id,
    ),
);

const selectedQuotations = () =>
    props.quotations.filter((quotation) =>
        form.quotation_ids.includes(quotation.id),
    );

const onCompanyChange = () => {
    if (
        form.client_id !== null &&
        !filteredClients.value.some((client) => client.id === form.client_id)
    ) {
        form.client_id = null;
    }

    form.quotation_ids = [];
    applyQuotations();
};

const onClientChange = () => {
    form.quotation_ids = form.quotation_ids.filter((quotationId) =>
        filteredQuotations.value.some((quotation) => quotation.id === quotationId),
    );
    applyQuotations();
};

const applyQuotations = () => {
    const quotations = selectedQuotations();
    const manualLines = form.items.filter(
        (line) => line.quotation_item_id === null,
    );

    if (!quotations.length) {
        form.items = manualLines.length ? manualLines : [blankLine()];

        return;
    }

    form.currency = quotations[0].currency;
    const quotationLines = quotations.flatMap((quotation) =>
        quotation.items.map((item) => {
            const { id, ...line } = item;

            return normalizeLineItem({
                ...line,
                quotation_item_id: id,
            });
        }),
    );

    form.items = [...quotationLines, ...manualLines];
};

const selectedProductService = (line: LineItem) =>
    props.productServices.find((item) => item.id === line.product_service_id);

// Display only: which quotation a copied line came from.
const quotationNoForLine = (line: LineItem) => {
    if (line.quotation_item_id === null) {
        return null;
    }

    return (
        props.quotations.find((quotation) =>
            quotation.items.some((item) => item.id === line.quotation_item_id),
        )?.quotation_no ?? null
    );
};

// Warning only: it never blocks saving.
const exceedsStock = (line: LineItem) => {
    const item = selectedProductService(line);

    return (
        item?.type === 'product' && Number(line.quantity || 0) > item.quantity
    );
};

const stockNote = (line: LineItem) => {
    const item = selectedProductService(line);

    if (!item) {
        return '';
    }

    if (item.type !== 'product') {
        return 'Service';
    }

    return exceedsStock(line)
        ? `Only ${item.quantity} in stock`
        : `${item.quantity} in stock`;
};

const lineTotal = (line: LineItem) =>
    Math.max(
        0,
        Number(line.quantity || 0) * Number(line.unit_price || 0) -
            Number(line.discount || 0) +
            Number(line.tax || 0),
    );

const subtotal = computed(() =>
    form.items.reduce(
        (total, line) =>
            total + Number(line.quantity || 0) * Number(line.unit_price || 0),
        0,
    ),
);

const discountTotal = computed(() =>
    form.items.reduce((total, line) => total + Number(line.discount || 0), 0),
);

const taxTotal = computed(() =>
    form.items.reduce((total, line) => total + Number(line.tax || 0), 0),
);

const totalAmount = computed(() =>
    form.items.reduce((total, line) => total + lineTotal(line), 0).toFixed(2),
);

watch(
    totalAmount,
    (amount) => {
        form.amount = amount;
    },
    { immediate: true },
);

const addItem = () => {
    form.items.push(blankLine());
};

const removeItem = (index: number) => {
    form.items.splice(index, 1);

    if (form.items.length === 0) {
        addItem();
    }
};

const applyProductService = (line: LineItem) => {
    const item = selectedProductService(line);

    line.quotation_item_id = null;

    if (!item) {
        return;
    }

    line.description = `${item.code} - ${item.name}`;
    line.unit = item.unit;
    line.unit_price = item.default_price;
};

const normalizeCurrency = () => {
    form.currency = form.currency.toUpperCase().slice(0, 3);
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(ClientPurchaseOrderController.store.url());

        return;
    }

    if (props.clientPurchaseOrder) {
        form.put(
            ClientPurchaseOrderController.update.url(
                props.clientPurchaseOrder.id,
            ),
        );
    }
};
</script>

<template>
    <form class="flex w-full min-w-0 flex-col gap-4" @submit.prevent="submit">
        <!-- Details: compact 3-column grid -->
        <Card class="w-full min-w-0 gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Details</CardTitle>
            </CardHeader>
            <CardContent class="min-w-0 px-4">
                <div
                    class="grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div class="grid min-w-0 gap-1.5">
                        <Label for="company_id">Company</Label>
                        <select
                            id="company_id"
                            v-model="form.company_id"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm"
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

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="client_id">Client</Label>
                        <select
                            id="client_id"
                            v-model="form.client_id"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm"
                            @change="onClientChange"
                        >
                            <option :value="null">Select client</option>
                            <option
                                v-for="client in filteredClients"
                                :key="client.id"
                                :value="client.id"
                            >
                                {{ client.client_name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.client_id" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="client_po_no">Client PO number</Label>
                        <Input
                            id="client_po_no"
                            v-model="form.client_po_no"
                            class="w-full min-w-0"
                        />
                        <InputError :message="form.errors.client_po_no" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="po_date">PO date</Label>
                        <Input
                            id="po_date"
                            v-model="form.po_date"
                            type="date"
                            class="w-full min-w-0"
                        />
                        <InputError :message="form.errors.po_date" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="received_at">Received at</Label>
                        <Input
                            id="received_at"
                            v-model="form.received_at"
                            type="datetime-local"
                            class="w-full min-w-0"
                        />
                        <InputError :message="form.errors.received_at" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="statusOption in statuses"
                                :key="statusOption"
                                :value="statusOption"
                            >
                                {{ statusLabel(statusOption) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid min-w-0 gap-1.5">
                        <Label for="currency">Currency</Label>
                        <Input
                            id="currency"
                            v-model="form.currency"
                            class="w-full min-w-0 uppercase"
                            maxlength="3"
                            @input="normalizeCurrency"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Linked quotations: whole card is the checkbox -->
        <Card class="w-full min-w-0 gap-4 py-4">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3 px-4"
            >
                <CardTitle class="text-base">
                    Linked approved quotations
                </CardTitle>
                <span class="text-sm text-muted-foreground">
                    {{
                        form.quotation_ids.length
                            ? `${form.quotation_ids.length} selected`
                            : 'None selected'
                    }}
                </span>
            </CardHeader>
            <CardContent class="space-y-2 px-4">
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <label
                        v-for="quotation in filteredQuotations"
                        :key="quotation.id"
                        class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 text-sm transition-colors hover:border-foreground/30 has-[:checked]:border-foreground has-[:checked]:bg-muted/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring/50"
                    >
                        <input
                            v-model="form.quotation_ids"
                            type="checkbox"
                            :value="quotation.id"
                            class="peer sr-only"
                            @change="applyQuotations"
                        />
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border border-input text-transparent peer-checked:border-foreground peer-checked:bg-foreground peer-checked:text-background"
                        >
                            <Check class="size-3" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium">
                                {{ quotation.quotation_no }}
                            </span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                            >
                                {{ quotation.currency }}
                                {{ money(quotation.total_amount) }}
                                &middot; {{ quotation.quotation_date }}
                                &middot; {{ quotation.items.length }} item(s)
                            </span>
                        </span>
                    </label>
                    <p
                        v-if="filteredQuotations.length === 0"
                        class="text-sm text-muted-foreground sm:col-span-2 lg:col-span-3"
                    >
                        No approved quotations match the selected
                        company/client.
                    </p>
                </div>
                <p class="text-xs text-muted-foreground">
                    Selecting a quotation copies its lines into this client PO.
                </p>
                <InputError :message="form.errors.quotation_ids" />
            </CardContent>
        </Card>

        <!-- Items + inline totals -->
        <Card class="w-full min-w-0 gap-4 py-4">
            <CardHeader
                class="flex flex-row items-center justify-between gap-3 px-4"
            >
                <CardTitle class="text-base">Items</CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="addItem"
                >
                    <Plus />
                    Add item
                </Button>
            </CardHeader>

            <CardContent class="min-w-0 space-y-4 px-4">
                <!-- Only this container scrolls horizontally on small screens -->
                <div class="w-full min-w-0 overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-2 py-2 font-medium">
                                    Product / Service
                                </th>
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
                                    Tax
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
                                <!-- Image + product select + stock note in one cell -->
                                <td class="w-64 px-2 py-2">
                                    <div class="flex items-start gap-2">
                                        <img
                                            v-if="
                                                selectedProductService(line)
                                                    ?.primary_image
                                            "
                                            :src="
                                                selectedProductService(line)
                                                    ?.primary_image?.url
                                            "
                                            :alt="
                                                selectedProductService(line)
                                                    ?.name
                                            "
                                            class="size-8 shrink-0 rounded-md object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex size-8 shrink-0 items-center justify-center rounded-md border border-dashed text-xs text-muted-foreground"
                                        >
                                            -
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <select
                                                v-model="line.product_service_id"
                                                class="h-8 w-full rounded-md border border-input bg-background px-2 text-sm"
                                                @change="
                                                    applyProductService(line)
                                                "
                                            >
                                                <option :value="null">
                                                    Manual line
                                                </option>
                                                <option
                                                    v-for="item in productServices"
                                                    :key="item.id"
                                                    :value="item.id"
                                                >
                                                    {{ item.code }} -
                                                    {{ item.name }}
                                                </option>
                                            </select>

                                            <p
                                                v-if="
                                                    stockNote(line) ||
                                                    quotationNoForLine(line)
                                                "
                                                class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-[11px]"
                                                :class="
                                                    exceedsStock(line)
                                                        ? 'text-amber-600 dark:text-amber-400'
                                                        : 'text-muted-foreground'
                                                "
                                            >
                                                <span v-if="stockNote(line)">
                                                    {{ stockNote(line) }}
                                                </span>
                                                <span
                                                    v-if="
                                                        quotationNoForLine(line)
                                                    "
                                                    class="rounded-full bg-blue-500/10 px-1.5 py-px font-medium text-blue-700 dark:text-blue-400"
                                                >
                                                    {{
                                                        quotationNoForLine(line)
                                                    }}
                                                </span>
                                            </p>

                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `items.${lineIndex}.product_service_id`
                                                    ]
                                                "
                                            />
                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `items.${lineIndex}.quotation_item_id`
                                                    ]
                                                "
                                            />
                                        </div>
                                    </div>
                                </td>

                                <!-- Description -->
                                <td class="min-w-48 px-2 py-2">
                                    <Input
                                        v-model="line.description"
                                        placeholder="Description"
                                        class="h-8 w-full px-2"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${lineIndex}.description`
                                            ]
                                        "
                                    />
                                </td>

                                <!-- Quantity -->
                                <td class="w-20 px-2 py-2">
                                    <Input
                                        v-model="line.quantity"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${lineIndex}.quantity`
                                            ]
                                        "
                                    />
                                </td>

                                <!-- Unit -->
                                <td class="w-20 px-2 py-2">
                                    <Input v-model="line.unit" class="h-8 px-2" />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${lineIndex}.unit`
                                            ]
                                        "
                                    />
                                </td>

                                <!-- Unit price -->
                                <td class="w-28 px-2 py-2">
                                    <Input
                                        v-model="line.unit_price"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                </td>

                                <!-- Discount -->
                                <td class="w-24 px-2 py-2">
                                    <Input
                                        v-model="line.discount"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                </td>

                                <!-- Tax -->
                                <td class="w-24 px-2 py-2">
                                    <Input
                                        v-model="line.tax"
                                        inputmode="decimal"
                                        class="h-8 px-2 text-right tabular-nums"
                                    />
                                </td>

                                <!-- Total -->
                                <td
                                    class="w-28 px-2 py-2 pt-3 text-right font-medium whitespace-nowrap tabular-nums"
                                >
                                    {{ money(lineTotal(line)) }}
                                </td>

                                <!-- Remove -->
                                <td class="w-10 px-2 py-2 text-right">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        aria-label="Remove item"
                                        @click="removeItem(lineIndex)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <InputError :message="form.errors.items" />
                <InputError :message="form.errors.amount" />

                <!-- Totals sit right under the items -->
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
                                {{ form.currency }} {{ money(discountTotal) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">VAT/Tax</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(taxTotal) }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Client PO total</dt>
                            <dd class="tabular-nums">
                                {{ form.currency }} {{ money(totalAmount) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </CardContent>
        </Card>

        <!-- Notes -->
        <Card class="w-full min-w-0 gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Notes</CardTitle>
            </CardHeader>
            <CardContent class="px-4">
                <textarea
                    id="notes"
                    v-model="form.notes"
                    aria-label="Notes"
                    class="min-h-16 w-full min-w-0 resize-y rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </CardContent>
        </Card>

        <!-- Sticky action bar -->
        <div
            class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <p class="text-sm text-muted-foreground">
                Client PO total
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
                            ? 'Record client PO'
                            : 'Save client PO'
                    }}
                </Button>
            </div>
        </div>
    </form>
</template>