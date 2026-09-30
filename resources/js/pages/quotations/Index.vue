<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    Plus,
    Printer,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Money from '@/components/Money.vue';
import {
    create,
    edit,
    index,
    print as printRoute,
    show,
} from '@/routes/quotations';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type Quotation = {
    id: number;
    quotation_no: string;
    quotation_date: string;
    valid_until: string | null;
    currency: string;
    status: string;
    total_amount: string;
    company: { company_code: string; company_name: string };
    client: { client_code: string; client_name: string };
    can: { print: boolean };
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; status: string; company_id: number | null };
    companies: CompanyOption[];
    statuses: string[];
    quotations: {
        data: Quotation[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Quotations', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | 'all'>(props.filters.company_id ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusDot = (value: string) => {
    if (['approved', 'accepted'].includes(value)) {
        return 'bg-emerald-500';
    }

    if (['for_approval', 'sent'].includes(value)) {
        return 'bg-blue-500';
    }

    return 'bg-amber-500';
};

const canEditQuotation = (quotation: Quotation) =>
    props.can.edit &&
    !['approved', 'accepted', 'cancelled', 'for_approval'].includes(
        quotation.status,
    );

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const prevLink = computed(() => props.quotations.links[0]);
const nextLink = computed(
    () => props.quotations.links[props.quotations.links.length - 1],
);
const currentPage = computed(
    () => props.quotations.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.quotations.links.length > 3);
</script>

<template>
    <Head title="Quotations" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Quotations"
            description="Create and manage sales quotations for your clients."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New quotation
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div class="flex flex-col gap-2 md:flex-row">
                <div class="relative w-full md:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search quotation or client"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="companyId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-52"
                    aria-label="Filter by company"
                    @change="applyFilters"
                >
                    <option value="all">All companies</option>
                    <option
                        v-for="company in companies"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.company_name }}
                    </option>
                </select>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
                    aria-label="Filter by status"
                    @change="applyFilters"
                >
                    <option value="all">All statuses</option>
                    <option
                        v-for="statusOption in statuses"
                        :key="statusOption"
                        :value="statusOption"
                    >
                        {{ statusLabel(statusOption) }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">Quotation</th>
                            <th class="px-5 py-3 font-normal">Client</th>
                            <th class="px-5 py-3 font-normal">Company</th>
                            <th class="px-5 py-3 font-normal">Date</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="px-5 py-3 text-right font-normal">
                                Total
                            </th>
                            <th class="w-32 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="quotation in quotations.data"
                            :key="quotation.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(quotation.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ quotation.quotation_no }}
                                </Link>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ quotation.client.client_name }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ quotation.client.client_code }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    :title="quotation.company.company_name"
                                    class="rounded border px-1.5 py-0.5 font-mono text-xs text-muted-foreground"
                                >
                                    {{ quotation.company.company_code }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <p>{{ quotation.quotation_date }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        quotation.valid_until
                                            ? `Valid until ${quotation.valid_until}`
                                            : 'No expiry'
                                    }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="statusDot(quotation.status)"
                                    />
                                    {{ statusLabel(quotation.status) }}
                                </span>
                            </td>
                            <td
                                class="px-5 py-3.5 text-right font-medium tabular-nums"
                            >
                                <Money
                                    :amount="quotation.total_amount"
                                    :currency="quotation.currency"
                                />
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex justify-end">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="show(quotation.id)"
                                            aria-label="View quotation"
                                            title="View"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="quotation.can.print"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <a
                                            :href="printRoute.url(quotation.id)"
                                            target="_blank"
                                            aria-label="Print quotation"
                                            title="Print"
                                        >
                                            <Printer class="size-4" />
                                        </a>
                                    </Button>
                                    <Button
                                        v-if="canEditQuotation(quotation)"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="edit(quotation.id)"
                                            aria-label="Edit quotation"
                                            title="Edit"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="quotations.data.length === 0">
                            <td
                                colspan="7"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No quotations match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ quotations.total }}
                    {{ quotations.total === 1 ? 'quotation' : 'quotations' }}
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