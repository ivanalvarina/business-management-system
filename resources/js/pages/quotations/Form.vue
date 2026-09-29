<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import QuotationController from '@/actions/App/Http/Controllers/QuotationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/quotations';

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

type ProductOption = {
    id: number;
    code: string;
    type: string;
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
};

type QuotationLine = {
    product_service_id: number | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
};

type QuotationFormData = {
    company_id: number | null;
    client_id: number | null;
    quotation_date: string;
    valid_until: string;
    currency: string;
    notes: string;
    terms_conditions: string;
    items: QuotationLine[];
};

type ExistingQuotation = Omit<
    Partial<QuotationFormData>,
    'valid_until' | 'notes' | 'terms_conditions'
> & {
    id: number;
    quotation_no: string;
    valid_until?: string | null;
    notes?: string | null;
    terms_conditions?: string | null;
    items: QuotationLine[];
};

const props = defineProps<{
    mode: 'create' | 'edit';
    companies: CompanyOption[];
    clients: ClientOption[];
    products: ProductOption[];
    quotation?: ExistingQuotation;
}>();

const blankLine = (): QuotationLine => ({
    product_service_id: null,
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount: '0.00',
    tax: '0.00',
});

// Local YYYY-MM-DD (toISOString() is UTC and can be a day behind in PH mornings).
const toDateString = (date: Date) => {
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const today = toDateString(new Date());

const form = useForm<QuotationFormData>({
    company_id: props.quotation?.company_id ?? props.companies[0]?.id ?? null,
    client_id: props.quotation?.client_id ?? null,
    quotation_date: props.quotation?.quotation_date ?? today,
    valid_until: props.quotation?.valid_until ?? '',
    currency: props.quotation?.currency ?? 'PHP',
    notes: props.quotation?.notes ?? '',
    terms_conditions: props.quotation?.terms_conditions ?? '',
    items: props.quotation?.items?.length
        ? props.quotation.items
        : [blankLine()],
});

const filteredClients = computed(() =>
    props.clients.filter(
        (client) =>
            form.company_id !== null &&
            client.company_ids.includes(form.company_id),
    ),
);

// Quick picks for "Valid until" (UI only; the saved value is still a date).
const validityOptions = [7, 15, 30];

const setValidDays = (days: number) => {
    const base = new Date(`${form.quotation_date || today}T00:00:00`);
    base.setDate(base.getDate() + days);
    form.valid_until = toDateString(base);
};

const activeValidity = computed(() => {
    if (!form.valid_until || !form.quotation_date) {
        return null;
    }

    const diff = Math.round(
        (new Date(`${form.valid_until}T00:00:00`).getTime() -
            new Date(`${form.quotation_date}T00:00:00`).getTime()) /
            86_400_000,
    );

    return validityOptions.includes(diff) ? diff : null;
});

const money = (value: number) =>
    value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const asNumber = (value: string | number | null | undefined) => {
    const number = Number(value ?? 0);

    return Number.isFinite(number) ? number : 0;
};

const lineSubtotal = (line: QuotationLine) =>
    asNumber(line.quantity) * asNumber(line.unit_price);

const lineTotal = (line: QuotationLine) =>
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

const applyProduct = (line: QuotationLine) => {
    const product = selectedProduct(line.product_service_id);

    if (!product) {
        return;
    }

    line.description = product.description || product.name;
    line.unit = product.unit;
    line.unit_price = product.default_price;
};

const onCompanyChange = () => {
    if (
        form.client_id !== null &&
        !filteredClients.value.some((client) => client.id === form.client_id)
    ) {
        form.client_id = null;
    }
};

const submit = () => {
    if (props.mode === 'create') {
        form.post(QuotationController.store.url());

        return;
    }

    if (props.quotation) {
        form.put(QuotationController.update.url(props.quotation.id));
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
                        <Label for="client_id">Client</Label>
                        <select
                            id="client_id"
                            v-model="form.client_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                        <Label for="quotation_date">Quotation date</Label>
                        <Input
                            id="quotation_date"
                            v-model="form.quotation_date"
                            type="date"
                        />
                        <InputError :message="form.errors.quotation_date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="valid_until">Valid until</Label>
                        <Input
                            id="valid_until"
                            v-model="form.valid_until"
                            type="date"
                        />
                        <div class="flex gap-1.5">
                            <button
                                v-for="days in validityOptions"
                                :key="days"
                                type="button"
                                class="rounded-full border px-2.5 py-0.5 text-xs transition-colors"
                                :class="
                                    activeValidity === days
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                                "
                                @click="setValidDays(days)"
                            >
                                {{ days }} days
                            </button>
                        </div>
                        <InputError :message="form.errors.valid_until" />
                    </div>
                </div>
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
                                    <InputError
                                        :message="
                                            error(
                                                `items.${lineIndex}.product_service_id`,
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
                            ? 'Create quotation'
                            : 'Save quotation'
                    }}
                </Button>
            </div>
        </div>
    </form>
</template>