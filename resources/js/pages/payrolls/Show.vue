<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    FileText,
    LockKeyhole,
    Printer,
    Save,
    UnlockKeyhole,
} from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    exportMethod as payrollExport,
    finalize,
    index,
    reopen,
    show,
    update,
} from '@/routes/payrolls';
import {
    bulk as payslipBulk,
    show as payslipShow,
} from '@/routes/payrolls/payslips';

type PayrollItem = {
    id: number;
    employee_number: string;
    employee_name: string;
    department: string | null;
    job_title: string;
    monthly_basic_salary: string;
    bank_name: string | null;
    bank_account_last_four: string | null;
    hours_per_day: string;
    payroll_days_basis: string;
    monthly_workdays_divisor: string;
    annual_workdays_divisor: string;
    regular_ot_multiplier: string;
    special_holiday_multiplier: string;
    special_holiday_ot_multiplier: string;
    regular_holiday_multiplier: string;
    regular_holiday_ot_multiplier: string;
    days_worked: string;
    absent_days: string;
    late_hours: string;
    regular_ot_hours: string;
    special_holiday_hours: string;
    regular_holiday_hours: string;
    salary_adjustment: string;
    sss_contribution: string;
    philhealth_contribution: string;
    pagibig_contribution: string;
    withholding_tax: string;
    other_deduction: string;
    allowance: string;
};
type Payroll = {
    id: number;
    company: { id: number; company_code: string; company_name: string };
    period_start: string;
    period_end: string;
    pay_date: string;
    status: 'draft' | 'finalized';
    items: PayrollItem[];
};

const props = defineProps<{
    payroll: Payroll;
    weekdayCount: number;
    can: {
        edit: boolean;
        finalize: boolean;
        reopen: boolean;
        viewPayslip: boolean;
        printPayslip: boolean;
        export: boolean;
    };
}>();
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Payroll', href: index() },
            { title: 'Payroll details', href: '#' },
        ],
    },
});

const inputFields = [
    'days_worked',
    'absent_days',
    'late_hours',
    'regular_ot_hours',
    'special_holiday_hours',
    'regular_holiday_hours',
    'salary_adjustment',
    'sss_contribution',
    'philhealth_contribution',
    'pagibig_contribution',
    'withholding_tax',
    'other_deduction',
    'allowance',
] as const;
type InputField = (typeof inputFields)[number];
type FormItem = { id: number } & Record<InputField, string>;
const form = useForm<{ items: FormItem[] }>({
    items: props.payroll.items.map(
        (item) =>
            ({
                id: item.id,
                ...Object.fromEntries(
                    inputFields.map((field) => [field, item[field]]),
                ),
            }) as FormItem,
    ),
});

const amount = (value: string | number) => Number(value || 0);
const calculate = (item: PayrollItem, values: FormItem) => {
    const daily =
        amount(item.monthly_basic_salary) /
        amount(item.monthly_workdays_divisor);
    const hourly = daily / amount(item.hours_per_day);
    const basic = amount(item.monthly_basic_salary) / 2;
    const regularOt =
        hourly *
        amount(values.regular_ot_hours) *
        amount(item.regular_ot_multiplier);
    const holiday130 =
        hourly *
        amount(values.special_holiday_hours) *
        amount(item.special_holiday_multiplier);
    const holiday200 =
        hourly *
        amount(values.regular_holiday_hours) *
        amount(item.regular_holiday_multiplier);
    const gross =
        basic +
        regularOt +
        holiday130 +
        holiday200 +
        amount(values.salary_adjustment) +
        amount(values.allowance);
    const lateDeduction = hourly * amount(values.late_hours);
    const absenceDeduction = daily * amount(values.absent_days);
    const deductions =
        lateDeduction +
        absenceDeduction +
        amount(values.sss_contribution) +
        amount(values.philhealth_contribution) +
        amount(values.pagibig_contribution) +
        amount(values.withholding_tax) +
        amount(values.other_deduction);
    return {
        basic,
        regularOt,
        holiday130,
        holiday200,
        lateDeduction,
        absenceDeduction,
        gross,
        deductions,
        net: gross - deductions,
    };
};
const results = computed(() =>
    props.payroll.items.map((item, indexValue) =>
        calculate(item, form.items[indexValue]),
    ),
);
const totals = computed(() =>
    results.value.reduce(
        (total, row) => ({
            gross: total.gross + row.gross,
            deductions: total.deductions + row.deductions,
            net: total.net + row.net,
        }),
        { gross: 0, deductions: 0, net: 0 },
    ),
);
const payrollDaysBasis = computed(() =>
    amount(props.payroll.items[0]?.payroll_days_basis ?? 13),
);
const monthlyWorkdaysDivisor = computed(() =>
    amount(props.payroll.items[0]?.monthly_workdays_divisor ?? 26),
);
const money = (value: number) =>
    value.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
const date = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        dateStyle: 'medium',
    });
const save = () =>
    form.put(update.url(props.payroll.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    });
const finalizePayroll = () => {
    if (window.confirm('Finalize and lock this payroll?'))
        router.patch(finalize.url(props.payroll.id));
};
const reopenPayroll = () => {
    if (window.confirm('Reopen this payroll for editing?'))
        router.patch(reopen.url(props.payroll.id));
};
</script>

<template>
    <Head :title="`Payroll ${date(payroll.pay_date)}`" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`${payroll.company.company_name} payroll`"
            :description="`${date(payroll.period_start)} – ${date(payroll.period_end)} · Pay date ${date(payroll.pay_date)}`"
        >
            <template #actions>
                <StatusBadge
                    :tone="
                        payroll.status === 'finalized' ? 'success' : 'warning'
                    "
                    >{{ payroll.status }}</StatusBadge
                >
                <Button
                    variant="outline"
                    @click="router.visit(index.url())"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back
                </Button>
                <span
                    v-if="
                        form.isDirty &&
                        payroll.items.length &&
                        (can.printPayslip || can.export)
                    "
                    class="text-xs font-medium text-amber-600 dark:text-amber-400"
                >
                    Save payroll first
                </span>
                <Button
                    v-if="
                        can.printPayslip && payroll.items.length && form.isDirty
                    "
                    variant="outline"
                    disabled
                    title="Save payroll changes before printing."
                >
                    <Printer /> Print all
                </Button>
                <Button
                    v-else-if="can.printPayslip && payroll.items.length"
                    variant="outline"
                    as-child
                >
                    <a
                        :href="payslipBulk.url(payroll.id)"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer /> Print all
                    </a>
                </Button>
                <Button
                    v-if="can.export && payroll.items.length && form.isDirty"
                    variant="outline"
                    disabled
                    title="Save payroll changes before exporting."
                >
                    <Download /> Export Excel CSV
                </Button>
                <Button
                    v-else-if="can.export && payroll.items.length"
                    variant="outline"
                    as-child
                >
                    <a :href="payrollExport.url(payroll.id)">
                        <Download /> Export Excel CSV
                    </a>
                </Button>
                <Button
                    v-if="can.reopen"
                    variant="outline"
                    @click="reopenPayroll"
                    ><UnlockKeyhole /> Reopen</Button
                >
                <Button v-if="can.finalize" @click="finalizePayroll"
                    ><LockKeyhole /> Finalize</Button
                >
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Gross payroll</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(totals.gross) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Deductions</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(totals.deductions) }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Net payroll</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(totals.net) }}
                </p>
            </div>
        </div>

        <!-- <div
            class="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground"
        >
            Payroll basis: {{ payrollDaysBasis }} days (monthly divisor:
            {{ monthlyWorkdaysDivisor }}). Worked defaults to the 13-day payroll
            basis; absences are limited to {{ weekdayCount }} actual
            Monday–Friday workdays in this cutoff. Regular OT is 125%; holiday
            columns use 130% OT and 200% OT.
        </div> -->
        <InputError :message="form.errors.items" />

        <form @submit.prevent="save">
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1950px] table-fixed text-xs">
                        <thead
                            class="border-b bg-muted/40 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    class="sticky left-0 z-10 w-48 bg-muted px-2 py-2 font-medium"
                                >
                                    Employee
                                </th>
                                <th class="w-24 px-2 py-3 font-medium">
                                    Worked
                                </th>
                                <th
                                    class="w-32 px-3 py-3 text-right font-medium"
                                >
                                    Basic Pay
                                </th>
                                <th class="w-24 px-2 py-3 font-medium">
                                    OT Pay
                                </th>
                                <th class="w-24 px-2 py-3 font-medium">
                                    130% OT
                                </th>
                                <th class="w-24 px-2 py-3 font-medium">
                                    200% OT
                                </th>
                                <th class="w-24 px-2 py-3 font-medium">Late</th>
                                <th class="w-24 px-2 py-3 font-medium">
                                    Absent
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    Sal. Adj.
                                </th>
                                <th
                                    class="w-32 px-3 py-3 text-right font-medium"
                                >
                                    Gross
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">SSS</th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    PhilHealth
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    Pag-IBIG
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    W/Tax
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    Deduction
                                </th>
                                <th class="w-28 px-2 py-3 font-medium">
                                    Allowance
                                </th>
                                <th
                                    class="w-32 px-3 py-3 text-right font-medium"
                                >
                                    Net Pay
                                </th>
                                <th class="w-24 px-3 py-3">Payslip</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="(item, rowIndex) in payroll.items"
                                :key="item.id"
                                class="align-top hover:bg-muted/20"
                            >
                                <td
                                    class="sticky left-0 z-10 w-48 bg-card px-2 py-2"
                                >
                                    <p class="truncate text-sm font-medium">
                                        {{ item.employee_name }}
                                    </p>
                                    <p
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ item.employee_number }} ·
                                        {{ item.job_title }}
                                    </p>
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex].days_worked
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :max="payrollDaysBasis"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${rowIndex}.days_worked`
                                            ]
                                        "
                                        class="w-28 text-xs"
                                    />
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-medium tabular-nums"
                                >
                                    {{ money(results[rowIndex].basic) }}
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .regular_ot_hours
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <p
                                        class="mt-1 text-right text-xs text-muted-foreground"
                                    >
                                        {{ money(results[rowIndex].regularOt) }}
                                    </p>
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .special_holiday_hours
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <p
                                        class="mt-1 text-right text-xs text-muted-foreground"
                                    >
                                        {{
                                            money(results[rowIndex].holiday130)
                                        }}
                                    </p>
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .regular_holiday_hours
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <p
                                        class="mt-1 text-right text-xs text-muted-foreground"
                                    >
                                        {{
                                            money(results[rowIndex].holiday200)
                                        }}
                                    </p>
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex].late_hours
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <p
                                        class="mt-1 text-right text-xs text-destructive"
                                    >
                                        {{
                                            money(
                                                results[rowIndex].lateDeduction,
                                            )
                                        }}
                                    </p>
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex].absent_days
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :max="weekdayCount"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                    <p
                                        class="mt-1 text-right text-xs text-destructive"
                                    >
                                        {{
                                            money(
                                                results[rowIndex]
                                                    .absenceDeduction,
                                            )
                                        }}
                                    </p>
                                    <InputError
                                        :message="
                                            form.errors[
                                                `items.${rowIndex}.absent_days`
                                            ]
                                        "
                                        class="w-28 text-xs"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .salary_adjustment
                                        "
                                        type="number"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-semibold tabular-nums"
                                >
                                    {{ money(results[rowIndex].gross) }}
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .sss_contribution
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .philhealth_contribution
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex]
                                                .pagibig_contribution
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex].withholding_tax
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="
                                            form.items[rowIndex].other_deduction
                                        "
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td class="px-2 py-2">
                                    <Input
                                        v-model="form.items[rowIndex].allowance"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        :disabled="!can.edit"
                                        class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                    />
                                </td>
                                <td
                                    class="px-3 py-3 text-right font-semibold tabular-nums"
                                >
                                    {{ money(results[rowIndex].net) }}
                                </td>
                                <td class="px-3 py-2">
                                    <Button
                                        v-if="
                                            can.viewPayslip || can.printPayslip
                                        "
                                        variant="outline"
                                        size="sm"
                                        as-child
                                        ><a
                                            :href="
                                                payslipShow.url({
                                                    payroll: payroll.id,
                                                    payrollItem: item.id,
                                                })
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            ><FileText /> PDF</a
                                        ></Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="!payroll.items.length"
                    class="p-8 text-center text-sm text-muted-foreground"
                >
                    No active semi-monthly employees were found for this
                    company.
                </div>
            </div>
            <div
                v-if="can.edit && payroll.items.length"
                class="mt-4 flex justify-end"
            >
                <Button type="submit" :disabled="form.processing"
                    ><Save /> Save payroll</Button
                >
            </div>
        </form>
    </div>
</template>
