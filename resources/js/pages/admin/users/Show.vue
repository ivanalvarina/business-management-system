<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/admin/users';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    email_verified_at: string | null;
    created_at: string | null;
    roles: string[];
};

const props = defineProps<{
    managedUser: ManagedUser;
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'User details', href: '#' },
        ],
    },
});

const toggleActive = () => {
    router.visit(
        props.managedUser.is_active
            ? UserController.deactivate(props.managedUser.id)
            : UserController.activate(props.managedUser.id),
        { preserveScroll: true },
    );
};

const sendReset = () => {
    router.visit(UserController.sendPasswordResetLink(props.managedUser.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="managedUser.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="managedUser.name" :description="managedUser.email">
            <template #actions>
                <Button v-if="can.edit" variant="outline" @click="sendReset">
                    Send reset link
                </Button>
                <Button v-if="can.edit" variant="outline" @click="toggleActive">
                    {{ managedUser.is_active ? 'Deactivate' : 'Activate' }}
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(managedUser.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <CardTitle>Account details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">Status</p>
                    <StatusBadge
                        :tone="managedUser.is_active ? 'success' : 'warning'"
                    >
                        {{ managedUser.is_active ? 'Active' : 'Inactive' }}
                    </StatusBadge>
                </div>
                <div>
                    <p class="text-sm text-muted-foreground">Created</p>
                    <p class="font-medium">
                        {{ managedUser.created_at ?? 'Unknown' }}
                    </p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-muted-foreground">Roles</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <StatusBadge
                            v-for="role in managedUser.roles"
                            :key="role"
                        >
                            {{ role }}
                        </StatusBadge>
                        <span
                            v-if="managedUser.roles.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No roles assigned
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
