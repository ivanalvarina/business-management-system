<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description?: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="flex min-w-0 items-start gap-3">
            <div
                v-if="icon"
                class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-lg border bg-card text-muted-foreground"
            >
                <component :is="icon" class="size-5" />
            </div>
            <div class="min-w-0">
                <h1
                    class="text-2xl font-semibold tracking-normal text-foreground"
                >
                    {{ title }}
                </h1>
                <p
                    v-if="description"
                    class="mt-1 max-w-3xl text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
