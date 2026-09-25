<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/admin/roles';

type Permission = {
    id: number;
    name: string;
};

type PermissionGroup = {
    name: string;
    permissions: Permission[];
};

defineProps<{
    permissionGroups: PermissionGroup[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles & Permissions', href: index() },
            { title: 'Create', href: '#' },
        ],
    },
});

const form = useForm({
    name: '',
    permissions: [] as number[],
});

const togglePermission = (permissionId: number, checked: boolean) => {
    form.permissions = checked
        ? [...form.permissions, permissionId]
        : form.permissions.filter((id) => id !== permissionId);
};

const submit = () => form.submit(RoleController.store());
</script>

<template>
    <Head title="Create role" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Create role"
            description="Create a permission set that can be assigned to users."
        />

        <Card>
            <CardContent>
                <form class="max-w-4xl space-y-6" @submit.prevent="submit">
                    <div class="grid max-w-2xl gap-2">
                        <Label for="name">Role name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-4">
                        <div>
                            <Label>Permissions</Label>
                            <InputError :message="form.errors.permissions" />
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section
                                v-for="group in permissionGroups"
                                :key="group.name"
                                class="rounded-lg border p-4"
                            >
                                <h2 class="text-sm font-semibold">
                                    {{ group.name }}
                                </h2>
                                <div class="mt-3 grid gap-2">
                                    <label
                                        v-for="permission in group.permissions"
                                        :key="permission.id"
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            :model-value="
                                                form.permissions.includes(
                                                    permission.id,
                                                )
                                            "
                                            @update:model-value="
                                                togglePermission(
                                                    permission.id,
                                                    Boolean($event),
                                                )
                                            "
                                        />
                                        {{ permission.name }}
                                    </label>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing">Create role</Button>
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
