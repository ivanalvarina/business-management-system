<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/clients';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type Client = {
    id: number;
    client_code: string;
    client_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    status: 'active' | 'inactive';
    contacts_count: number;
    companies: CompanyOption[];
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string; status: string; company_id: number | null };
    companies: CompanyOption[];
    clients: {
        data: Client[];
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
        breadcrumbs: [{ title: 'Clients', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref(props.filters.company_id ?? '');

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Clients"
            description="Manage centralized customer master records and company assignments."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New client
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 lg:grid-cols-[1fr_220px_180px_auto]"
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
                                <th class="px-4 py-3 font-medium">Client</th>
                                <th class="px-4 py-3 font-medium">Companies</th>
                                <th class="px-4 py-3 font-medium">Contact</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="client in clients.data" :key="client.id">
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(client.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ client.client_name }}
                                    </Link>
                                    <p class="text-muted-foreground">
                                        {{ client.client_code }}
                                        <span v-if="client.tin">
                                            · {{ client.tin }}</span
                                        >
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <StatusBadge
                                            v-for="company in client.companies"
                                            :key="company.id"
                                            tone="info"
                                        >
                                            {{ company.company_code }}
                                        </StatusBadge>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    <p>{{ client.email ?? 'No email' }}</p>
                                    <p>{{ client.phone ?? 'No phone' }}</p>
                                    <p>{{ client.contacts_count }} contacts</p>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            client.status === 'active'
                                                ? 'success'
                                                : 'warning'
                                        "
                                    >
                                        {{
                                            client.status === 'active'
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
                                        <Link :href="edit(client.id)"
                                            >Edit</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="clients.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No clients found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ clients.from ?? 0 }} to
                        {{ clients.to ?? 0 }} of {{ clients.total }} clients
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in clients.links"
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
