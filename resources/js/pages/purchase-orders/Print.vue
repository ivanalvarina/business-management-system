<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, Phone, Printer } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/purchase-orders';

type PurchaseOrderItem = {
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

type Signatory = { name: string; title: string | null };

type PurchaseOrder = {
    id: number;
    po_no: string;
    po_date: string;
    expected_delivery: string | null;
    currency: string;
    notes: string | null;
    terms_conditions: string | null;
    subtotal: string;
    discount: string;
    tax_amount: string;
    total_amount: string;
    // Optional: shown when your backend sends them, "-" / hidden otherwise.
    shipping_method?: string | null;
    payment_terms?: string | null;
    ship_to?: string | null;
    prepared_by?: Signatory | null;
    noted_by?: Signatory | null;
    purchase_requests?: { id: number; pr_no: string }[];
    company: {
        company_name: string;
        logo_url?: string | null;
        purchasing_assistant_name?: string | null;
        corporate_sales_manager_name?: string | null;
        tin: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    vendor: {
        vendor_name: string;
        tin: string | null;
        email: string | null;
        phone: string | null;
        address: string | null;
    } | null;
    items: PurchaseOrderItem[];
};

const props = defineProps<{
    purchaseOrder: PurchaseOrder;
}>();

// Keep the print sheet close to the reference form height without spilling.
const MIN_ROWS = 13;

const fillerRows = computed(() =>
    Math.max(0, MIN_ROWS - props.purchaseOrder.items.length),
);

const money = (value: string | number) => {
    const number = Number(value);

    return Number.isNaN(number)
        ? String(value)
        : number.toLocaleString('en-US', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};

const qty = (value: string) => {
    const number = Number(value);

    return Number.isNaN(number) ? value : String(number);
};

const hasAmount = (value: string) => Number(value) > 0;

// Only mention the currency when it isn't PHP (the reference shows none).
const currencyNote = computed(() =>
    props.purchaseOrder.currency && props.purchaseOrder.currency !== 'PHP'
        ? ` (${props.purchaseOrder.currency})`
        : '',
);

const shipTo = computed(
    () =>
        props.purchaseOrder.ship_to ??
        props.purchaseOrder.company?.address ??
        '-',
);

const printPage = () => window.print();
</script>

<template>
    <Head :title="`Print ${purchaseOrder.po_no}`" />

    <div
        class="min-h-screen bg-muted/40 p-4 text-foreground print:bg-white print:p-0"
    >
        <div
            class="po-sheet mx-auto w-full max-w-[210mm] bg-white p-7 text-[10.5px] leading-tight text-black shadow-sm print:max-w-none print:p-0 print:shadow-none"
            style="font-family: Arial, Helvetica, sans-serif"
        >
            <!-- Screen-only toolbar -->
            <div class="mb-6 flex justify-between gap-3 print:hidden">
                <Button variant="outline" as-child>
                    <Link :href="show(props.purchaseOrder.id)">Back</Link>
                </Button>
                <Button @click="printPage">
                    <Printer />
                    Print
                </Button>
            </div>

            <!-- Logo + orange rule -->
            <header class="border-b-[3px] border-[#f79646] pb-1.5 pl-4">
                <img
                    v-if="purchaseOrder.company?.logo_url"
                    :src="purchaseOrder.company.logo_url"
                    :alt="purchaseOrder.company.company_name"
                    class="h-16 w-auto max-w-[92mm] object-contain"
                />
                <p v-else class="text-lg font-bold">
                    {{ purchaseOrder.company?.company_name }}
                </p>
            </header>

            <!-- Title bar + PO info -->
            <table class="print-grid mt-2 w-full table-fixed border-collapse">
                <colgroup>
                    <col style="width: 22%" />
                    <col style="width: 38%" />
                    <col style="width: 16%" />
                    <col style="width: 24%" />
                </colgroup>
                <thead>
                    <tr>
                        <th
                            colspan="4"
                            class="border border-black bg-[#ffc000] py-0.5 text-center text-base font-extrabold tracking-wide text-[#e00000]"
                        >
                            PURCHASE ORDER
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-black px-1 py-px">
                            Purchase Order No.
                        </td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.po_no }}
                        </td>
                        <td class="border border-black px-1 py-px">
                            Shipping Method:
                        </td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.shipping_method ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="border border-black px-1 py-px">
                            Purchase Order Date:
                        </td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.po_date }}
                        </td>
                        <td class="border border-black px-1 py-px">
                            Shipping Date:
                        </td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.expected_delivery ?? 'TBA' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="border border-black px-1 py-px">
                            Issued By:
                        </td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.company?.company_name }}
                        </td>
                        <td class="border border-black px-1 py-px">Terms:</td>
                        <td class="border border-black px-1 py-px font-bold">
                            {{ purchaseOrder.payment_terms ?? '-' }}
                        </td>
                    </tr>
                    <tr v-if="purchaseOrder.purchase_requests?.length">
                        <td class="border border-black px-1 py-px">
                            Purchase Request No.
                        </td>
                        <td
                            colspan="3"
                            class="border border-black px-1 py-px font-bold"
                        >
                            {{
                                purchaseOrder.purchase_requests
                                    .map(
                                        (purchaseRequest) =>
                                            purchaseRequest.pr_no,
                                    )
                                    .join(', ')
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Vendor / ship to -->
            <dl
                class="mt-3 grid grid-cols-[80px_1fr] gap-x-2 gap-y-0.5 px-1 pb-2"
            >
                <dt>Vendor Name:</dt>
                <dd class="font-bold">
                    {{ purchaseOrder.vendor?.vendor_name ?? '-' }}
                </dd>
                <dt>Address:</dt>
                <dd class="font-bold">
                    {{ purchaseOrder.vendor?.address ?? '-' }}
                </dd>
                <dt>Ship To:</dt>
                <dd class="font-bold">{{ shipTo }}</dd>
            </dl>

            <!-- Items -->
            <table
                class="print-grid w-full table-fixed border-collapse border border-black"
            >
                <colgroup>
                    <col style="width: 8%" />
                    <col style="width: 7%" />
                    <col style="width: 8%" />
                    <col style="width: 45%" />
                    <col style="width: 16%" />
                    <col style="width: 16%" />
                </colgroup>
                <thead>
                    <tr
                        class="bg-[#ffc000] text-center text-[10px] font-bold text-[#8a1c1c]"
                    >
                        <th class="border border-black py-0.5">ITEM</th>
                        <th class="border border-black py-0.5">QTY</th>
                        <th class="border border-black py-0.5">UNIT</th>
                        <th class="border border-black py-0.5">PARTICULARS</th>
                        <th class="border border-black py-0.5">
                            UNIT PRICE{{ currencyNote }}
                        </th>
                        <th class="border border-black py-0.5">
                            AMOUNT{{ currencyNote }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in purchaseOrder.items"
                        :key="item.id"
                        class="[break-inside:avoid]"
                    >
                        <td class="border border-black px-1 py-1 text-center">
                            {{ String.fromCharCode(65 + (index % 26)) }}
                        </td>
                        <td class="border border-black px-1 py-1 text-center">
                            {{ qty(item.quantity) }}
                        </td>
                        <td class="border border-black px-1 py-1 text-center">
                            {{ item.unit }}
                        </td>
                        <td
                            class="border border-black px-1 py-1 text-[10px] whitespace-pre-line"
                        >
                            {{
                                item.description ||
                                item.product_service?.name ||
                                ''
                            }}
                        </td>
                        <td
                            class="border border-black px-2 py-1 text-right text-xs tabular-nums"
                        >
                            {{ money(item.unit_price) }}
                        </td>
                        <td
                            class="border border-black px-2 py-1 text-right font-bold tabular-nums"
                        >
                            {{ money(item.line_total) }}
                        </td>
                    </tr>

                    <!-- Filler rows so every sheet looks the same -->
                    <tr
                        v-for="n in fillerRows"
                        :key="`filler-${n}`"
                        class="h-4"
                    >
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black px-2 text-right">-</td>
                        <td class="border border-black"></td>
                    </tr>
                </tbody>
                <tfoot class="[break-inside:avoid]">
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black px-2 py-px text-right font-bold"
                        >
                            Total
                        </td>
                        <td
                            class="border border-black px-2 py-px text-right font-bold text-[#e00000] tabular-nums"
                        >
                            {{ money(purchaseOrder.subtotal) }}
                        </td>
                    </tr>
                    <tr v-if="hasAmount(purchaseOrder.discount)">
                        <td
                            colspan="5"
                            class="border border-black px-2 py-px text-right font-bold"
                        >
                            Discount
                        </td>
                        <td
                            class="border border-black px-2 py-px text-right font-bold tabular-nums"
                        >
                            {{ money(purchaseOrder.discount) }}
                        </td>
                    </tr>
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black px-2 py-px text-right font-bold"
                        >
                            VAT-INC
                        </td>
                        <td
                            class="border border-black px-2 py-px text-right font-bold tabular-nums"
                        >
                            {{
                                hasAmount(purchaseOrder.tax_amount)
                                    ? money(purchaseOrder.tax_amount)
                                    : ''
                            }}
                        </td>
                    </tr>
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black px-2 py-px text-right font-bold"
                        >
                            Grand Total
                        </td>
                        <td
                            class="border border-black px-2 py-px text-right font-bold text-[#e00000] tabular-nums"
                        >
                            {{ money(purchaseOrder.total_amount) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Signatories -->
            <section class="mt-2 [break-inside:avoid] text-[9.5px]">
                <div class="grid grid-cols-[62%_38%] font-bold">
                    <p>Prepared By:</p>
                    <p>Noted By:</p>
                </div>

                <div class="mt-8 grid grid-cols-[62%_38%]">
                    <div>
                        <p class="min-h-3 font-bold">
                            {{ purchaseOrder.prepared_by?.name }}
                        </p>
                        <p>{{ purchaseOrder.prepared_by?.title }}</p>
                        <p class="font-bold text-[#e00000] uppercase">
                            {{ purchaseOrder.company?.company_name }}
                        </p>
                    </div>
                    <div>
                        <p class="min-h-3 font-bold">
                            {{ purchaseOrder.noted_by?.name }}
                        </p>
                        <p>{{ purchaseOrder.noted_by?.title }}</p>
                        <p class="font-bold text-[#e00000] uppercase">
                            {{ purchaseOrder.company?.company_name }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Contact footer -->
            <footer
                v-if="
                    purchaseOrder.company?.email || purchaseOrder.company?.phone
                "
                class="mt-4 border-t-[3px] border-[#f79646] pt-1 text-[10px]"
            >
                <p>Contact Us To Know More &gt;&gt;</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-8 gap-y-1">
                    <span
                        v-if="purchaseOrder.company?.email"
                        class="flex items-center gap-1.5"
                    >
                        <Mail class="size-3.5" />
                        {{ purchaseOrder.company.email }}
                    </span>
                    <span
                        v-if="purchaseOrder.company?.phone"
                        class="flex items-center gap-1.5"
                    >
                        <Phone class="size-3.5" />
                        {{ purchaseOrder.company.phone }}
                    </span>
                </div>
            </footer>
        </div>
    </div>
</template>

<style>
@page {
    size: A4;
    margin: 10mm;
}

/* Keep the amber fills and red text when printing / saving as PDF */
.po-sheet,
.po-sheet * {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}

.print-grid {
    box-sizing: border-box;
    outline: 1px solid #000;
    outline-offset: -1px;
}

@media print {
    .print-grid {
        width: calc(100% - 0.5mm) !important;
    }
}
</style>
