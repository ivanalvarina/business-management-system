<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Download } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/reports';

type Option = {
    id: number;
    name?: string;
    company_name?: string;
};
type ReportOption = { key: string; label: string };
type Column = { key: string; label: string };
type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    reports: ReportOption[];
    filters: {
        report: string;
        company_id: number | null;
        client_id: number | null;
        vendor_id: number | null;
        status: string;
        date_from: string | null;
        date_to: string | null;
    };
    options: {
        companies: Option[];
        clients: Option[];
        vendors: Option[];
        statuses: string[];
    };
    columns: Column[];
    rows: {
        data: Record<string, string | null>[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    exportUrl: string;
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Reports', href: index() }] },
});

const report = ref(props.filters.report);
const companyId = ref<number | 'all'>(props.filters.company_id ?? 'all');
const clientId = ref<number | 'all'>(props.filters.client_id ?? 'all');
const vendorId = ref<number | 'all'>(props.filters.vendor_id ?? 'all');
const status = ref(props.filters.status ?? 'all');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

const showClientFilter = computed(() =>
    ['quotations', 'client-pos'].includes(report.value),
);
const showVendorFilter = computed(() => report.value === 'purchase-orders');

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const applyFilters = () => {
    router.get(
        index.url(),
        {
            report: report.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
            client_id: clientId.value === 'all' ? undefined : clientId.value,
            vendor_id: vendorId.value === 'all' ? undefined : vendorId.value,
            status: status.value === 'all' ? undefined : status.value,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(
    [report, companyId, clientId, vendorId, status, dateFrom, dateTo],
    () => {
        clearTimeout(timer);
        timer = setTimeout(applyFilters, 300);
    },
);

const setReport = (key: string) => {
    if (key === report.value) {
        return;
    }

    clientId.value = 'all';
    vendorId.value = 'all';
    status.value = 'all';
    report.value = key;
};

const prevLink = computed(() => props.rows.links[0]);
const nextLink = computed(() => props.rows.links[props.rows.links.length - 1]);
const currentPage = computed(
    () => props.rows.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.rows.links.length > 3);
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Reports"
            description="Operational reports for the companies you can access."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <a :href="exportUrl">
                        <Download />
                        Export CSV
                    </a>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div
                class="flex gap-1 overflow-x-auto"
                role="group"
                aria-label="Choose a report"
            >
                <button
                    v-for="reportOption in reports"
                    :key="reportOption.key"
                    type="button"
                    class="shrink-0 rounded-md px-3 py-1.5 text-sm transition-colors"
                    :class="
                        report === reportOption.key
                            ? 'bg-muted font-medium text-foreground'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="report === reportOption.key"
                    @click="setReport(reportOption.key)"
                >
                    {{ reportOption.label }}
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-model="companyId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-48"
                    aria-label="Filter by company"
                >
                    <option value="all">All companies</option>
                    <option
                        v-for="company in options.companies"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.company_name }}
                    </option>
                </select>
                <select
                    v-if="showClientFilter"
                    v-model="clientId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
                    aria-label="Filter by client"
                >
                    <option value="all">All clients</option>
                    <option
                        v-for="client in options.clients"
                        :key="client.id"
                        :value="client.id"
                    >
                        {{ client.name }}
                    </option>
                </select>
                <select
                    v-if="showVendorFilter"
                    v-model="vendorId"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-44"
                    aria-label="Filter by vendor"
                >
                    <option value="all">All vendors</option>
                    <option
                        v-for="vendor in options.vendors"
                        :key="vendor.id"
                        :value="vendor.id"
                    >
                        {{ vendor.name }}
                    </option>
                </select>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-40"
                    aria-label="Filter by status"
                >
                    <option value="all">All statuses</option>
                    <option
                        v-for="statusOption in options.statuses"
                        :key="statusOption"
                        :value="statusOption"
                    >
                        {{ label(statusOption) }}
                    </option>
                </select>

                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    From
                    <Input v-model="dateFrom" type="date" class="w-40" />
                </label>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    To
                    <Input v-model="dateTo" type="date" class="w-40" />
                </label>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th
                                v-for="column in columns"
                                :key="column.key"
                                class="px-5 py-3 font-normal"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, rowIndex) in rows.data"
                            :key="rowIndex"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td
                                v-for="column in columns"
                                :key="column.key"
                                class="px-5 py-3.5"
                            >
                                {{ row[column.key] ?? '' }}
                            </td>
                        </tr>
                        <tr v-if="rows.data.length === 0">
                            <td
                                :colspan="columns.length"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No records match these filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ rows.total }}
                    {{ rows.total === 1 ? 'record' : 'records' }}
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