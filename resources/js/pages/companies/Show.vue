<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import { update as switchCompany } from '@/routes/current-company';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/companies';

type AssignedUser = {
    id: number;
    name: string;
    email: string;
};

type Company = {
    id: number;
    company_code: string;
    company_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    logo_url: string | null;
    status: 'active' | 'inactive';
    created_at: string | null;
    users: AssignedUser[];
};

const props = defineProps<{
    company: Company;
    can: {
        edit: boolean;
        delete: boolean;
        switch: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: 'Company details', href: '#' },
        ],
    },
});

const toggleStatus = () => {
    router.visit(
        props.company.status === 'active'
            ? CompanyController.deactivate(props.company.id)
            : CompanyController.activate(props.company.id),
        { preserveScroll: true },
    );
};

const selectCompany = () => {
    router.visit(switchCompany(props.company.id), {
        preserveScroll: true,
    });
};

const deleteCompany = () => {
    if (!confirm('Delete this company?')) {
        return;
    }

    router.visit(CompanyController.destroy(props.company.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="company.company_name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="company.company_name"
            :description="company.company_code"
        >
            <template #actions>
                <Button
                    v-if="can.switch"
                    variant="outline"
                    @click="selectCompany"
                >
                    Select
                </Button>
                <Button v-if="can.edit" variant="outline" @click="toggleStatus">
                    {{
                        company.status === 'active' ? 'Deactivate' : 'Activate'
                    }}
                </Button>
                <Button
                    v-if="can.delete"
                    variant="destructive"
                    @click="deleteCompany"
                >
                    Delete
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(company.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
            <Card>
                <CardHeader>
                    <CardTitle>Company details</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted-foreground">Status</p>
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
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Trade name</p>
                        <p class="font-medium">
                            {{ company.trade_name ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">TIN</p>
                        <p class="font-medium">
                            {{ company.tin ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Email</p>
                        <p class="font-medium">
                            {{ company.email ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Phone</p>
                        <p class="font-medium">
                            {{ company.phone ?? 'Not set' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Created</p>
                        <p class="font-medium">
                            {{ company.created_at ?? 'Unknown' }}
                        </p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-sm text-muted-foreground">Address</p>
                        <p class="font-medium whitespace-pre-line">
                            {{ company.address ?? 'Not set' }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>User access</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="user in company.users"
                        :key="user.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <p class="font-medium">{{ user.name }}</p>
                        <p class="text-muted-foreground">{{ user.email }}</p>
                    </div>
                    <p
                        v-if="company.users.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No users assigned.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
