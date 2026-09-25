<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    ClipboardList,
    FileText,
    LayoutDashboard,
    Package,
    ShieldCheck,
} from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const foundationItems = [
    {
        title: 'Application shell',
        description:
            'Sidebar, header, content area, and responsive navigation.',
        icon: LayoutDashboard,
        status: 'Ready',
    },
    {
        title: 'Authentication',
        description:
            'Fortify login, logout, password reset, 2FA, and passkeys.',
        icon: ShieldCheck,
        status: 'Starter kit',
    },
    {
        title: 'Master data',
        description: 'Companies, clients, vendors, products, and services.',
        icon: Building2,
        status: 'Placeholder',
    },
    {
        title: 'Operations',
        description: 'Sales, procurement, documents, reports, and approvals.',
        icon: ClipboardList,
        status: 'Placeholder',
    },
];

const moduleGroups = [
    {
        title: 'Master Data',
        modules: ['Companies', 'Clients', 'Vendors', 'Products & Services'],
        icon: Package,
    },
    {
        title: 'Sales & Procurement',
        modules: ['Quotations', 'Client Purchase Orders', 'Purchase Orders'],
        icon: FileText,
    },
    {
        title: 'Administration',
        modules: [
            'Users',
            'Roles & Permissions',
            'Approval Workflows',
            'Audit Trail',
            'Settings',
        ],
        icon: ShieldCheck,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Dashboard"
            description="Business Management System foundation workspace. Module navigation is intentionally limited to placeholders in this phase."
            :icon="LayoutDashboard"
        >
            <template #actions>
                <StatusBadge tone="info">Phase 0</StatusBadge>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="item in foundationItems"
                :key="item.title"
                class="gap-4 py-5"
            >
                <CardHeader class="gap-3">
                    <div class="flex items-start justify-between gap-3">
                        <div
                            class="flex size-10 items-center justify-center rounded-lg border bg-muted/30 text-muted-foreground"
                        >
                            <component :is="item.icon" class="size-5" />
                        </div>
                        <StatusBadge
                            :tone="
                                item.status === 'Ready' ? 'success' : 'neutral'
                            "
                        >
                            {{ item.status }}
                        </StatusBadge>
                    </div>
                    <div>
                        <CardTitle class="text-base">{{
                            item.title
                        }}</CardTitle>
                        <CardDescription class="mt-1">
                            {{ item.description }}
                        </CardDescription>
                    </div>
                </CardHeader>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-[1fr_360px]">
            <Card class="gap-4">
                <CardHeader>
                    <CardTitle>Module Readiness</CardTitle>
                    <CardDescription>
                        Placeholder areas for upcoming implementation phases.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-hidden rounded-lg border">
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-3 font-medium">Area</th>
                                    <th class="px-4 py-3 font-medium">
                                        Modules
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <tr
                                    v-for="group in moduleGroups"
                                    :key="group.title"
                                >
                                    <td class="px-4 py-4 align-top">
                                        <div
                                            class="flex items-center gap-3 font-medium"
                                        >
                                            <component
                                                :is="group.icon"
                                                class="size-4 text-muted-foreground"
                                            />
                                            {{ group.title }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        {{ group.modules.join(', ') }}
                                    </td>
                                    <td class="px-4 py-4 align-top">
                                        <StatusBadge>Placeholder</StatusBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <EmptyState
                title="No business data yet"
                description="Business records, metrics, approvals, and operational activity will appear after future phases add the actual modules."
                :icon="ClipboardList"
            />
        </div>
    </div>
</template>
