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
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as clientPosIndex } from '@/routes/client-pos';
import { index as clientsIndex } from '@/routes/clients';
import { index as companiesIndex } from '@/routes/companies';
import { index as documentsIndex } from '@/routes/documents';
import { index as productServicesIndex } from '@/routes/product-services';
import { index as purchaseOrdersIndex } from '@/routes/purchase-orders';
import { index as purchaseRequestsIndex } from '@/routes/purchase-requests';
import { index as quotationsIndex } from '@/routes/quotations';
import { index as receivingReceiptsIndex } from '@/routes/receiving-receipts';
import { index as reportsIndex } from '@/routes/reports';
import { index as vendorsIndex } from '@/routes/vendors';
import type { NavSection } from '@/types';

const page = usePage();

const currentCompanyLogoUrl = computed(
    () => page.props.companyContext.current?.logo_url,
);

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
                    href: quotationsIndex(),
                    icon: ReceiptText,
                    permission: 'quotations.view',
                },
                {
                    title: 'Client Purchase Orders',
                    href: clientPosIndex(),
                    icon: FileText,
                    permission: 'client-pos.view',
                },
            ],
        },
        {
            title: 'Procurement',
            items: [
                {
                    title: 'Purchase Requests',
                    href: purchaseRequestsIndex(),
                    icon: ScrollText,
                    permission: 'purchase-requests.view',
                },
                {
                    title: 'Purchase Orders',
                    href: purchaseOrdersIndex(),
                    icon: ShoppingCart,
                    permission: 'purchase-orders.view',
                },
                {
                    title: 'Receiving Receipts',
                    href: receivingReceiptsIndex(),
                    icon: ReceiptText,
                    permission: 'receiving-receipts.view',
                },
            ],
        },
        {
            title: 'Documents',
            items: [
                {
                    title: 'Documents',
                    href: documentsIndex(),
                    icon: FileArchive,
                    permission: 'documents.view',
                },
            ],
        },
        {
            title: 'Reports',
            items: [
                {
                    title: 'Reports',
                    href: reportsIndex(),
                    icon: BarChart3,
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
                    href: auditLogsIndex(),
                    icon: ScrollText,
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
                    <SidebarMenuButton size="lg" class="h-16" as-child>
                        <Link :href="dashboard()">
                            <!-- <AppLogo /> -->
                            <img
                                v-if="currentCompanyLogoUrl"
                                :src="currentCompanyLogoUrl"
                                alt=""
                                class="ml-auto h-12 w-64 rounded-lg border bg-background object-contain p-1.5 group-data-[collapsible=icon]:hidden"
                            />
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
