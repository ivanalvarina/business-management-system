<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/purchase-requests';
import Form from './Form.vue';

defineProps<{
    purchaseRequest: {
        id: number;
        pr_no: string;
        items: [];
    } & Record<string, unknown>;
    companies: [];
    vendors: [];
    products: [];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase Requests', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit purchase request" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${purchaseRequest.pr_no}`"
            description="Update purchase request details."
        />
        <Form
            mode="edit"
            :purchase-request="purchaseRequest"
            :companies="companies"
            :vendors="vendors"
            :products="products"
        />
    </div>
</template>
