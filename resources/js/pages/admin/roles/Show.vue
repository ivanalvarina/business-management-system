<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { edit, index } from '@/routes/admin/roles';

type Role = {
    id: number;
    name: string;
    users_count: number;
    permissions: string[];
};

const props = defineProps<{
    role: Role;
    can: {
        edit: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Roles & Permissions', href: index() },
            { title: 'Role details', href: '#' },
        ],
    },
});

const deleteRole = () => {
    if (!confirm('Delete this role?')) {
        return;
    }

    router.visit(RoleController.destroy(props.role.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="role.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="role.name"
            description="Role permission details and usage."
        >
            <template #actions>
                <Button
                    v-if="can.delete"
                    variant="destructive"
                    @click="deleteRole"
                >
                    Delete
                </Button>
                <Button v-if="can.edit" as-child>
                    <Link :href="edit(role.id)">Edit</Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardHeader>
                <CardTitle>Role details</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-sm text-muted-foreground">Assigned users</p>
                    <StatusBadge
                        :tone="role.users_count > 0 ? 'success' : 'neutral'"
                    >
                        {{ role.users_count }}
                    </StatusBadge>
                </div>
                <div class="md:col-span-2">
                    <p class="text-sm text-muted-foreground">Permissions</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <StatusBadge
                            v-for="permission in role.permissions"
                            :key="permission"
                        >
                            {{ permission }}
                        </StatusBadge>
                        <span
                            v-if="role.permissions.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            No permissions assigned
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
