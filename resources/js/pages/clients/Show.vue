<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ClientController from '@/actions/App/Http/Controllers/ClientController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/clients';

type Company = {
    id: number;
    company_code: string;
    company_name: string;
    status: 'active' | 'inactive';
};

type Contact = {
    id: number;
    name: string;
    position: string | null;
    department: string | null;
    email: string | null;
    phone: string | null;
    mobile: string | null;
    is_primary: boolean;
};

type Client = {
    id: number;
    client_code: string;
    client_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    billing_address: string | null;
    shipping_address: string | null;
    status: 'active' | 'inactive';
    created_at: string | null;
    companies: Company[];
    contacts: Contact[];
};

const props = defineProps<{
    client: Client;
    can: {
        edit: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clients', href: index() },
            { title: 'Client details', href: '#' },
        ],
    },
});

const toggleStatus = () => {
    router.visit(
        props.client.status === 'active'
            ? ClientController.deactivate(props.client.id)
            : ClientController.activate(props.client.id),
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="client.client_name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="client.client_name"
            :description="client.client_code"
        >
            <template #actions>
                <Button v-if="can.edit" variant="outline" @click="toggleStatus">
                    {{ client.status === 'active' ? 'Deactivate' : 'Activate' }}
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(client.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle>Client details</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Status</p>
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
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Trade name</p>
                        <p class="font-medium">
                            {{ client.trade_name ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">TIN</p>
                        <p class="font-medium">{{ client.tin ?? 'Not set' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p class="font-medium">
                            {{ client.email ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Phone</p>
                        <p class="font-medium">
                            {{ client.phone ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Created</p>
                        <p class="font-medium">
                            {{ client.created_at ?? 'Unknown' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Billing address
                        </p>
                        <p class="font-medium whitespace-pre-line">
                            {{ client.billing_address ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Shipping address
                        </p>
                        <p class="font-medium whitespace-pre-line">
                            {{ client.shipping_address ?? 'Not set' }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Companies</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="company in client.companies"
                        :key="company.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <p class="font-medium">{{ company.company_name }}</p>
                        <p class="text-muted-foreground">
                            {{ company.company_code }}
                        </p>
                    </div>
                    <p
                        v-if="client.companies.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No companies assigned.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Contact persons</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="contact in client.contacts"
                    :key="contact.id"
                    class="rounded-lg border p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ contact.name }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ contact.position ?? 'No position' }}
                            </p>
                        </div>
                        <StatusBadge v-if="contact.is_primary" tone="success"
                            >Primary</StatusBadge
                        >
                    </div>
                    <dl class="mt-3 space-y-1 text-muted-foreground">
                        <div v-if="contact.department">
                            Department: {{ contact.department }}
                        </div>
                        <div v-if="contact.email">
                            Email: {{ contact.email }}
                        </div>
                        <div v-if="contact.phone">
                            Phone: {{ contact.phone }}
                        </div>
                        <div v-if="contact.mobile">
                            Mobile: {{ contact.mobile }}
                        </div>
                    </dl>
                </div>
                <p
                    v-if="client.contacts.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No contacts recorded.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
