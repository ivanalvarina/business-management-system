<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/quotations';
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

defineProps<{
    companies: CompanyOption[];
    clients: ClientOption[];
    products: ProductOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Quotations', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Create quotation" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Create quotation"
            description="Prepare a sales quotation with item snapshots and server-validated totals."
        />

        <QuotationForm
            mode="create"
            :companies="companies"
            :clients="clients"
            :products="products"
        />
    </div>
</template>
