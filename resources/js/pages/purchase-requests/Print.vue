<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Item = {
    id: number;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    tax: string;
    line_total: string;
};
type PurchaseRequest = {
    pr_no: string;
    request_date: string;
    date_required: string | null;
    client_project: string | null;
    requested_by_name: string | null;
    checked_by_name: string | null;
    noted_by_name: string | null;
    currency: string;
    subtotal: string;
    tax_amount: string;
    total_amount: string;
    company: { company_name: string; logo_url: string | null } | null;
    vendor: {
        vendor_name: string;
        address: string | null;
        phone: string | null;
    } | null;
    items: Item[];
};

const props = defineProps<{ purchaseRequest: PurchaseRequest }>();

const fillerRows = computed(() =>
    Math.max(0, 15 - props.purchaseRequest.items.length),
);
const money = (value: string) =>
    Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

setTimeout(() => window.print(), 500);
</script>

<template>
    <Head :title="`Print ${purchaseRequest.pr_no}`" />

    <main class="min-h-screen bg-neutral-200 p-6 print:bg-white print:p-0">
        <section
            class="mx-auto min-h-[277mm] w-[190mm] bg-white p-4 text-[10px] leading-tight text-black shadow print:min-h-0 print:w-auto print:p-0 print:shadow-none"
        >
            <div class="grid grid-cols-[1fr_34mm] items-start gap-2">
                <div class="border border-black">
                    <div
                        class="flex items-center gap-2 border-b border-black px-1"
                    >
                        <img
                            v-if="purchaseRequest.company?.logo_url"
                            :src="purchaseRequest.company.logo_url"
                            alt=""
                            class="h-7 w-24 object-contain"
                        />
                        <h1 class="text-[15px] font-extrabold text-[#0069b4]">
                            PURCHASED REQUEST FORM
                        </h1>
                    </div>
                    <table class="w-full table-fixed border-collapse">
                        <tbody>
                            <tr>
                                <td class="w-24 px-1 font-bold">VENDOR NAME</td>
                                <td class="w-2">:</td>
                                <td class="border-b border-black font-bold">
                                    {{
                                        purchaseRequest.vendor?.vendor_name ??
                                        '-'
                                    }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-1 font-bold">ADDRESS</td>
                                <td>:</td>
                                <td class="border-b border-black">
                                    {{ purchaseRequest.vendor?.address ?? '-' }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-1 font-bold">CONTACT NO</td>
                                <td>:</td>
                                <td class="border-b border-black">
                                    {{ purchaseRequest.vendor?.phone ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border border-black">
                    <div
                        class="border-b border-black p-2 py-1 text-left text-xs font-extrabold"
                    >
                        PR No :
                        <span class="block text-[#0069b4]">{{
                            purchaseRequest.pr_no
                        }}</span>
                    </div>
                    <table class="w-full text-[9px]">
                        <tbody>
                            <tr>
                                <td class="px-1 font-bold">
                                    Date Of Request :
                                </td>
                            </tr>
                            <tr>
                                <td
                                    class="border-b border-black px-1 text-right"
                                >
                                    {{ purchaseRequest.request_date }}
                                </td>
                            </tr>
                            <tr>
                                <td class="px-1 font-bold">Date Required :</td>
                            </tr>
                            <tr>
                                <td class="px-1 text-right">
                                    {{ purchaseRequest.date_required ?? '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <table class="mt-2 w-full table-fixed border-collapse text-[10px]">
                <tbody>
                    <tr>
                        <td class="w-24 font-bold">CLIENT / PROJECT</td>
                        <td class="w-2">:</td>
                        <td class="border-b border-black">
                            {{ purchaseRequest.client_project ?? '-' }}
                        </td>
                    </tr>
                    <tr>
                        <td class="font-bold">REQUESTED BY</td>
                        <td>:</td>
                        <td class="border-b border-black">
                            {{ purchaseRequest.requested_by_name ?? '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <table
                class="mt-2 w-full table-fixed border-collapse border border-black text-[9px]"
            >
                <colgroup>
                    <col style="width: 10%" />
                    <col style="width: 9%" />
                    <col style="width: 9%" />
                    <col style="width: 34%" />
                    <col style="width: 19%" />
                    <col style="width: 19%" />
                </colgroup>
                <thead>
                    <tr class="bg-[#f4b400] text-center font-bold">
                        <th class="border border-black">ITEM NO</th>
                        <th class="border border-black">QTY</th>
                        <th class="border border-black">UNITS</th>
                        <th class="border border-black">
                            ITEM TO BE PURCHASED
                        </th>
                        <th class="border border-black">UNIT PRICE</th>
                        <th class="border border-black">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, index) in purchaseRequest.items"
                        :key="item.id"
                        class="h-8 align-top"
                    >
                        <td class="border border-black text-center">
                            {{ String.fromCharCode(65 + index) }}
                        </td>
                        <td class="border border-black text-center">
                            {{ Number(item.quantity) }}
                        </td>
                        <td class="border border-black text-center">
                            {{ item.unit }}
                        </td>
                        <td class="border border-black px-1">
                            {{ item.description }}
                        </td>
                        <td class="border border-black px-1 text-right">
                            {{ money(item.unit_price) }}
                        </td>
                        <td
                            class="border border-black px-1 text-right font-bold"
                        >
                            {{ money(item.line_total) }}
                        </td>
                    </tr>
                    <tr
                        v-for="n in fillerRows"
                        :key="`filler-${n}`"
                        class="h-4"
                    >
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                        <td class="border border-black text-right">0.00</td>
                    </tr>
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black pr-1 text-right font-bold"
                        >
                            Total
                        </td>
                        <td class="border border-black pr-1 text-right">
                            {{ money(purchaseRequest.subtotal) }}
                        </td>
                    </tr>
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black pr-1 text-right font-bold"
                        >
                            VAT-INC
                        </td>
                        <td class="border border-black pr-1 text-right">
                            {{
                                Number(purchaseRequest.tax_amount) > 0
                                    ? money(purchaseRequest.tax_amount)
                                    : ''
                            }}
                        </td>
                    </tr>
                    <tr>
                        <td
                            colspan="5"
                            class="border border-black pr-1 text-right font-bold"
                        >
                            Grand Total
                        </td>
                        <td class="border border-black pr-1 text-right">
                            {{ money(purchaseRequest.total_amount) }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <p
                class="mt-5 border border-black bg-yellow-300 py-1 text-center text-[10px] font-bold text-red-600"
            >
                "PURCHASED REQUEST MUST BE APPROVED BEFORE A PURCHASE ORDER WILL
                BE ISSUE"
            </p>

            <table
                class="mt-3 w-full table-fixed border-collapse border border-black text-[9px]"
            >
                <tbody>
                    <tr class="h-7 align-top">
                        <td class="border border-black px-1 font-bold">
                            PREPARED BY :
                        </td>
                        <td class="border border-black px-1 font-bold">
                            CHECKED BY :
                        </td>
                        <td class="border border-black px-1 font-bold">
                            NOTED BY :
                        </td>
                    </tr>
                    <tr class="h-8 align-bottom">
                        <td class="border border-black px-1">
                            {{ purchaseRequest.requested_by_name }}
                        </td>
                        <td class="border border-black px-1">
                            {{ purchaseRequest.checked_by_name }}
                        </td>
                        <td class="border border-black px-1">
                            {{ purchaseRequest.noted_by_name }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </main>
</template>

<style>
@page {
    size: A4;
    margin: 10mm;
}

* {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
</style>
