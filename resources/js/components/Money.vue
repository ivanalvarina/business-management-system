<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    amount: string | number;
    currency?: string;
}>();

const currencySymbol = computed(() => {
    const symbols: Record<string, string> = {
        PHP: '₱',
        USD: '$',
        EUR: '€',
        GBP: '£',
        JPY: '¥',
    };

    return symbols[props.currency ?? 'PHP'] ?? props.currency;
});

const formattedAmount = computed(() => {
    return Number(props.amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
});
</script>

<template>
    <span>{{ currencySymbol }}{{ formattedAmount }}</span>
</template>
