<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Companies" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Companies"
            description="Manage internal legal and business entities."
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

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="flex flex-col gap-2 md:max-w-2xl md:flex-row"
                    @submit.prevent="submitSearch"
                >
                    <Input v-model="search" placeholder="Search companies" />
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
                                <th class="px-4 py-3 font-medium">Company</th>
                                <th class="px-4 py-3 font-medium">Code</th>
                                <th class="px-4 py-3 font-medium">Contact</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="company in companies.data"
                                :key="company.id"
                            >
                                <td class="px-4 py-4">
                                    <Link
                                        :href="show(company.id)"
                                        class="font-medium hover:underline"
                                    >
                                        {{ company.company_name }}
                                    </Link>
                                    <p
                                        v-if="company.trade_name"
                                        class="text-muted-foreground"
                                    >
                                        {{ company.trade_name }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ company.company_code }}
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    <p>{{ company.email ?? 'No email' }}</p>
                                    <p>{{ company.phone ?? 'No phone' }}</p>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            company.status === 'active'
                                                ? 'success'
                                                : 'warning'
                                        "
                                    >
                                        {{
                                            company.status === 'active'
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
                                        <Link :href="edit(company.id)"
                                            >Edit</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="companies.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No companies found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ companies.from ?? 0 }} to
                        {{ companies.to ?? 0 }} of
                        {{ companies.total }} companies
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in companies.links"
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
