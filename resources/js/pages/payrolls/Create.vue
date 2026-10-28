<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Users } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, store } from '@/routes/payrolls';

type PayrollGroup = 'regular' | 'contractual';
type CutoffType = 'first_half' | 'second_half';
type Company = {
    id: number;
    company_code: string;
    company_name: string;
    regular_payroll_employees_count: number;
    contractual_payroll_employees_count: number;
    payroll_holidays: { date: string; name: string }[];
};

const props = defineProps<{
    companies: Company[];
    defaultCompanyId: number | null;
    defaultMonth: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Payroll', href: index() },
            { title: 'Create', href: create() },
        ],
    },
});

const form = useForm<{
    company_id: number | null;
    payroll_month: string;
    payroll_group: PayrollGroup;
    cutoff_type: CutoffType;
}>({
    company_id: props.defaultCompanyId,
    payroll_month: props.defaultMonth,
    payroll_group: 'regular',
    cutoff_type: 'first_half',
});

const selectedCompany = computed(() =>
    props.companies.find((company) => company.id === Number(form.company_id)),
);
const employeeCount = computed(() =>
    form.payroll_group === 'contractual'
        ? (selectedCompany.value?.contractual_payroll_employees_count ?? 0)
        : (selectedCompany.value?.regular_payroll_employees_count ?? 0),
);
const employeeGroupLabel = computed(() =>
    form.payroll_group === 'contractual'
        ? 'contractual'
        : 'regular and probationary',
);
const toDateKey = (date: Date) =>
    `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(
        date.getDate(),
    ).padStart(2, '0')}`;

const payrollDates = computed(() => {
    const [year, month] = form.payroll_month.split('-').map(Number);

    if (!year || !month) {
        return null;
    }

    const isFirstHalf = form.cutoff_type === 'first_half';
    const periodStart = new Date(year, month - 1, isFirstHalf ? 1 : 16);
    const periodEnd = isFirstHalf
        ? new Date(year, month - 1, 15)
        : new Date(year, month, 0);
    const scheduledPayDate =
        form.payroll_group === 'regular'
            ? isFirstHalf
                ? new Date(year, month - 1, 25)
                : new Date(year, month, 10)
            : isFirstHalf
              ? new Date(year, month - 1, 15)
              : new Date(year, month, 0);
    const payDate = new Date(scheduledPayDate);
    const holidayDates = new Set(
        selectedCompany.value?.payroll_holidays.map((holiday) => holiday.date) ??
            [],
    );

    while (
        payDate.getDay() === 0 ||
        payDate.getDay() === 6 ||
        holidayDates.has(toDateKey(payDate))
    ) {
        payDate.setDate(payDate.getDate() + 1);
    }

    return { periodStart, periodEnd, scheduledPayDate, payDate };
});

const formatDate = (date: Date) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(date);
const cutoffLabel = computed(() => {
    if (!payrollDates.value) {
        return 'Select a payroll month';
    }

    return `${formatDate(payrollDates.value.periodStart)} – ${formatDate(payrollDates.value.periodEnd)}`;
});
const computedPayDate = computed(() =>
    payrollDates.value
        ? formatDate(payrollDates.value.payDate)
        : 'Select a payroll month',
);
const wasPayDateAdjusted = computed(
    () =>
        payrollDates.value !== null &&
        payrollDates.value.payDate.getTime() !==
            payrollDates.value.scheduledPayDate.getTime(),
);
const submit = () => form.post(store.url());
</script>

<template>
    <Head title="New payroll" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New payroll"
            description="Choose an employee group and cutoff. The period and pay date are calculated automatically."
            :icon="CalendarDays"
        />

        <form
            class="max-w-8xl space-y-6 rounded-xl border bg-card p-6"
            @submit.prevent="submit"
        >
            <div class="space-y-2">
                <label for="company" class="text-sm font-medium">Company</label>
                <select
                    id="company"
                    v-model="form.company_id"
                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option
                        v-for="company in companies"
                        :key="company.id"
                        :value="company.id"
                    >
                        {{ company.company_code }} — {{ company.company_name }}
                    </option>
                </select>
                <InputError :message="form.errors.company_id" />
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <div class="space-y-2">
                    <label for="month" class="text-sm font-medium">
                        Payroll month
                    </label>
                    <Input
                        id="month"
                        v-model="form.payroll_month"
                        type="month"
                    />
                    <InputError :message="form.errors.payroll_month" />
                </div>

                <div class="space-y-2">
                    <label for="payroll-group" class="text-sm font-medium">
                        Employee group
                    </label>
                    <select
                        id="payroll-group"
                        v-model="form.payroll_group"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="regular">
                            Regular (includes probationary)
                        </option>
                        <option value="contractual">Contractual</option>
                    </select>
                    <InputError :message="form.errors.payroll_group" />
                </div>

                <div class="space-y-2">
                    <label for="cutoff-type" class="text-sm font-medium">
                        Cutoff period
                    </label>
                    <select
                        id="cutoff-type"
                        v-model="form.cutoff_type"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option value="first_half">
                            1–15
                            {{
                                form.payroll_group === 'regular'
                                    ? '→ 25th'
                                    : '→ 15th'
                            }}
                        </option>
                        <option value="second_half">
                            16–month-end
                            {{
                                form.payroll_group === 'regular'
                                    ? '→ 10th next month'
                                    : '→ month-end'
                            }}
                        </option>
                    </select>
                    <InputError :message="form.errors.cutoff_type" />
                </div>

                <div class="space-y-2">
                    <label for="pay-date" class="text-sm font-medium">
                        Pay date
                    </label>
                    <Input
                        id="pay-date"
                        :model-value="computedPayDate"
                        readonly
                        class="bg-muted"
                    />
                    <p
                        v-if="wasPayDateAdjusted"
                        class="text-xs text-muted-foreground"
                    >
                        Moved to the next working day because the scheduled date
                        is a weekend or company holiday.
                    </p>
                </div>
            </div>

            <div
                class="flex flex-col gap-2 rounded-lg border bg-muted/30 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="font-medium">{{ cutoffLabel }}</p>
                    <p class="text-muted-foreground">
                        Monday–Friday attendance only. Weekend and saved company
                        holiday pay dates move to the next working day.
                    </p>
                </div>
                <div class="flex items-center gap-2 font-medium">
                    <Users class="size-4" />
                    {{ employeeCount }} active {{ employeeGroupLabel }}
                    {{ employeeCount === 1 ? 'employee' : 'employees' }}
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()"><ArrowLeft /> Cancel</Link>
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing || employeeCount === 0"
                >
                    Create draft payroll
                </Button>
            </div>
        </form>
    </div>
</template>
