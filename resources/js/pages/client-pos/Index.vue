<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/client-pos';
import Money from '@/components/Money.vue';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type ClientOption = {
    id: number;
    client_code: string;
    client_name: string;
    company_ids: number[];
};

type ClientPurchaseOrder = {
    id: number;
    client_po_no: string;
    po_date: string;
    currency: string;
    amount: string;
    status: string;
    received_at: string | null;
    company: { company_code: string; company_name: string };
    client: { client_code: string; client_name: string };
    quotations: { id: number; quotation_no: string }[];
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: {
        search: string;
        status: string;
        company_id: number | null;
        client_id: number | null;
    };
    companies: CompanyOption[];
    clients: ClientOption[];
    statuses: string[];
    clientPurchaseOrders: {
        data: ClientPurchaseOrder[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Client Purchase Orders', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | 'all'>(props.filters.company_id ?? 'all');
const clientId = ref<number | 'all'>(props.filters.client_id ?? 'all');

const statusLabel = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const statusTone = (value: string) => {
    if (value === 'confirmed') {
        return 'success';
    }

    if (value === 'in_review') {
        return 'info';
    }

    return value === 'cancelled' ? 'warning' : 'neutral';
};

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value === 'all' ? undefined : companyId.value,
            client_id: clientId.value === 'all' ? undefined : clientId.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Client Purchase Orders" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Client Purchase Orders"
            description="Track purchase orders received from clients."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New client PO
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
                        placeholder="Search PO, client, or quotation"
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
                        v-model="clientId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="all">All clients</option>
                        <option
                            v-for="client in clients"
                            :key="client.id"
                            :value="client.id"
                        >
                            {{ client.client_name }}
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
                    <table class="w-full min-w-[980px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Client PO</th>
                                <th class="px-4 py-3 font-medium">Client</th>
                                <th class="px-4 py-3 font-medium">Company</th>
                                <th class="px-4 py-3 font-medium">
                                    Linked quote
                                </th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Amount
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="clientPo in clientPurchaseOrders.data"
                                :key="clientPo.id"
                            >
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(clientPo.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ clientPo.client_po_no }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        {{ clientPo.po_date }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ clientPo.client.client_name }}
                                    <p class="text-muted-foreground">
                                        {{ clientPo.client.client_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ clientPo.company.company_name }}
                                    <p class="text-muted-foreground">
                                        {{ clientPo.company.company_code }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{
                                        clientPo.quotations.length
                                            ? clientPo.quotations
                                                  .map(
                                                      (quotation) =>
                                                          quotation.quotation_no,
                                                  )
                                                  .join(', ')
                                            : 'None'
                                    }}
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="statusTone(clientPo.status)"
                                    >
                                        {{ statusLabel(clientPo.status) }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right font-medium">
                                    <Money
                                        :amount="clientPo.amount"
                                        :currency="clientPo.currency"
                                    />
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="show(clientPo.id)">
                                                <Eye />
                                                View
                                            </Link>
                                        </Button>

                                        <Button
                                            v-if="
                                                can.edit &&
                                                clientPo.status !== 'fulfilled'
                                            "
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link :href="edit(clientPo.id)"
                                                >Edit</Link
                                            >
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="clientPurchaseOrders.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No client purchase orders found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ clientPurchaseOrders.from ?? 0 }} to
                        {{ clientPurchaseOrders.to ?? 0 }} of
                        {{ clientPurchaseOrders.total }} client POs
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in clientPurchaseOrders.links"
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
