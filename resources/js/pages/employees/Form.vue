<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import EmployeeController from '@/actions/App/Http/Controllers/EmployeeController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/employees';

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

export type EmployeeFormData = {
    id: number;
    company_id: number;
    employee_number: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    email: string | null;
    phone: string | null;
    address: string | null;
    birth_date: string | null;
    hire_date: string;
    department: string | null;
    job_title: string;
    employment_type: string;
    pay_frequency: string;
    basic_salary: string;
    tin: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    bank_name: string | null;
    bank_account_name: string | null;
    bank_account_number: string | null;
    status: string;
};

const props = defineProps<{
    companies: CompanyOption[];
    defaultCompanyId?: number | null;
    employee?: EmployeeFormData;
    employmentTypes: string[];
    payFrequencies: string[];
}>();

const form = useForm({
    company_id: props.employee?.company_id ?? props.defaultCompanyId ?? '',
    employee_number: props.employee?.employee_number ?? '',
    first_name: props.employee?.first_name ?? '',
    middle_name: props.employee?.middle_name ?? '',
    last_name: props.employee?.last_name ?? '',
    email: props.employee?.email ?? '',
    phone: props.employee?.phone ?? '',
    address: props.employee?.address ?? '',
    birth_date: props.employee?.birth_date ?? '',
    hire_date:
        props.employee?.hire_date ?? new Date().toISOString().slice(0, 10),
    department: props.employee?.department ?? '',
    job_title: props.employee?.job_title ?? '',
    employment_type: props.employee?.employment_type ?? 'regular',
    pay_frequency: props.employee?.pay_frequency ?? 'semi-monthly',
    basic_salary: props.employee?.basic_salary ?? '0.00',
    tin: props.employee?.tin ?? '',
    sss_number: props.employee?.sss_number ?? '',
    philhealth_number: props.employee?.philhealth_number ?? '',
    pagibig_number: props.employee?.pagibig_number ?? '',
    bank_name: props.employee?.bank_name ?? '',
    bank_account_name: props.employee?.bank_account_name ?? '',
    bank_account_number: props.employee?.bank_account_number ?? '',
    status: props.employee?.status ?? 'active',
});

const labelFor = (value: string) =>
    value
        .split('-')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

const submit = () =>
    form.submit(
        props.employee
            ? EmployeeController.update(props.employee.id)
            : EmployeeController.store(),
    );
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <Card>
            <CardHeader>
                <CardTitle>Employee information</CardTitle>
            </CardHeader>
            <CardContent
                class="grid min-w-0 grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <div class="grid min-w-0 gap-2">
                    <Label for="company_id">Company</Label>

                    <select
                        id="company_id"
                        v-model="form.company_id"
                        class="h-9 w-full min-w-0 max-w-full truncate rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option disabled value="">Select company</option>

                        <option
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.id"
                        >
                            {{ company.company_code }} —
                            {{ company.company_name }}
                        </option>
                    </select>

                    <InputError :message="form.errors.company_id" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="employee_number">Employee number</Label>
                    <Input
                        id="employee_number"
                        v-model="form.employee_number"
                        placeholder="EMP-0001"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.employee_number" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="status">Status</Label>
                    <select
                        id="status"
                        v-model="form.status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="first_name">First name</Label>
                    <Input
                        id="first_name"
                        v-model="form.first_name"
                        autocomplete="given-name"
                    />
                    <InputError :message="form.errors.first_name" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="middle_name">Middle name</Label>
                    <Input
                        id="middle_name"
                        v-model="form.middle_name"
                        autocomplete="additional-name"
                    />
                    <InputError :message="form.errors.middle_name" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        v-model="form.last_name"
                        autocomplete="family-name"
                    />
                    <InputError :message="form.errors.last_name" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="phone">Phone</Label>
                    <Input id="phone" v-model="form.phone" autocomplete="tel" />
                    <InputError :message="form.errors.phone" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="birth_date">Birth date</Label>
                    <Input
                        id="birth_date"
                        v-model="form.birth_date"
                        type="date"
                    />
                    <InputError :message="form.errors.birth_date" />
                </div>

                <div class="grid gap-2 md:col-span-2 lg:col-span-3">
                    <Label for="address">Address</Label>
                    <textarea
                        id="address"
                        v-model="form.address"
                        rows="2"
                        class="rounded-md border border-input bg-background px-3 py-2 text-sm"
                        autocomplete="street-address"
                    />
                    <InputError :message="form.errors.address" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Employment and pay</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div class="grid min-w-0 gap-2">
                    <Label for="hire_date">Hire date</Label>
                    <Input
                        id="hire_date"
                        v-model="form.hire_date"
                        type="date"
                    />
                    <InputError :message="form.errors.hire_date" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="department">Department</Label>
                    <Input
                        id="department"
                        v-model="form.department"
                        placeholder="Operations"
                    />
                    <InputError :message="form.errors.department" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="job_title">Job title</Label>
                    <Input
                        id="job_title"
                        v-model="form.job_title"
                        placeholder="Payroll Assistant"
                    />
                    <InputError :message="form.errors.job_title" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="employment_type">Employment type</Label>
                    <select
                        id="employment_type"
                        v-model="form.employment_type"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option
                            v-for="type in employmentTypes"
                            :key="type"
                            :value="type"
                        >
                            {{ labelFor(type) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.employment_type" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="pay_frequency">Pay frequency</Label>
                    <select
                        id="pay_frequency"
                        v-model="form.pay_frequency"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option
                            v-for="frequency in payFrequencies"
                            :key="frequency"
                            :value="frequency"
                        >
                            {{ labelFor(frequency) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.pay_frequency" />
                </div>

                <div class="grid min-w-0 gap-2">
                    <Label for="basic_salary">Basic salary</Label>
                    <Input
                        id="basic_salary"
                        v-model="form.basic_salary"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                    />
                    <InputError :message="form.errors.basic_salary" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Government IDs</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <div class="grid min-w-0 gap-2">
                    <Label for="tin">TIN</Label>
                    <Input id="tin" v-model="form.tin" autocomplete="off" />
                    <InputError :message="form.errors.tin" />
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label for="sss_number">SSS number</Label>
                    <Input
                        id="sss_number"
                        v-model="form.sss_number"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.sss_number" />
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label for="philhealth_number">PhilHealth number</Label>
                    <Input
                        id="philhealth_number"
                        v-model="form.philhealth_number"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.philhealth_number" />
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label for="pagibig_number">Pag-IBIG number</Label>
                    <Input
                        id="pagibig_number"
                        v-model="form.pagibig_number"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.pagibig_number" />
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Bank details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-3">
                <div class="grid min-w-0 gap-2">
                    <Label for="bank_name">Bank name</Label>
                    <Input
                        id="bank_name"
                        v-model="form.bank_name"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.bank_name" />
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label for="bank_account_name">Account name</Label>
                    <Input
                        id="bank_account_name"
                        v-model="form.bank_account_name"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.bank_account_name" />
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label for="bank_account_number">Account number</Label>
                    <Input
                        id="bank_account_number"
                        v-model="form.bank_account_number"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.bank_account_number" />
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-end gap-2">
            <Button variant="outline" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
            <Button :disabled="form.processing">
                {{ employee ? 'Save changes' : 'Create employee' }}
            </Button>
        </div>
    </form>
</template>
