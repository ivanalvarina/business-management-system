<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/employees';
import EmployeeForm from './Form.vue';
import type { EmployeeFormData } from './Form.vue';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

defineProps<{
    employee: EmployeeFormData;
    companies: CompanyOption[];
    employmentTypes: string[];
    payFrequencies: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Employees', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${employee.first_name} ${employee.last_name}`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`Edit ${employee.first_name} ${employee.last_name}`"
            description="Update employee and payroll master data."
        />

        <EmployeeForm
            :employee="employee"
            :companies="companies"
            :employment-types="employmentTypes"
            :pay-frequencies="payFrequencies"
        />
    </div>
</template>
