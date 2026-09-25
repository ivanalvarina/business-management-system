<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BarChart3,
    Building2,
    FileArchive,
    FileText,
    LayoutGrid,
    Package,
    ReceiptText,
    ScrollText,
    Settings,
    ShieldCheck,
    ShoppingCart,
    Store,
    Users,
    Workflow,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as usersIndex } from '@/routes/admin/users';
import { index as clientsIndex } from '@/routes/clients';
import { index as companiesIndex } from '@/routes/companies';
import { index as productServicesIndex } from '@/routes/product-services';
import { index as vendorsIndex } from '@/routes/vendors';
import type { NavSection } from '@/types';

const page = usePage();

const can = (permission: string) =>
    page.props.auth.permissions.includes(permission);

const navigationSections = computed<NavSection[]>(() =>
    [
        {
            items: [
                {
                    title: 'Dashboard',
                    href: dashboard(),
                    icon: LayoutGrid,
                    permission: 'dashboard.view',
                },
            ],
        },
        {
            title: 'Master Data',
            items: [
                {
                    title: 'Companies',
                    href: companiesIndex(),
                    icon: Building2,
                    permission: 'companies.view',
                },
                {
                    title: 'Clients',
                    href: clientsIndex(),
                    icon: Users,
                    permission: 'clients.view',
                },
                {
                    title: 'Vendors',
                    href: vendorsIndex(),
                    icon: Store,
                    permission: 'vendors.view',
                },
                {
                    title: 'Products & Services',
                    href: productServicesIndex(),
                    icon: Package,
                    permission: 'products-services.view',
                },
            ],
        },
        {
            title: 'Sales',
            items: [
                {
                    title: 'Quotations',
                    href: dashboard(),
                    icon: ReceiptText,
                    disabled: true,
                    permission: 'quotations.view',
                },
                {
                    title: 'Client Purchase Orders',
                    href: dashboard(),
                    icon: FileText,
                    disabled: true,
                    permission: 'client-pos.view',
                },
            ],
        },
        {
            title: 'Procurement',
            items: [
                {
                    title: 'Purchase Orders',
                    href: dashboard(),
                    icon: ShoppingCart,
                    disabled: true,
                    permission: 'purchase-orders.view',
                },
            ],
        },
        {
            title: 'Documents',
            items: [
                {
                    title: 'Documents',
                    href: dashboard(),
                    icon: FileArchive,
                    disabled: true,
                    permission: 'documents.view',
                },
            ],
        },
        {
            title: 'Reports',
            items: [
                {
                    title: 'Reports',
                    href: dashboard(),
                    icon: BarChart3,
                    disabled: true,
                    permission: 'reports.view',
                },
            ],
        },
        {
            title: 'Administration',
            items: [
                {
                    title: 'Users',
                    href: usersIndex(),
                    icon: Users,
                    permission: 'users.view',
                },
                {
                    title: 'Roles & Permissions',
                    href: rolesIndex(),
                    icon: ShieldCheck,
                    permission: 'roles.view',
                },
                {
                    title: 'Approval Workflows',
                    href: dashboard(),
                    icon: Workflow,
                    disabled: true,
                    permission: 'approval-workflows.view',
                },
                {
                    title: 'Audit Trail',
                    href: dashboard(),
                    icon: ScrollText,
                    disabled: true,
                    permission: 'audit-logs.view',
                },
                {
                    title: 'Settings',
                    href: dashboard(),
                    icon: Settings,
                    disabled: true,
                    permission: 'settings.view',
                },
            ],
        },
    ]
        .map((section) => ({
            ...section,
            items: section.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((section) => section.items.length > 0),
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :sections="navigationSections" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
