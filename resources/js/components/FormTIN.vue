<script setup lang="ts">
import { Input } from '@/components/ui/input';

const model = defineModel<string>({
    default: '',
});

const formatTIN = (event: Event) => {
    const input = event.target as HTMLInputElement;

    let value = input.value.replace(/\D/g, '').slice(0, 14);

    value = value
        .replace(/^(\d{3})(\d)/, '$1-$2')
        .replace(/^(\d{3})-(\d{3})(\d)/, '$1-$2-$3')
        .replace(/^(\d{3})-(\d{3})-(\d{3})(\d)/, '$1-$2-$3-$4');

    model.value = value;
};
</script>

<template>
    <Input
        id="tin"
        v-model="model"
        maxlength="17"
        inputmode="numeric"
        placeholder="123-456-789-000"
        @input="formatTIN"
    />
</template>
