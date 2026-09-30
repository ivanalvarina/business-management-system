<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
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
const companyId = ref<number | ''>(props.filters.company_id ?? '');
const categoryId = ref<number | ''>(props.filters.vendor_category_id ?? '');

const statusOptions = [
    { value: 'all', label: 'All' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value || undefined,
            vendor_category_id: categoryId.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const setStatus = (value: string) => {
    status.value = value;
    applyFilters();
};

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

const prevLink = computed(() => props.vendors.links[0]);
const nextLink = computed(
    () => props.vendors.links[props.vendors.links.length - 1],
);
const currentPage = computed(
    () => props.vendors.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.vendors.links.length > 3);
</script>

<template>
    <Head title="Vendors" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Vendors"
            description="Manage suppliers, categories, and company coverage."
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

        <div class="space-y-3">
            <div
                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <div class="flex flex-1 flex-col gap-2 md:flex-row">
                    <div class="relative w-full md:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search code, name, or TIN"
                            class="pl-9"
                        />
                    </div>
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
                        aria-label="Filter by company"
                        @change="applyFilters"
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
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
                        aria-label="Filter by category"
                        @change="applyFilters"
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
                </div>

                <div class="flex gap-1" role="group" aria-label="Filter by status">
                    <button
                        v-for="option in statusOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm transition-colors"
                        :class="
                            status === option.value
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="status === option.value"
                        @click="setStatus(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">Vendor</th>
                            <th class="px-5 py-3 font-normal">Category</th>
                            <th class="px-5 py-3 font-normal">Companies</th>
                            <th class="px-5 py-3 font-normal">Contact</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="w-16 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="vendor in vendors.data"
                            :key="vendor.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ initials(vendor.vendor_name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="show(vendor.id)"
                                            class="block truncate font-medium hover:underline"
                                        >
                                            {{ vendor.vendor_name }}
                                        </Link>
                                        <p
                                            class="truncate font-mono text-xs text-muted-foreground"
                                        >
                                            {{ vendor.vendor_code }}
                                            <span v-if="vendor.tin">
                                                · {{ vendor.tin }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-muted-foreground">
                                {{ vendor.category?.name ?? 'Uncategorized' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="company in vendor.companies"
                                        :key="company.id"
                                        :title="company.company_name"
                                        class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ company.company_code }}
                                    </span>
                                    <span
                                        v-if="vendor.companies.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        None assigned
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ vendor.email ?? 'No email' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ vendor.phone ?? 'No phone' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ vendor.contacts_count }}
                                    {{
                                        vendor.contacts_count === 1
                                            ? 'contact'
                                            : 'contacts'
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            vendor.status === 'active'
                                                ? 'bg-emerald-500'
                                                : 'bg-amber-500'
                                        "
                                    />
                                    {{
                                        vendor.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <Button
                                    v-if="can.edit"
                                    variant="ghost"
                                    size="icon"
                                    class="text-muted-foreground hover:text-foreground"
                                    as-child
                                >
                                    <Link
                                        :href="edit(vendor.id)"
                                        aria-label="Edit vendor"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="vendors.data.length === 0">
                            <td
                                colspan="6"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No vendors match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ vendors.total }}
                    {{ vendors.total === 1 ? 'vendor' : 'vendors' }}
                </p>

                <div v-if="showPagination" class="flex items-center gap-1">
                    <Button
                        v-if="prevLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="prevLink.url" aria-label="Previous page">
                            <ChevronLeft class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Previous page"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>

                    <span class="px-2 text-sm font-medium">
                        {{ currentPage }}
                    </span>

                    <Button
                        v-if="nextLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="nextLink.url" aria-label="Next page">
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Next page"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>