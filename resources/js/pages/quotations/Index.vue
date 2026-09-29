<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/quotations';
import Money from '@/components/Money.vue';

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

const statusTone = (value: string) => {
    if (['approved', 'accepted'].includes(value)) {
        return 'success';
    }

    if (['for_approval', 'sent'].includes(value)) {
        return 'info';
    }

    if (['rejected', 'declined', 'cancelled'].includes(value)) {
        return 'warning';
    }

    return 'warning';
};

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Quotations" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Quotations"
            description="Create and manage sales quotations for active company-client relationships."
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

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 xl:grid-cols-[1fr_220px_180px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="search"
                        placeholder="Search quotation or client"
                    />
                    <select
                        v-model="companyId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Quotation</th>
                                <th class="px-4 py-3 font-medium">Client</th>
                                <th class="px-4 py-3 font-medium">Company</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="quotation in quotations.data"
                                :key="quotation.id"
                            >
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(quotation.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ quotation.quotation_no }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        Valid until
                                        {{ quotation.valid_until ?? 'not set' }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ quotation.client.client_name }}
                                    <p class="text-muted-foreground">
                                        {{ quotation.client.client_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ quotation.company.company_name }}
                                    <p class="text-muted-foreground">
                                        {{ quotation.company.company_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ quotation.quotation_date }}
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="statusTone(quotation.status)"
                                    >
                                        {{ statusLabel(quotation.status) }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right font-medium">
                                    <Money
                                        :amount="quotation.total_amount"
                                        :currency="quotation.currency"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="show(quotation.id)">
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>

                                        <Button
                                            v-if="
                                                can.edit &&
                                                quotation.status !==
                                                    'approved' &&
                                                quotation.status !==
                                                    'accepted' &&
                                                quotation.status !==
                                                    'cancelled' &&
                                                quotation.status !==
                                                    'for_approval'
                                            "
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="edit(quotation.id)"
                                                >Edit</Link
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="quotations.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No quotations found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ quotations.from ?? 0 }} to
                        {{ quotations.to ?? 0 }} of {{ quotations.total }}
                        quotations
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in quotations.links"
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
