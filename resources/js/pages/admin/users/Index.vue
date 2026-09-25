<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/admin/users';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: string[];
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string };
    users: {
        data: ManagedUser[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: index(),
            },
        ],
    },
});

const search = ref(props.filters.search ?? '');

const submitSearch = () => {
    router.get(
        index.url(),
        { search: search.value || undefined },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Manage application user access, activation status, and role assignment."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New user
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="flex max-w-md gap-2"
                    @submit.prevent="submitSearch"
                >
                    <Input v-model="search" placeholder="Search users" />
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-hidden rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Email</th>
                                <th class="px-4 py-3 font-medium">Roles</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="user in users.data" :key="user.id">
                                <td class="px-4 py-4 font-medium">
                                    <Link
                                        :href="show(user.id)"
                                        class="hover:underline"
                                    >
                                        {{ user.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ user.email }}
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <StatusBadge
                                            v-for="role in user.roles"
                                            :key="role"
                                        >
                                            {{ role }}
                                        </StatusBadge>
                                        <span
                                            v-if="user.roles.length === 0"
                                            class="text-muted-foreground"
                                        >
                                            No roles
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            user.is_active
                                                ? 'success'
                                                : 'warning'
                                        "
                                    >
                                        {{
                                            user.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Button
                                        v-if="can.edit"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="edit(user.id)">Edit</Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td
                                    class="px-4 py-10 text-center text-muted-foreground"
                                    colspan="5"
                                >
                                    No users found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of
                        {{ users.total }} users
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="link in users.links" :key="link.label">
                            <Button
                                v-if="link.url"
                                :variant="link.active ? 'default' : 'outline'"
                                size="sm"
                                as-child
                            >
                                <Link :href="link.url" v-html="link.label" />
                            </Button>
                            <Button v-else variant="outline" size="sm" disabled>
                                <span v-html="link.label" />
                            </Button>
                        </template>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
