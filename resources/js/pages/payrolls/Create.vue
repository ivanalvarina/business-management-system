<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index, store } from '@/routes/payrolls';

type Company = { id: number; company_code: string; company_name: string };
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

const form = useForm({
    company_id: props.defaultCompanyId,
    payroll_month: props.defaultMonth,
    payday: 25,
});
const computedPayDate = computed(() => {
    const [year, month] = form.payroll_month.split('-').map(Number);

    if (!year || !month) {
        return 'Select a payroll month';
    }

    const payDate =
        form.payday === 25
            ? new Date(year, month - 1, 25)
            : new Date(year, month, 10);

    return new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        day: 'numeric',
        year: 'numeric',
    }).format(payDate);
});
const submit = () => form.post(store.url());
</script>

<template>
    <Head title="New payroll" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New payroll"
            description="Choose the company, payroll month, and cutoff period. Active semi-monthly employees will be loaded automatically."
            :icon="CalendarDays"
        />
        <form
            class="max-w-7xl space-y-6 rounded-xl border bg-card p-6"
            @submit.prevent="submit"
        >
            <div class="space-y-2">
                <label for="company" class="text-sm font-medium">Company</label
                ><select
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
                    </option></select
                ><InputError :message="form.errors.company_id" />
            </div>
            <div class="grid gap-5 sm:grid-cols-3">
                <div class="space-y-2">
                    <label for="month" class="text-sm font-medium"
                        >Payroll month</label
                    ><Input
                        id="month"
                        v-model="form.payroll_month"
                        type="month"
                    /><InputError :message="form.errors.payroll_month" />
                </div>
                <div class="space-y-2">
                    <label for="payday" class="text-sm font-medium"
                        >Cutoff period</label
                    ><select
                        id="payday"
                        v-model="form.payday"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                    >
                        <option :value="25">1–15 → Pay date: 25th</option>
                        <option :value="10">
                            16–month-end → Pay date: 10th next month
                        </option></select
                    ><InputError :message="form.errors.payday" />
                </div>
                <div class="space-y-2">
                    <label for="pay-date" class="text-sm font-medium"
                        >Pay date</label
                    ><Input
                        id="pay-date"
                        :model-value="computedPayDate"
                        readonly
                        class="bg-muted"
                    />
                </div>
            </div>
            <!-- <p class="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                Payroll uses the client's fixed 13-day semi-monthly basis and a
                26-day monthly divisor. Actual attendance counts Monday to
                Friday only. Pay dates are the 25th for the 1–15 cutoff and the
                10th of the next month for the 16–month-end cutoff.
            </p> -->
            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child
                    ><Link :href="index()"><ArrowLeft /> Cancel</Link></Button
                ><Button type="submit" :disabled="form.processing"
                    >Create draft payroll</Button
                >
            </div>
        </form>
    </div>
</template>
