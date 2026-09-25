<script setup lang="ts">
import type { Component } from 'vue';
import { Inbox } from '@lucide/vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
    }>(),
    {
        icon: Inbox,
    },
);
</script>

<template>
    <div
        class="flex min-h-48 flex-col items-center justify-center rounded-lg border border-dashed bg-muted/20 p-8 text-center"
    >
        <div
            class="flex size-11 items-center justify-center rounded-lg border bg-background text-muted-foreground"
        >
            <component :is="icon" class="size-5" />
        </div>
        <h2 class="mt-4 text-sm font-medium text-foreground">
            {{ title }}
        </h2>
        <p
            v-if="description"
            class="mt-1 max-w-md text-sm text-muted-foreground"
        >
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
