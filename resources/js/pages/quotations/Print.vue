<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/quotations';

type QuotationItem = {
    id: number;
    product_service: { code: string; name: string } | null;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount: string;
    tax: string;
    line_total: string;
};

type Quotation = {
    id: number;
    quotation_no: string;
    quotation_date: string;
    valid_until: string | null;
    currency: string;
    notes: string | null;
    terms_conditions: string | null;
    subtotal: string;
    discount: string;
    tax_amount: string;
    total_amount: string;
    company: {
        company_name: string;
        tin: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    client: {
        client_name: string;
        tin: string | null;
        email: string | null;
        phone: string | null;
        billing_address: string | null;
        shipping_address: string | null;
    } | null;
    items: QuotationItem[];
};

defineProps<{
    quotation: Quotation;
}>();

const printPage = () => window.print();
</script>

<template>
    <Head :title="`Print ${quotation.quotation_no}`" />

    <div
        class="min-h-screen bg-muted/40 p-4 text-foreground print:bg-white print:p-0"
    >
        <div
            class="mx-auto max-w-5xl bg-white p-8 shadow-sm print:max-w-none print:p-0 print:shadow-none"
        >
            <div class="mb-6 flex justify-between gap-3 print:hidden">
                <Button variant="outline" as-child>
                    <Link :href="show(quotation.id)">Back</Link>
                </Button>
                <Button @click="printPage">
                    <Printer />
                    Print
                </Button>
            </div>

            <header class="border-b pb-6">
                <div
                    class="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h1 class="text-2xl font-semibold">Quotation</h1>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ quotation.quotation_no }}
                        </p>
                    </div>
                    <div class="text-sm sm:text-right">
                        <p class="font-semibold">
                            {{ quotation.company?.company_name }}
                        </p>
                        <p v-if="quotation.company?.tin">
                            TIN: {{ quotation.company.tin }}
                        </p>
                        <p v-if="quotation.company?.email">
                            {{ quotation.company.email }}
                        </p>
                        <p v-if="quotation.company?.phone">
                            {{ quotation.company.phone }}
                        </p>
                        <p v-if="quotation.company?.address">
                            {{ quotation.company.address }}
                        </p>
                    </div>
                </div>
            </header>

            <section class="grid gap-6 border-b py-6 sm:grid-cols-2">
                <div>
                    <p
                        class="text-xs font-medium text-muted-foreground uppercase"
                    >
                        Bill to
                    </p>
                    <p class="mt-2 font-semibold">
                        {{ quotation.client?.client_name }}
                    </p>
                    <p v-if="quotation.client?.tin" class="text-sm">
                        TIN: {{ quotation.client.tin }}
                    </p>
                    <p v-if="quotation.client?.email" class="text-sm">
                        {{ quotation.client.email }}
                    </p>
                    <p v-if="quotation.client?.phone" class="text-sm">
                        {{ quotation.client.phone }}
                    </p>
                    <p v-if="quotation.client?.billing_address" class="text-sm">
                        {{ quotation.client.billing_address }}
                    </p>
                </div>
                <div class="grid gap-2 text-sm sm:text-right">
                    <div>
                        <span class="text-muted-foreground"
                            >Quotation date</span
                        >
                        <p class="font-medium">
                            {{ quotation.quotation_date }}
                        </p>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Valid until</span>
                        <p class="font-medium">
                            {{ quotation.valid_until ?? 'Not set' }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="py-6">
                <table class="w-full text-sm">
                    <thead class="border-b text-left text-muted-foreground">
                        <tr>
                            <th class="py-3 pr-3 font-medium">Description</th>
                            <th class="px-3 py-3 text-right font-medium">
                                Qty
                            </th>
                            <th class="px-3 py-3 font-medium">Unit</th>
                            <th class="px-3 py-3 text-right font-medium">
                                Unit price
                            </th>
                            <th class="px-3 py-3 text-right font-medium">
                                Discount
                            </th>
                            <th class="px-3 py-3 text-right font-medium">
                                Tax
                            </th>
                            <th class="py-3 pl-3 text-right font-medium">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in quotation.items" :key="item.id">
                            <td class="py-3 pr-3">
                                <p>{{ item.description }}</p>
                                <p
                                    v-if="item.product_service"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ item.product_service.code }} -
                                    {{ item.product_service.name }}
                                </p>
                            </td>
                            <td class="px-3 py-3 text-right">
                                {{ item.quantity }}
                            </td>
                            <td class="px-3 py-3">{{ item.unit }}</td>
                            <td class="px-3 py-3 text-right">
                                {{ item.unit_price }}
                            </td>
                            <td class="px-3 py-3 text-right">
                                {{ item.discount }}
                            </td>
                            <td class="px-3 py-3 text-right">{{ item.tax }}</td>
                            <td class="py-3 pl-3 text-right font-medium">
                                {{ item.line_total }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="grid gap-6 border-t pt-6 sm:grid-cols-[1fr_320px]">
                <div class="space-y-4 text-sm">
                    <div v-if="quotation.notes">
                        <p class="font-medium">Notes</p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ quotation.notes }}
                        </p>
                    </div>
                    <div v-if="quotation.terms_conditions">
                        <p class="font-medium">Terms & conditions</p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ quotation.terms_conditions }}
                        </p>
                    </div>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Subtotal</span>
                        <span
                            >{{ quotation.currency }}
                            {{ quotation.subtotal }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Discount</span>
                        <span
                            >{{ quotation.currency }}
                            {{ quotation.discount }}</span
                        >
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">VAT/Tax</span>
                        <span
                            >{{ quotation.currency }}
                            {{ quotation.tax_amount }}</span
                        >
                    </div>
                    <div
                        class="flex justify-between border-t pt-3 text-lg font-semibold"
                    >
                        <span>Total</span>
                        <span>
                            {{ quotation.currency }}
                            {{ quotation.total_amount }}
                        </span>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
