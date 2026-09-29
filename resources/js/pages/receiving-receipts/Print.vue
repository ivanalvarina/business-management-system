<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

type Item = { id: number; quantity: string; description: string };
type Receipt = {
    rr_no: string;
    invoice_no: string | null;
    received_date: string;
    received_by_name: string | null;
    checked_by_name: string | null;
    company: { company_name: string; logo_url: string | null } | null;
    vendor: {
        vendor_code: string;
        vendor_name: string;
        address: string | null;
    } | null;
    purchase_order: { po_no: string } | null;
    items: Item[];
};

const props = defineProps<{ receivingReceipt: Receipt }>();

const fillerRows = computed(() =>
    Math.max(0, 17 - props.receivingReceipt.items.length),
);

setTimeout(() => window.print(), 500);
</script>

<template>
    <Head :title="`Print ${receivingReceipt.rr_no}`" />

    <main class="min-h-screen bg-neutral-200 p-6 print:bg-white print:p-0">
        <section
            class="mx-auto min-h-[277mm] w-[190mm] bg-white p-4 text-[10px] leading-tight text-black shadow print:min-h-0 print:w-auto print:p-0 print:shadow-none"
        >
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <img
                        v-if="receivingReceipt.company?.logo_url"
                        :src="receivingReceipt.company.logo_url"
                        alt=""
                        class="h-8 w-28 object-contain"
                    />
                    <h1 class="text-lg font-extrabold text-[#0070c0]">
                        (RR) RECEIVING RECEIPT
                    </h1>
                </div>
                <p class="text-sm font-extrabold">
                    RR NO :
                    <span class="text-[#0070c0]">{{
                        receivingReceipt.rr_no
                    }}</span>
                </p>
            </div>

            <div class="grid grid-cols-2 gap-x-2">
                <table class="w-full table-fixed text-[10px]">
                    <tbody>
                        <tr>
                            <td class="w-24">Supplier /From</td>
                            <td class="border-b border-black">
                                {{ receivingReceipt.vendor?.vendor_name }}
                            </td>
                        </tr>
                        <tr>
                            <td>PR No.</td>
                            <td class="border-b border-black">
                                {{ receivingReceipt.purchase_order?.po_no }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <table class="w-full table-fixed text-[10px]">
                    <tbody>
                        <tr>
                            <td class="w-24">Invoice / DR</td>
                            <td class="border-b border-black">
                                {{ receivingReceipt.invoice_no ?? '-' }}
                            </td>
                        </tr>
                        <tr>
                            <td>Date</td>
                            <td class="border-b border-black text-right">
                                {{ receivingReceipt.received_date }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <table
                class="mt-4 w-full table-fixed border-collapse border border-black text-[10px]"
            >
                <colgroup>
                    <col style="width: 22%" />
                    <col style="width: 78%" />
                </colgroup>
                <thead>
                    <tr class="text-center font-bold">
                        <th class="border border-black">QUANTITY</th>
                        <th class="border border-black">PARTICULARS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in receivingReceipt.items"
                        :key="item.id"
                        class="h-9"
                    >
                        <td class="border border-black text-center">
                            {{ Number(item.quantity) }}
                        </td>
                        <td class="border border-black px-1">
                            {{ item.description }}
                        </td>
                    </tr>
                    <tr
                        v-for="n in fillerRows"
                        :key="`filler-${n}`"
                        class="h-5"
                    >
                        <td class="border border-black"></td>
                        <td class="border border-black"></td>
                    </tr>
                </tbody>
            </table>

            <div class="grid grid-cols-2 border-b-2 border-black text-[10px]">
                <p>Received by : {{ receivingReceipt.received_by_name }}</p>
                <p>Checked by : {{ receivingReceipt.checked_by_name }}</p>
            </div>
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
