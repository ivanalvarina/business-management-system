<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/product-services';

type Item = {
    id: number;
    code: string;
    type: 'product' | 'service';
    name: string;
    unit: string;
    default_price: string;
    status: 'active' | 'inactive';
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; type: string; status: string };
    items: {
        data: Item[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Products & Services', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');
const status = ref(props.filters.status ?? 'all');

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Products & Services" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Products & Services"
            description="Manage reusable catalog records for future transaction documents."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New item
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 lg:grid-cols-[1fr_180px_180px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input v-model="search" placeholder="Search code or name" />
                    <select
                        v-model="type"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All types</option>
                        <option value="product">Products</option>
                        <option value="service">Services</option>
                    </select>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-hidden rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Item</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">Unit</th>
                                <th class="px-4 py-3 font-medium">Price</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="item in items.data" :key="item.id">
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(item.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ item.name }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        {{ item.code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge tone="info">
                                        {{
                                            item.type === 'product'
                                                ? 'Product'
                                                : 'Service'
                                        }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ item.unit }}
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ item.default_price }}
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            item.status === 'active'
                                                ? 'success'
                                                : 'warning'
                                        "
                                    >
                                        {{
                                            item.status === 'active'
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Button
                                        v-if="can.edit"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="edit(item.id)">Edit</Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No products or services found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ items.from ?? 0 }} to {{ items.to ?? 0 }} of
                        {{ items.total }} items
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="link in items.links" :key="link.label">
                            <Button
                                v-if="link.url"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                as-child
                            >
                                <Link :href="link.url" v-html="link.label" />
                            </Button>
                            <Button v-else variant="outline" size="sm" disabled>
                                <span v-html="link.label" />
                            </Button>
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
