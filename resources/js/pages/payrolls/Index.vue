<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Plus, Trash2, WalletCards } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { create, destroy, index, show } from '@/routes/payrolls';

type Company = { id: number; company_code: string; company_name: string };
type Payroll = {
    id: number;
    company: Company;
    payroll_group: 'regular' | 'contractual' | 'mixed';
    period_start: string;
    period_end: string;
    pay_date: string;
    status: 'draft' | 'finalized';
    items_count: number;
    items_sum_gross_pay: string | null;
    items_sum_net_pay: string | null;
};

const props = defineProps<{
    companies: Company[];
    filters: { company_id: number | null };
    payrolls: { data: Payroll[]; total: number };
    can: { create: boolean; delete: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payroll', href: index() }] },
});

const changeCompany = (event: Event) => {
    router.get(
        index.url(),
        { company_id: Number((event.target as HTMLSelectElement).value) },
        { preserveState: true, replace: true },
    );
};
const date = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        dateStyle: 'medium',
    });
const money = (value: string | null) =>
    Number(value ?? 0).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
    });
const payrollGroup = (value: Payroll['payroll_group']) =>
    value === 'contractual'
        ? 'Contractual'
        : value === 'mixed'
          ? 'Mixed (historical)'
          : 'Regular';

const deletePayroll = (payroll: Payroll) => {
    if (
        !window.confirm(
            `Delete the ${date(payroll.period_start)} – ${date(payroll.period_end)} payroll run? All employee payroll entries in this run will be permanently deleted.`,
        )
    ) {
        return;
    }

    router.visit(destroy(payroll.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Payroll" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Payroll runs"
            description="Prepare semi-monthly payroll and employee payslips."
        >
            <template #actions>
                <Button v-if="can.create" as-child
                    ><Link :href="create()"><Plus /> New payroll</Link></Button
                >
            </template>
        </PageHeader>

        <select
            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm md:w-72"
            :value="filters.company_id ?? ''"
            @change="changeCompany"
        >
            <option
                v-for="company in companies"
                :key="company.id"
                :value="company.id"
            >
                {{ company.company_code }} — {{ company.company_name }}
            </option>
        </select>

        <div
            v-if="payrolls.data.length"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="border-b bg-muted/40 text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Cutoff</th>
                            <th class="px-4 py-3 font-medium">
                                Employee group
                            </th>
                            <th class="px-4 py-3 font-medium">Pay date</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Employees
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Gross
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Net
                            </th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="w-24 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="payroll in payrolls.data"
                            :key="payroll.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ date(payroll.period_start) }} –
                                {{ date(payroll.period_end) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ payrollGroup(payroll.payroll_group) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ date(payroll.pay_date) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ payroll.items_count }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ money(payroll.items_sum_gross_pay) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right font-medium tabular-nums"
                            >
                                {{ money(payroll.items_sum_net_pay) }}
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :tone="
                                        payroll.status === 'finalized'
                                            ? 'success'
                                            : 'warning'
                                    "
                                    >{{ payroll.status }}</StatusBadge
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        as-child
                                    >
                                        <Link
                                            :href="show(payroll.id)"
                                            aria-label="Open payroll"
                                        >
                                            <Eye />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="
                                            can.delete &&
                                            payroll.status === 'draft'
                                        "
                                        variant="ghost"
                                        size="icon"
                                        class="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        aria-label="Delete payroll run"
                                        @click="deletePayroll(payroll)"
                                    >
                                        <Trash2 />
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
            title="No payroll runs"
            description="Create the first payroll for this company."
            :icon="WalletCards"
        >
            <Button v-if="can.create" size="sm" as-child
                ><Link :href="create()"><Plus /> New payroll</Link></Button
            >
        </EmptyState>
    </div>
</template>
