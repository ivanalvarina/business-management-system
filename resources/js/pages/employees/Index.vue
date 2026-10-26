<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, destroy, edit, index } from '@/routes/employees';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type Employee = {
    id: number;
    company: CompanyOption;
    employee_number: string;
    full_name: string;
    department: string | null;
    job_title: string;
    employment_type: string;
    pay_frequency: string;
    basic_salary: string;
    status: 'active' | 'inactive';
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string; status: string; company_id: number | null };
    companies: CompanyOption[];
    employees: {
        data: Employee[];
        links: PaginationLink[];
        total: number;
    };
    can: { create: boolean; edit: boolean; delete: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Employees', href: index() }],
    },
});

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'all');
const companyId = ref<number | ''>(props.filters.company_id ?? '');

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            status: status.value === 'all' ? undefined : status.value,
            company_id: companyId.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const formatMoney = (amount: string) =>
    Number(amount).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
    });

const labelFor = (value: string) =>
    value
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const deleteEmployee = (employee: Employee) => {
    if (
        !window.confirm(
            `Delete ${employee.full_name}? Existing payroll records will be kept.`,
        )
    ) {
        return;
    }

    router.visit(destroy(employee.id), {
        preserveScroll: true,
    });
};

const prevLink = computed(() => props.employees.links[0]);
const nextLink = computed(
    () => props.employees.links[props.employees.links.length - 1],
);
const currentPage = computed(
    () => props.employees.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.employees.links.length > 3);
</script>

<template>
    <Head title="Employees" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Employee master data"
            description="Maintain employee information for payroll processing."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New employee
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="flex flex-col gap-3 lg:flex-row">
            <div class="relative w-full lg:max-w-sm">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search employee, department, or position"
                    class="pl-9"
                />
            </div>
            <select
                v-model="companyId"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm lg:w-64"
                aria-label="Filter by company"
                @change="applyFilters"
            >
                <option
                    v-for="company in companies"
                    :key="company.id"
                    :value="company.id"
                >
                    {{ company.company_code }} — {{ company.company_name }}
                </option>
            </select>
            <select
                v-model="status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm lg:w-40"
                aria-label="Filter by status"
                @change="applyFilters"
            >
                <option value="all">All statuses</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div
            v-if="employees.data.length"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Employee</th>
                            <th class="px-4 py-3 font-medium">
                                Department / position
                            </th>
                            <th class="px-4 py-3 font-medium">Employment</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Basic salary
                            </th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="w-24 px-4 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="employee in employees.data"
                            :key="employee.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ employee.full_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ employee.employee_number }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ employee.job_title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ employee.department || 'No department' }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <p>{{ labelFor(employee.employment_type) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ labelFor(employee.pay_frequency) }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                            >
                                {{ formatMoney(employee.basic_salary) }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :tone="
                                        employee.status === 'active'
                                            ? 'success'
                                            : 'warning'
                                    "
                                >
                                    {{ labelFor(employee.status) }}
                                </StatusBadge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="can.edit"
                                        size="icon"
                                        variant="ghost"
                                        as-child
                                    >
                                        <Link
                                            :href="edit(employee.id)"
                                            :aria-label="`Edit ${employee.full_name}`"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="can.delete"
                                        size="icon"
                                        variant="ghost"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Delete ${employee.full_name}`"
                                        @click="deleteEmployee(employee)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EmptyState
            v-else
            title="No employees found"
            description="Add your first employee or adjust the current filters."
            :icon="Users"
        >
            <Button v-if="can.create" size="sm" as-child>
                <Link :href="create()"><Plus /> New employee</Link>
            </Button>
        </EmptyState>

        <div class="flex items-center justify-between px-1">
            <p class="text-sm text-muted-foreground">
                {{ employees.total }}
                {{ employees.total === 1 ? 'employee' : 'employees' }}
            </p>
            <div v-if="showPagination" class="flex items-center gap-1">
                <Button
                    v-if="prevLink?.url"
                    variant="ghost"
                    size="icon"
                    as-child
                >
                    <Link :href="prevLink.url" aria-label="Previous page"
                        ><ChevronLeft
                    /></Link>
                </Button>
                <Button v-else variant="ghost" size="icon" disabled
                    ><ChevronLeft
                /></Button>
                <span class="px-2 text-sm font-medium">{{ currentPage }}</span>
                <Button
                    v-if="nextLink?.url"
                    variant="ghost"
                    size="icon"
                    as-child
                >
                    <Link :href="nextLink.url" aria-label="Next page"
                        ><ChevronRight
                    /></Link>
                </Button>
                <Button v-else variant="ghost" size="icon" disabled
                    ><ChevronRight
                /></Button>
            </div>
        </div>
    </div>
</template>
