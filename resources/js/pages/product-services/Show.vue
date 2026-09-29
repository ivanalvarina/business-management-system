<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ProductServiceController from '@/actions/App/Http/Controllers/ProductServiceController';
import ActivityHistoryPanel from '@/components/app/ActivityHistoryPanel.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/product-services';
import { computed, ref, watch } from 'vue';

type Item = {
    id: number;
    code: string;
    type: 'product' | 'service';
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
    quantity: number;
    status: 'active' | 'inactive';
    is_public: boolean;
    public_url: string | null;
    primary_image: ProductImage | null;
    images: ProductImage[];
    inventory_movements: InventoryMovement[];
    created_at: string | null;
};

type ProductImage = {
    id: number;
    url: string;
    original_filename: string | null;
    is_primary: boolean;
};

type InventoryMovement = {
    id: number;
    type: string;
    quantity: string;
    quantity_before: number;
    quantity_after: number;
    reference: string | null;
    created_at: string | null;
};

type ActivityLog = {
    id: number;
    module: string;
    action: string;
    created_at: string | null;
    user: { id: number; name: string } | null;
};

const props = defineProps<{
    item: Item;
    activityLogs: ActivityLog[];
    can: { edit: boolean };
}>();

const selectedImage = ref<ProductImage | null>(
    props.item.primary_image ?? props.item.images[0] ?? null,
);
const qrUrl = computed(() =>
    props.item.public_url
        ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(props.item.public_url)}`
        : null,
);

watch(
    () => props.item.primary_image,
    (image) => {
        selectedImage.value = image ?? props.item.images[0] ?? null;
    },
);

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

const printQr = () => window.print();
</script>

<template>
    <Head :title="item.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="item.name" :description="item.code">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="index()"> Back </Link>
                </Button>
                <Button v-if="can.edit" variant="outline" @click="toggleStatus">
                    {{ item.status === 'active' ? 'Deactivate' : 'Activate' }}
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(item.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card v-if="item.type === 'product'">
            <CardContent
                class="grid gap-6 pt-6 lg:grid-cols-[minmax(0,1fr)_280px]"
            >
                <div class="space-y-3">
                    <div
                        class="flex aspect-video items-center justify-center overflow-hidden rounded-lg border bg-muted/30"
                    >
                        <img
                            v-if="selectedImage"
                            :src="selectedImage.url"
                            :alt="selectedImage.original_filename ?? item.name"
                            class="h-full w-full object-contain"
                        />
                        <span v-else class="text-sm text-muted-foreground">
                            No product images
                        </span>
                    </div>
                    <div
                        v-if="item.images.length > 0"
                        class="grid grid-cols-5 gap-2"
                    >
                        <button
                            v-for="image in item.images"
                            :key="image.id"
                            type="button"
                            class="overflow-hidden rounded-md border"
                            :class="
                                selectedImage?.id === image.id
                                    ? 'ring-2 ring-ring'
                                    : ''
                            "
                            @click="selectedImage = image"
                        >
                            <img
                                :src="image.url"
                                :alt="image.original_filename ?? item.name"
                                loading="lazy"
                                class="aspect-square w-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <div class="space-y-4 rounded-lg border p-4">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Public status
                        </p>
                        <StatusBadge
                            :tone="item.is_public ? 'success' : 'warning'"
                        >
                            {{ item.is_public ? 'Public' : 'Private' }}
                        </StatusBadge>
                    </div>
                    <div v-if="item.public_url" class="space-y-3">
                        <img
                            v-if="qrUrl"
                            :src="qrUrl"
                            :alt="`QR code for ${item.name}`"
                            class="size-44 rounded-md bg-white p-2"
                        />
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Public URL
                            </p>
                            <a
                                :href="item.public_url"
                                class="text-sm font-medium break-all hover:underline"
                                target="_blank"
                                rel="noreferrer"
                            >
                                {{ item.public_url }}
                            </a>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            @click="printQr"
                        >
                            Print QR
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

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
                <div v-if="item.type === 'product'">
                    <p class="text-sm text-muted-foreground">
                        Current quantity
                    </p>
                    <p class="font-medium">{{ item.quantity }}</p>
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

        <ActivityHistoryPanel :logs="activityLogs" />

        <Card v-if="item.type === 'product'">
            <CardHeader>
                <CardTitle>Recent inventory movements</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Qty</th>
                                <th class="px-4 py-3 font-medium">Before</th>
                                <th class="px-4 py-3 font-medium">After</th>
                                <th class="px-4 py-3 font-medium">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="movement in item.inventory_movements"
                                :key="movement.id"
                            >
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ movement.created_at ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-4">{{ movement.type }}</td>
                                <td class="px-4 py-4">
                                    {{ movement.quantity }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ movement.quantity_before }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ movement.quantity_after }}
                                </td>
                                <td class="px-4 py-4">
                                    {{ movement.reference ?? '-' }}
                                </td>
                            </tr>
                            <tr v-if="item.inventory_movements.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No inventory movements recorded.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
