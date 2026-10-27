<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Download,
    FileText,
    LockKeyhole,
    Printer,
    Save,
    UnlockKeyhole,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
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
    employment_type: 'regular' | 'probationary' | 'contractual';
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
    payroll_group: 'regular' | 'contractual' | 'mixed';
    cutoff_type: 'first_half' | 'second_half';
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
const payrollGroupLabel = computed(() =>
    props.payroll.payroll_group === 'contractual'
        ? 'Contractual'
        : props.payroll.payroll_group === 'mixed'
          ? 'Mixed historical'
          : 'Regular',
);
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
 
const payrollViews = [
    { value: 'summary', label: 'Summary' },
    { value: 'attendance', label: 'Attendance & OT' },
    { value: 'earnings', label: 'Earnings' },
    { value: 'deductions', label: 'Deductions' },
] as const;
type PayrollView = (typeof payrollViews)[number]['value'];
 
const payrollViewStorageKey = 'payroll-table-views';
const activeViews = ref<PayrollView[]>(['summary']);
const isViewActive = (view: PayrollView) => activeViews.value.includes(view);
const areAllViewsActive = computed(() =>
    payrollViews.every((view) => isViewActive(view.value)),
);
const toggleView = (view: PayrollView) => {
    if (isViewActive(view)) {
        if (activeViews.value.length > 1) {
            activeViews.value = activeViews.value.filter(
                (activeView) => activeView !== view,
            );
        }
 
        return;
    }
 
    activeViews.value = [...activeViews.value, view];
};
const selectAllViews = () => {
    activeViews.value = payrollViews.map((view) => view.value);
};
 
onMounted(() => {
    const savedViews = window.localStorage.getItem(payrollViewStorageKey);
 
    if (!savedViews) {
        return;
    }
 
    try {
        const parsedViews = JSON.parse(savedViews) as unknown;
 
        if (!Array.isArray(parsedViews)) {
            return;
        }
 
        const validViews = parsedViews.filter(
            (view): view is PayrollView =>
                typeof view === 'string' &&
                payrollViews.some((option) => option.value === view),
        );
 
        if (validViews.length > 0) {
            activeViews.value = [...new Set(validViews)];
        }
    } catch {
        window.localStorage.removeItem(payrollViewStorageKey);
    }
});
 
watch(activeViews, (views) => {
    window.localStorage.setItem(payrollViewStorageKey, JSON.stringify(views));
});
 
const amount = (value: string | number) => Number(value || 0);
const calculate = (item: PayrollItem, values: FormItem) => {
    // Basic salary
    const monthlyBasic = amount(item.monthly_basic_salary);
    const basic = monthlyBasic / 2;
 
    // Allowance
    const allowance = amount(values.allowance);
    const monthlyAllowance = allowance * 2;
 
    // Payroll divisors
    const monthlyDivisor = amount(item.monthly_workdays_divisor);
    const payrollDaysBasis = amount(item.payroll_days_basis);
    const hoursPerDay = amount(item.hours_per_day);
 
    // Daily and hourly rates (including allowance)
    const daily =
        monthlyDivisor > 0
            ? (monthlyBasic + monthlyAllowance) / monthlyDivisor
            : 0;
 
    const hourly = hoursPerDay > 0 ? daily / hoursPerDay : 0;
 
    // Overtime pay
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
 
    // Attendance deductions
    const lateDeduction = hourly * amount(values.late_hours);
    const absenceDeduction = daily * amount(values.absent_days);
 
    // Salary adjustments
    const salaryAdjustment = amount(values.salary_adjustment);
 
    // Gross pay (including allowance)
    const gross =
        basic +
        allowance +
        regularOt +
        holiday130 +
        holiday200 +
        salaryAdjustment;
 
    // Government and other deductions
    const governmentDeductions =
        amount(values.sss_contribution) +
        amount(values.philhealth_contribution) +
        amount(values.pagibig_contribution) +
        amount(values.withholding_tax) +
        amount(values.other_deduction);
 
    const deductions = lateDeduction + absenceDeduction + governmentDeductions;
 
    // Net pay
    const net = gross - deductions;
 
    return {
        basic,
        allowance,
        daily,
        hourly,
        payrollDaysBasis,
        regularOt,
        holiday130,
        holiday200,
        lateDeduction,
        absenceDeduction,
        gross,
        deductions,
        net,
    };
};
type CalcResult = ReturnType<typeof calculate>;
 
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
const money = (value: number) =>
    value.toLocaleString('en-PH', { style: 'currency', currency: 'PHP' });
const date = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        dateStyle: 'medium',
    });
 
/**
 * Table columns.
 * - `w` is the exact pixel width (used by <colgroup>, so header and body
 *   can never drift apart).
 * - `views` = which view buttons show the column. No `views` = always shown.
 * - kind 'input'   -> editable field (+ optional computed `sub` amount below it)
 * - kind 'calc'    -> read-only computed amount
 * - kind 'payslip' -> PDF button
 */
type Column = {
    id: string;
    label: string;
    w: number;
    views?: PayrollView[];
    kind: 'input' | 'calc' | 'payslip';
    field?: InputField;
    calc?: keyof CalcResult;
    sub?: keyof CalcResult;
    subTone?: 'muted' | 'destructive';
    tone?: 'default' | 'destructive';
    bold?: boolean;
    allowNegative?: boolean;
};
 
const columns: Column[] = [
    { id: 'payslip', label: 'Payslip', w: 144, kind: 'payslip' },
    {
        id: 'days_worked',
        label: 'Worked',
        w: 96,
        views: ['attendance'],
        kind: 'input',
        field: 'days_worked',
    },
    {
        id: 'basic',
        label: 'Basic Pay',
        w: 104,
        views: ['summary', 'earnings'],
        kind: 'calc',
        calc: 'basic',
        bold: true,
    },
    {
        id: 'regular_ot',
        label: 'OT Pay',
        w: 96,
        views: ['attendance', 'earnings'],
        kind: 'input',
        field: 'regular_ot_hours',
        sub: 'regularOt',
        subTone: 'muted',
    },
    {
        id: 'ot130',
        label: '130% OT',
        w: 96,
        views: ['attendance', 'earnings'],
        kind: 'input',
        field: 'special_holiday_hours',
        sub: 'holiday130',
        subTone: 'muted',
    },
    {
        id: 'ot200',
        label: '200% OT',
        w: 96,
        views: ['attendance', 'earnings'],
        kind: 'input',
        field: 'regular_holiday_hours',
        sub: 'holiday200',
        subTone: 'muted',
    },
    {
        id: 'late',
        label: 'Late',
        w: 96,
        views: ['attendance'],
        kind: 'input',
        field: 'late_hours',
        sub: 'lateDeduction',
        subTone: 'destructive',
    },
    {
        id: 'absent',
        label: 'Absent',
        w: 96,
        views: ['attendance'],
        kind: 'input',
        field: 'absent_days',
        sub: 'absenceDeduction',
        subTone: 'destructive',
    },
    {
        id: 'salary_adjustment',
        label: 'Sal. Adjustment',
        w: 112,
        views: ['earnings'],
        kind: 'input',
        field: 'salary_adjustment',
        allowNegative: true,
    },
    {
        id: 'allowance',
        label: 'Allowance',
        w: 112,
        views: ['earnings'],
        kind: 'input',
        field: 'allowance',
    },
    {
        id: 'gross',
        label: 'Gross Pay',
        w: 112,
        views: ['summary', 'earnings'],
        kind: 'calc',
        calc: 'gross',
        bold: true,
    },
    {
        id: 'sss',
        label: 'SSS',
        w: 112,
        views: ['deductions'],
        kind: 'input',
        field: 'sss_contribution',
    },
    {
        id: 'philhealth',
        label: 'PhilHealth',
        w: 112,
        views: ['deductions'],
        kind: 'input',
        field: 'philhealth_contribution',
    },
    {
        id: 'pagibig',
        label: 'Pag-IBIG',
        w: 112,
        views: ['deductions'],
        kind: 'input',
        field: 'pagibig_contribution',
    },
    {
        id: 'wtax',
        label: 'W/Tax',
        w: 112,
        views: ['deductions'],
        kind: 'input',
        field: 'withholding_tax',
    },
    {
        id: 'other',
        label: 'Deduction',
        w: 112,
        views: ['deductions'],
        kind: 'input',
        field: 'other_deduction',
    },
    {
        id: 'total_ded',
        label: 'Total Deductions',
        w: 112,
        views: ['summary', 'deductions'],
        kind: 'calc',
        calc: 'deductions',
        tone: 'destructive',
        bold: true,
    },
    {
        id: 'net',
        label: 'Net Pay',
        w: 120,
        kind: 'calc',
        calc: 'net',
        bold: true,
    },
];
 
const EMPLOYEE_COLUMN_WIDTH = 192;
 
const visibleColumns = computed(() =>
    columns.filter(
        (column) =>
            !column.views || column.views.some((view) => isViewActive(view)),
    ),
);
const tableWidth = computed(
    () =>
        `${visibleColumns.value.reduce((sum, column) => sum + column.w, EMPLOYEE_COLUMN_WIDTH)}px`,
);
 
const maxFor = (column: Column) => {
    if (column.field === 'days_worked') {
        return payrollDaysBasis.value;
    }
 
    if (column.field === 'absent_days') {
        return props.weekdayCount;
    }
 
    return undefined;
};
const errorFor = (rowIndex: number, column: Column) =>
    column.field
        ? form.errors[`items.${rowIndex}.${column.field}` as keyof typeof form.errors]
        : undefined;
const subClass = (column: Column) =>
    column.subTone === 'destructive'
        ? 'text-destructive'
        : 'text-muted-foreground';
const calcClass = (column: Column) => [
    column.bold ? 'font-semibold' : 'font-medium',
    column.tone === 'destructive' ? 'text-destructive' : '',
];
 
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
            :title="`${payroll.company.company_name} ${payrollGroupLabel} payroll`"
            :description="`${date(payroll.period_start)} – ${date(payroll.period_end)} · Pay date ${date(payroll.pay_date)}`"
        >
            <template #actions>
                <StatusBadge
                    :tone="
                        payroll.status === 'finalized' ? 'success' : 'warning'
                    "
                    >{{ payroll.status }}</StatusBadge
                >
                <Button variant="outline" @click="router.visit(index.url())">
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
 
        <InputError :message="form.errors.items" />
 
        <form @submit.prevent="save">
            <div
                class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border bg-muted/20 p-2"
            >
                <span class="px-1 text-sm font-medium text-muted-foreground">
                    View
                </span>
                <Button
                    v-for="view in payrollViews"
                    :key="view.value"
                    type="button"
                    size="sm"
                    :variant="isViewActive(view.value) ? 'default' : 'outline'"
                    :aria-pressed="isViewActive(view.value)"
                    @click="toggleView(view.value)"
                >
                    {{ view.label }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="areAllViewsActive ? 'default' : 'outline'"
                    :aria-pressed="areAllViewsActive"
                    @click="selectAllViews"
                >
                    All
                </Button>
            </div>
 
            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="overflow-x-auto">
                    <table
                        class="table-fixed text-xs"
                        :style="{ width: tableWidth, minWidth: '100%' }"
                    >
                        <colgroup>
                            <col :style="{ width: `${EMPLOYEE_COLUMN_WIDTH}px` }" />
                            <col
                                v-for="column in visibleColumns"
                                :key="column.id"
                                :style="{ width: `${column.w}px` }"
                            />
                        </colgroup>
 
                        <thead
                            class="border-b bg-muted text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    class="sticky left-0 z-10 bg-muted px-2 py-3 text-left font-medium"
                                >
                                    Employee
                                </th>
                                <th
                                    v-for="column in visibleColumns"
                                    :key="column.id"
                                    class="px-2 py-3 font-medium whitespace-nowrap"
                                    :class="
                                        column.kind === 'payslip'
                                            ? 'text-left'
                                            : 'text-right'
                                    "
                                >
                                    {{ column.label }}
                                </th>
                            </tr>
                        </thead>
 
                        <tbody class="divide-y">
                            <tr
                                v-for="(item, rowIndex) in payroll.items"
                                :key="item.id"
                                class="align-top hover:bg-muted/20"
                            >
                                <td class="sticky left-0 z-10 bg-card px-2 py-2">
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
 
                                <td
                                    v-for="column in visibleColumns"
                                    :key="column.id"
                                    class="px-2 py-2"
                                    :class="
                                        column.kind === 'calc'
                                            ? 'py-3 text-right tabular-nums'
                                            : ''
                                    "
                                >
                                    <!-- Payslip button -->
                                    <template v-if="column.kind === 'payslip'">
                                        <Button
                                            v-if="
                                                can.viewPayslip ||
                                                can.printPayslip
                                            "
                                            variant="outline"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            as-child
                                        >
                                            <a
                                                :href="
                                                    payslipShow.url({
                                                        payroll: payroll.id,
                                                        payrollItem: item.id,
                                                    })
                                                "
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <FileText />
                                                {{
                                                    item.employment_type ===
                                                    'contractual'
                                                        ? 'Voucher PDF'
                                                        : 'Payslip PDF'
                                                }}
                                            </a>
                                        </Button>
                                    </template>
 
                                    <!-- Editable field -->
                                    <template
                                        v-else-if="column.kind === 'input'"
                                    >
                                        <Input
                                            v-model="
                                                form.items[rowIndex][
                                                    column.field!
                                                ]
                                            "
                                            type="number"
                                            :min="
                                                column.allowNegative
                                                    ? undefined
                                                    : 0
                                            "
                                            :max="maxFor(column)"
                                            step="0.01"
                                            :disabled="!can.edit"
                                            class="h-7 w-full min-w-0 px-1.5 text-right text-xs tabular-nums"
                                        />
                                        <p
                                            v-if="column.sub"
                                            class="mt-1 text-right text-xs tabular-nums"
                                            :class="subClass(column)"
                                        >
                                            {{
                                                money(
                                                    results[rowIndex][
                                                        column.sub
                                                    ],
                                                )
                                            }}
                                        </p>
                                        <InputError
                                            :message="errorFor(rowIndex, column)"
                                            class="text-xs"
                                        />
                                    </template>
 
                                    <!-- Computed amount -->
                                    <template v-else>
                                        <span :class="calcClass(column)">
                                            {{
                                                money(
                                                    results[rowIndex][
                                                        column.calc!
                                                    ],
                                                )
                                            }}
                                        </span>
                                    </template>
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