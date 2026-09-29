<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

watch(report, () => {
    clientId.value = 'all';
    vendorId.value = 'all';
    status.value = 'all';
});

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const submit = () => {
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
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Reports"
            description="Operational reports scoped to your accessible companies."
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

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid w-full min-w-0 gap-3 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8"
                    @submit.prevent="submit"
                >
                    <select
                        v-model="report"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option
                            v-for="reportOption in reports"
                            :key="reportOption.key"
                            :value="reportOption.key"
                        >
                            {{ reportOption.label }}
                        </option>
                    </select>
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                        v-model="clientId"
                        :disabled="
                            !['quotations', 'client-pos'].includes(report)
                        "
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50"
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
                        v-model="vendorId"
                        :disabled="report !== 'purchase-orders'"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50"
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
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                    <Input v-model="dateFrom" type="date" />
                    <Input v-model="dateTo" type="date" />
                    <Button type="submit" variant="outline">
                        <Search />
                        Run
                    </Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="px-4 py-3 font-medium"
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(row, rowIndex) in rows.data"
                                :key="rowIndex"
                            >
                                <td
                                    v-for="column in columns"
                                    :key="column.key"
                                    class="px-4 py-4"
                                >
                                    {{ row[column.key] ?? '' }}
                                </td>
                            </tr>
                            <tr v-if="rows.data.length === 0">
                                <td
                                    :colspan="columns.length"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No report records found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ rows.from ?? 0 }} to {{ rows.to ?? 0 }} of
                        {{ rows.total }} records
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="link in rows.links" :key="link.label">
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
