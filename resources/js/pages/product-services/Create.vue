<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ProductServiceController from '@/actions/App/Http/Controllers/ProductServiceController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/product-services';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Products & Services', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});

const form = useForm({
    code: '',
    type: 'product',
    name: '',
    description: '',
    unit: '',
    default_price: '0.00',
    status: 'active',
});

const submit = () => form.post(ProductServiceController.store.url());
</script>

<template>
    <Head title="Create product/service" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Create product/service"
            description="Add a reusable catalog item."
        />

        <Card>
            <CardContent>
                <form class="max-w-4xl space-y-6" @submit.prevent="submit">
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
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.description" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing">Create item</Button>
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
