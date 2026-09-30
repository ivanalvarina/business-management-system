<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    Pencil,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
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

const applyFilters = () => {
    router.get(
        index.url(),
        { search: search.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const initials = (name: string) =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();

const prevLink = computed(() => props.users.links[0]);
const nextLink = computed(
    () => props.users.links[props.users.links.length - 1],
);
const currentPage = computed(
    () => props.users.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.users.links.length > 3);
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Manage user access, activation status, and roles."
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

        <div class="space-y-3">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search by name or email"
                    class="pl-9"
                />
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">User</th>
                            <th class="px-5 py-3 font-normal">Roles</th>
                            <th class="px-5 py-3 font-normal">Status</th>
                            <th class="w-28 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-full border bg-muted/50 text-xs font-medium text-muted-foreground"
                                    >
                                        {{ initials(user.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <Link
                                            :href="show(user.id)"
                                            class="block truncate font-medium hover:underline"
                                        >
                                            {{ user.name }}
                                        </Link>
                                        <p
                                            class="truncate text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role"
                                        class="rounded border px-1.5 py-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ role }}
                                    </span>
                                    <span
                                        v-if="user.roles.length === 0"
                                        class="text-xs text-muted-foreground"
                                    >
                                        No roles
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            user.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-amber-500'
                                        "
                                    />
                                    {{ user.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="show(user.id)"
                                            aria-label="View user"
                                            title="View"
                                        >
                                            <Eye class="size-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="can.edit"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <Link
                                            :href="edit(user.id)"
                                            aria-label="Edit user"
                                            title="Edit"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <span v-else class="size-9" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td
                                colspan="4"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No users match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ users.total }}
                    {{ users.total === 1 ? 'user' : 'users' }}
                </p>

                <div v-if="showPagination" class="flex items-center gap-1">
                    <Button
                        v-if="prevLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="prevLink.url" aria-label="Previous page">
                            <ChevronLeft class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Previous page"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>

                    <span class="px-2 text-sm font-medium">
                        {{ currentPage }}
                    </span>

                    <Button
                        v-if="nextLink?.url"
                        variant="ghost"
                        size="icon"
                        as-child
                    >
                        <Link :href="nextLink.url" aria-label="Next page">
                            <ChevronRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon"
                        disabled
                        aria-label="Next page"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>