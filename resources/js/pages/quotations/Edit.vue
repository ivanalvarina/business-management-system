<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index, show } from '@/routes/quotations';
import QuotationForm from './Form.vue';

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

type Quotation = {
    id: number;
    quotation_no: string;
    company_id: number;
    client_id: number;
    quotation_date: string;
    valid_until: string | null;
    currency: string;
    notes: string | null;
    terms_conditions: string | null;
    items: QuotationLine[];
};

const props = defineProps<{
    quotation: Quotation;
    companies: CompanyOption[];
    clients: ClientOption[];
    products: ProductOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${quotation.quotation_no}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${quotation.quotation_no}`"
            description="Revise a draft quotation and refresh its item totals."
        />

        <QuotationForm
            mode="edit"
            :quotation="props.quotation"
            :companies="companies"
            :clients="clients"
            :products="products"
        />
    </div>
</template>
