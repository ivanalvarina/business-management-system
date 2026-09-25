<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import CompanyController from '@/actions/App/Http/Controllers/CompanyController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/companies';

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type Company = {
    id: number;
    company_code: string;
    company_name: string;
    trade_name: string | null;
    tin: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    logo_url: string | null;
    status: 'active' | 'inactive';
    user_ids: number[];
};

const props = defineProps<{
    company: Company;
    users: UserOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Companies', href: index() },
            { title: 'Edit company', href: '#' },
        ],
    },
});

const form = useForm({
    company_code: props.company.company_code,
    company_name: props.company.company_name,
    trade_name: props.company.trade_name ?? '',
    tin: props.company.tin ?? '',
    email: props.company.email ?? '',
    phone: props.company.phone ?? '',
    address: props.company.address ?? '',
    logo: null as File | null,
    remove_logo: false,
    status: props.company.status,
    user_ids: [...props.company.user_ids],
});

const toggleUser = (userId: number, checked: boolean) => {
    form.user_ids = checked
        ? [...form.user_ids, userId]
        : form.user_ids.filter((id) => id !== userId);
};

const selectLogo = (event: Event) => {
    const input = event.target as HTMLInputElement;
    form.logo = input.files?.[0] ?? null;
};

const submit = () => {
    form.post(CompanyController.update.form(props.company.id).action, {
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Edit company" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Edit company"
            description="Update company details and user access."
        />

        <Card>
            <CardContent>
                <form class="max-w-4xl space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="company_code">Company code</Label>
                            <Input
                                id="company_code"
                                v-model="form.company_code"
                            />
                            <InputError :message="form.errors.company_code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="company_name">Company name</Label>
                            <Input
                                id="company_name"
                                v-model="form.company_name"
                            />
                            <InputError :message="form.errors.company_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="trade_name">Trade name</Label>
                            <Input id="trade_name" v-model="form.trade_name" />
                            <InputError :message="form.errors.trade_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tin">TIN</Label>
                            <Input id="tin" v-model="form.tin" />
                            <InputError :message="form.errors.tin" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                            />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="address">Address</Label>
                            <textarea
                                id="address"
                                v-model="form.address"
                                class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="form.errors.address" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="logo">Logo</Label>
                            <Input
                                id="logo"
                                type="file"
                                accept="image/*"
                                @change="selectLogo"
                            />
                            <label
                                v-if="company.logo_url"
                                class="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    :model-value="form.remove_logo"
                                    @update:model-value="
                                        form.remove_logo = Boolean($event)
                                    "
                                />
                                Remove current logo
                            </label>
                            <InputError :message="form.errors.logo" />
                        </div>
                        <div class="grid gap-2">
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
                    </div>

                    <div class="space-y-3">
                        <Label>User access</Label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label
                                v-for="user in users"
                                :key="user.id"
                                class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                            >
                                <Checkbox
                                    :model-value="
                                        form.user_ids.includes(user.id)
                                    "
                                    @update:model-value="
                                        toggleUser(user.id, Boolean($event))
                                    "
                                />
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{
                                        user.name
                                    }}</span>
                                    <span
                                        class="block truncate text-muted-foreground"
                                        >{{ user.email }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <InputError :message="form.errors.user_ids" />
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing"
                            >Save changes</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="show(company.id)">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
