<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/receiving-receipts';
import Form from './Form.vue';

defineProps<{ purchaseOrders: [] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Receiving Receipts', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Record receiving receipt" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Record receiving receipt"
            description="Record items received against an approved purchase order."
        />
        <Form mode="create" :purchase-orders="purchaseOrders" />
    </div>
</template>
