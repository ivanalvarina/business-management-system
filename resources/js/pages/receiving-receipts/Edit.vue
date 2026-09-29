<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/receiving-receipts';
import Form from './Form.vue';

defineProps<{
    receivingReceipt: {
        id: number;
        rr_no: string;
        items: [];
    } & Record<string, unknown>;
    purchaseOrders: [];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Receiving Receipts', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Edit receiving receipt" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${receivingReceipt.rr_no}`"
            description="Update received item details."
        />
        <Form
            mode="edit"
            :receiving-receipt="receivingReceipt"
            :purchase-orders="purchaseOrders"
        />
    </div>
</template>
