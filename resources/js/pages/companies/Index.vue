<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/companies';

type Company = {
    id: number;
    company_code: string;
    company_name: string;
    trade_name: string | null;
    email: string | null;
    phone: string | null;
    status: 'active' | 'inactive';
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string; status: string };
    companies: {
        data: Company[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Companies', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');

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

const prevLink = computed(() => props.companies.links[0]);
const nextLink = computed(
    () => props.companies.links[props.companies.links.length - 1],
);
const currentPage = computed(
    () => props.companies.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.companies.links.length > 3);
</script>

<template>
    <Head title="Companies" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Companies"
            description="Manage your legal and business entities."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New company
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative w-full sm:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search by name or code"
                        class="pl-9"
                    />
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
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Contact</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="w-16 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="company in companies.data"
                            :key="company.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-lg border bg-muted/50 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ initials(company.company_name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="show(company.id)"
                                            class="block truncate font-medium hover:underline"
                                        >
                                            {{ company.company_name }}
                                        </Link>
                                        <p
                                            class="truncate font-mono text-xs text-muted-foreground"
                                        >
                                            {{ company.company_code }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ company.email ?? 'No email' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ company.phone ?? 'No phone' }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            company.status === 'active'
                                                ? 'bg-emerald-500'
                                                : 'bg-amber-500'
                                        "
                                    />
                                    {{
                                        company.status === 'active'
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
                                        :href="edit(company.id)"
                                        aria-label="Edit company"
                                    >
                                        <Pencil class="size-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="companies.data.length === 0">
                            <td
                                colspan="4"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No companies match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ companies.total }}
                    {{ companies.total === 1 ? 'company' : 'companies' }}
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