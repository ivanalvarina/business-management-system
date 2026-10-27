<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/admin/users';

type RoleOption = {
    id: number;
    name: string;
};

type CompanyOption = {
    id: number;
    company_code: string;
    company_name: string;
};

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: number[];
    company_ids: number[];
};

const props = defineProps<{
    managedUser: ManagedUser;
    roles: RoleOption[];
    companies: CompanyOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Edit user', href: '#' },
        ],
    },
});

const form = useForm({
    name: props.managedUser.name,
    email: props.managedUser.email,
    is_active: props.managedUser.is_active,
    roles: [...props.managedUser.roles],
    company_ids: [...props.managedUser.company_ids],
});

const toggleRole = (roleId: number, checked: boolean) => {
    form.roles = checked
        ? [...form.roles, roleId]
        : form.roles.filter((id) => id !== roleId);
};

const toggleCompany = (companyId: number, checked: boolean) => {
    form.company_ids = checked
        ? [...form.company_ids, companyId]
        : form.company_ids.filter((id) => id !== companyId);
};

const submit = () => form.submit(UserController.update(props.managedUser.id));
</script>

<template>
    <Head title="Edit user" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Edit user"
            description="Update account details, activation status, and role assignment."
        />

        <Card>
            <CardContent>
                <form class="max-w-7xl space-y-6" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            autocomplete="name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.is_active"
                            @update:model-value="
                                form.is_active = Boolean($event)
                            "
                        />
                        Active account
                    </label>

                    <div class="space-y-3">
                        <div>
                            <Label>Company access</Label>
                            <p class="text-sm text-muted-foreground">
                                The user will appear as a board member only in
                                the selected companies.
                            </p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="company in companies"
                                :key="company.id"
                                class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 text-sm"
                            >
                                <Checkbox
                                    class="mt-0.5"
                                    :model-value="
                                        form.company_ids.includes(company.id)
                                    "
                                    @update:model-value="
                                        toggleCompany(
                                            company.id,
                                            Boolean($event),
                                        )
                                    "
                                />
                                <span class="min-w-0">
                                    <span class="block font-medium">{{
                                        company.company_name
                                    }}</span>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ company.company_code }}</span
                                    >
                                </span>
                            </label>
                        </div>
                        <InputError :message="form.errors.company_ids" />
                    </div>

                    <div class="space-y-3">
                        <Label>Roles</Label>
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="role in roles"
                                :key="role.id"
                                class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                            >
                                <Checkbox
                                    :model-value="form.roles.includes(role.id)"
                                    @update:model-value="
                                        toggleRole(role.id, Boolean($event))
                                    "
                                />
                                {{ role.name }}
                            </label>
                        </div>
                        <InputError :message="form.errors.roles" />
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing"
                            >Save changes</Button
                        >
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
