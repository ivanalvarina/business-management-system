<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProductServiceController from '@/actions/App/Http/Controllers/ProductServiceController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/product-services';

type Item = {
    id: number;
    code: string;
    type: 'product' | 'service';
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
    status: 'active' | 'inactive';
    created_at: string | null;
};

const props = defineProps<{ item: Item; can: { edit: boolean } }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products & Services', href: index() },
            { title: 'Item details', href: '#' },
        ],
    },
});

const toggleStatus = () => {
    router.visit(
        props.item.status === 'active'
            ? ProductServiceController.deactivate(props.item.id)
            : ProductServiceController.activate(props.item.id),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="item.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="item.name" :description="item.code">
            <template #actions>
                <Button v-if="can.edit" variant="outline" @click="toggleStatus">
                    {{ item.status === 'active' ? 'Deactivate' : 'Activate' }}
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(item.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <CardTitle>Catalog details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <StatusBadge
                        :tone="item.status === 'active' ? 'success' : 'warning'"
                    >
                        {{ item.status === 'active' ? 'Active' : 'Inactive' }}
                    </StatusBadge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Type</p>
                    <p class="font-medium">
                        {{ item.type === 'product' ? 'Product' : 'Service' }}
                    </p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Unit</p>
                    <p class="font-medium">{{ item.unit }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Default price</p>
                    <p class="font-medium">{{ item.default_price }}</p>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Created</p>
                    <p class="font-medium">
                        {{ item.created_at ?? 'Unknown' }}
                    </p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-muted-foreground">Description</p>
                    <p class="font-medium whitespace-pre-line">
                        {{ item.description ?? 'Not set' }}
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
