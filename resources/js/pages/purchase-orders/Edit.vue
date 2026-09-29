<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/purchase-orders';
import PurchaseOrderForm from './Form.vue';

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

type PurchaseOrder = {
    id: number;
    po_no: string;
    company_id: number;
    vendor_id: number;
    purchase_request_ids: number[];
    po_date: string;
    expected_delivery: string | null;
    currency: string;
    notes: string | null;
    terms_conditions: string | null;
    items: PurchaseOrderLine[];
};

const props = defineProps<{
    purchaseOrder: PurchaseOrder;
    companies: CompanyOption[];
    vendors: VendorOption[];
    products: ProductOption[];
    purchaseRequests: PurchaseRequestOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase Orders', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${purchaseOrder.po_no}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${purchaseOrder.po_no}`"
            description="Revise a draft purchase order and refresh its item totals."
        />

        <PurchaseOrderForm
            mode="edit"
            :purchase-order="props.purchaseOrder"
            :companies="companies"
            :vendors="vendors"
            :products="products"
            :purchase-requests="purchaseRequests"
        />
    </div>
</template>
