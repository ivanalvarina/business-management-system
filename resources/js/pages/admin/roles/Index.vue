<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { create, edit, index, show } from '@/routes/admin/roles';

type Role = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    created_at: string | null;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    filters: { search: string };
    roles: {
        data: Role[];
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
                title: 'Roles & Permissions',
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
    <Head title="Roles & Permissions" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Roles & Permissions"
            description="Manage permission sets used to authorize application access."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New role
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
                    <Input v-model="search" placeholder="Search roles" />
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
                                <th class="px-4 py-3 font-medium">Role</th>
                                <th class="px-4 py-3 font-medium">
                                    Permissions
                                </th>
                                <th class="px-4 py-3 font-medium">Users</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="role in roles.data" :key="role.id">
                                <td class="px-4 py-4 font-medium">
                                    <Link
                                        :href="show(role.id)"
                                        class="hover:underline"
                                    >
                                        {{ role.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge>{{
                                        role.permissions_count
                                    }}</StatusBadge>
                                </td>
                                <td class="px-4 py-4">
                                    <StatusBadge
                                        :tone="
                                            role.users_count > 0
                                                ? 'success'
                                                : 'neutral'
                                        "
                                    >
                                        {{ role.users_count }}
                                    </StatusBadge>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <Button
                                        v-if="can.edit"
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link :href="edit(role.id)">Edit</Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="roles.data.length === 0">
                                <td
                                    class="px-4 py-10 text-center text-muted-foreground"
                                    colspan="4"
                                >
                                    No roles found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ roles.from ?? 0 }} to {{ roles.to ?? 0 }} of
                        {{ roles.total }} roles
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="link in roles.links" :key="link.label">
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
