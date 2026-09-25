<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/vendors';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type CategoryOption = {
    id: number;
    name: string;
};

type Vendor = {
    id: number;
    vendor_code: string;
    vendor_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    status: 'active' | 'inactive';
    contacts_count: number;
    category: CategoryOption | null;
    companies: CompanyOption[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: {
        search: string;
        status: string;
        company_id: number | null;
        vendor_category_id: number | null;
    };
    companies: CompanyOption[];
    categories: CategoryOption[];
    vendors: {
        data: Vendor[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        create: boolean;
        edit: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Vendors', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref(props.filters.company_id ?? '');
const categoryId = ref(props.filters.vendor_category_id ?? '');

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value || undefined,
            vendor_category_id: categoryId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Vendors" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Vendors"
            description="Manage supplier master records, categories, contacts, and company coverage."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New vendor
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 xl:grid-cols-[1fr_220px_220px_180px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="search"
                        placeholder="Search code, name, or TIN"
                    />
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All companies</option>
                        <option
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.id"
                        >
                            {{ company.company_name }}
                        </option>
                    </select>
                    <select
                        v-model="categoryId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All categories</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
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
                                <th class="px-4 py-3 font-medium">Vendor</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Companies</th>
                                <th class="px-4 py-3 font-medium">Contact</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="vendor in vendors.data" :key="vendor.id">
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(vendor.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ vendor.vendor_name }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        {{ vendor.vendor_code }}
                                        <span v-if="vendor.tin">
                                            - {{ vendor.tin }}</span
                                        >
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{
                                        vendor.category?.name ?? 'Uncategorized'
                                    }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <StatusBadge
                                            v-for="company in vendor.companies"
                                            :key="company.id"
                                            tone="info"
                                        >
                                            {{ company.company_code }}
                                        </StatusBadge>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    <p>{{ vendor.email ?? 'No email' }}</p>
                                    <p>{{ vendor.phone ?? 'No phone' }}</p>
                                    <p>{{ vendor.contacts_count }} contacts</p>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            vendor.status === 'active'
                                                ? 'success'
                                                : 'warning'
                                        "
                                    >
                                        {{
                                            vendor.status === 'active'
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
                                        <Link :href="edit(vendor.id)"
                                            >Edit</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="vendors.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No vendors found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ vendors.from ?? 0 }} to
                        {{ vendors.to ?? 0 }} of {{ vendors.total }} vendors
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in vendors.links"
                            :key="link.label"
                        >
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
