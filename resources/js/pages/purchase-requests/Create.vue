<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/purchase-requests';
import Form from './Form.vue';

defineProps<{
    companies: [];
    vendors: [];
    products: [];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Purchase Requests', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Create purchase request" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Create purchase request"
            description="Request items before issuing a purchase order."
        />
        <Form
            mode="create"
            :companies="companies"
            :vendors="vendors"
            :products="products"
        />
    </div>
</template>
