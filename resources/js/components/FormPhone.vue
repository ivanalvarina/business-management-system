<script setup lang="ts">
import { Input } from '@/components/ui/input';

const model = defineModel<string>({
    default: '',
});

const formatPhone = (event: Event) => {
    const input = event.target as HTMLInputElement;

    let value = input.value.replace(/\D/g, '');

    // Remove 63 prefix if present
    if (value.startsWith('63')) {
        value = value.slice(2);
    }

    // Remove leading 0
    if (value.startsWith('0')) {
        value = value.slice(1);
    }

    // Maximum 10 digits
    value = value.slice(0, 10);

    // Format: XXX XXX XXXX
    value = value
        .replace(/^(\d{3})(\d)/, '$1 $2')
        .replace(/^(\d{3}) (\d{3})(\d)/, '$1 $2 $3');

    model.value = value ? `+63 ${value}` : '';
};
</script>

<template>
    <Input
        id="phone"
        v-model="model"
        maxlength="16"
        inputmode="tel"
        placeholder="+63 946 639 4191"
        @input="formatPhone"
    />
</template>
