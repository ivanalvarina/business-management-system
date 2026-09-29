<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ActivityLog = {
    id: number;
    module: string;
    action: string;
    created_at: string | null;
    user: { id: number; name: string } | null;
};

defineProps<{
    logs: ActivityLog[];
}>();

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Activity history</CardTitle>
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-for="log in logs"
                :key="log.id"
                class="rounded-lg border p-3 text-sm"
            >
                <div
                    class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="font-medium">{{ label(log.action) }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ log.created_at ?? 'Unknown time' }}
                    </p>
                </div>
                <p class="mt-1 text-muted-foreground">
                    {{ log.user?.name ?? 'System' }} in {{ label(log.module) }}
                </p>
            </div>
            <p v-if="logs.length === 0" class="text-sm text-muted-foreground">
                No activity recorded yet.
            </p>
        </CardContent>
    </Card>
</template>
