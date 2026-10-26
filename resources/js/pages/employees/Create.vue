<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PageHeader from '@/components/app/PageHeader.vue';
import { index } from '@/routes/employees';
import EmployeeForm from './Form.vue';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

defineProps<{
    companies: CompanyOption[];
    defaultCompanyId: number | null;
    employmentTypes: string[];
    payFrequencies: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Employees', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="New employee" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New employee"
            description="Add the employee information needed for payroll."
        />

        <EmployeeForm
            :companies="companies"
            :default-company-id="defaultCompanyId"
            :employment-types="employmentTypes"
            :pay-frequencies="payFrequencies"
        />
    </div>
</template>
