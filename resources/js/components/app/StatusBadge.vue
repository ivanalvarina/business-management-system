<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = withDefaults(
    defineProps<{
        tone?: 'neutral' | 'success' | 'warning' | 'info';
    }>(),
    {
        tone: 'neutral',
    },
);

const toneClasses = computed(() => {
    return {
        neutral: 'border-border bg-muted text-muted-foreground',
        success:
            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/70 dark:bg-emerald-950/30 dark:text-emerald-300',
        warning:
            'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-300',
        info: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/70 dark:bg-sky-950/30 dark:text-sky-300',
    }[props.tone];
});
</script>

<template>
    <Badge variant="outline" :class="toneClasses">
        <slot />
    </Badge>
</template>
