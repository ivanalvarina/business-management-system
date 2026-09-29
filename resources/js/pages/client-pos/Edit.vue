<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/client-pos';
import ClientPurchaseOrderForm from './Form.vue';

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

defineProps<{
    clientPurchaseOrder: ClientPurchaseOrder;
    companies: CompanyOption[];
    clients: ClientOption[];
    quotations: QuotationOption[];
    productServices: ProductServiceOption[];
    statuses: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Client Purchase Orders', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${clientPurchaseOrder.client_po_no}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${clientPurchaseOrder.client_po_no}`"
            description="Update the received client purchase order record."
        />

        <ClientPurchaseOrderForm
            mode="edit"
            :client-purchase-order="clientPurchaseOrder"
            :companies="companies"
            :clients="clients"
            :quotations="quotations"
            :product-services="productServices"
            :statuses="statuses"
        />
    </div>
</template>
