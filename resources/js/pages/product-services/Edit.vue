<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import ProductServiceController from '@/actions/App/Http/Controllers/ProductServiceController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import ProductImageUploader from '@/components/product-services/ProductImageUploader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/product-services';

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
    images: {
        id: number;
        url: string;
        original_filename: string | null;
        is_primary: boolean;
    }[];
};

const props = defineProps<{ item: Item }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products & Services', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});

const form = useForm({
    code: props.item.code,
    type: props.item.type,
    name: props.item.name,
    description: props.item.description ?? '',
    unit: props.item.unit,
    default_price: props.item.default_price,
    quantity: props.item.quantity,
    status: props.item.status,
    is_public: props.item.is_public,
    existing_image_ids: props.item.images.map((image) => image.id),
    images: [] as File[],
    primary_existing_image_id:
        props.item.images.find((image) => image.is_primary)?.id ?? null,
    primary_new_image_index: null as number | null,
    _method: 'put',
});

watch(
    () => form.type,
    (type) => {
        if (type === 'service') {
            form.quantity = 0;
            form.existing_image_ids = [];
            form.images = [];
            form.primary_existing_image_id = null;
            form.primary_new_image_index = null;
        }
    },
);

const submit = () =>
    form.post(ProductServiceController.update.url(props.item.id), {
        forceFormData: true,
    });
</script>

<template>
    <Head :title="`Edit ${item.name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="`Edit ${item.name}`" :description="item.code" />

        <Card>
            <CardContent>
                <form class="max-w-6xl space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="code">Code</Label>
                            <Input id="code" v-model="form.code" />
                            <InputError :message="form.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Name</Label>
                            <Input id="name" v-model="form.name" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="type">Type</Label>
                            <select
                                id="type"
                                v-model="form.type"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="product">Product</option>
                                <option value="service">Service</option>
                            </select>
                            <InputError :message="form.errors.type" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="unit">Unit</Label>
                            <Input id="unit" v-model="form.unit" />
                            <InputError :message="form.errors.unit" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="default_price">Default price</Label>
                            <Input
                                id="default_price"
                                v-model="form.default_price"
                                inputmode="decimal"
                            />
                            <InputError :message="form.errors.default_price" />
                        </div>
                        <div v-if="form.type === 'product'" class="grid gap-2">
                            <Label for="quantity">Quantity</Label>
                            <Input
                                id="quantity"
                                v-model.number="form.quantity"
                                type="number"
                                min="0"
                                step="1"
                            />
                            <InputError :message="form.errors.quantity" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="status">Status</Label>
                            <select
                                id="status"
                                v-model="form.status"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <InputError :message="form.errors.status" />
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                v-model="form.is_public"
                                type="checkbox"
                                class="size-4 rounded border-input"
                            />
                            Public QR product page
                        </label>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                        <ProductImageUploader
                            v-if="form.type === 'product'"
                            class="md:col-span-2"
                            :existing-images="item.images"
                            :errors="form.errors"
                            @update:existing-image-ids="
                                form.existing_image_ids = $event
                            "
                            @update:new-images="form.images = $event"
                            @update:primary-existing-image-id="
                                form.primary_existing_image_id = $event
                            "
                            @update:primary-new-image-index="
                                form.primary_new_image_index = $event
                            "
                        />
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing"
                            >Save changes</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
