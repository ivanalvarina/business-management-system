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

const prevLink = computed(() => props.roles.links[0]);
const nextLink = computed(
    () => props.roles.links[props.roles.links.length - 1],
);
const currentPage = computed(
    () => props.roles.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.roles.links.length > 3);
</script>

<template>
    <Head title="Roles & Permissions" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Roles & Permissions"
            description="Manage the permission sets that control application access."
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

        <div class="space-y-3">
            <div class="relative w-full sm:max-w-xs">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    placeholder="Search roles"
                    class="pl-9"
                />
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">Role</th>
                            <th class="px-5 py-3 font-normal">Permissions</th>
                            <th class="px-5 py-3 font-normal">Users</th>
                            <th class="w-28 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="role in roles.data"
                            :key="role.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <Link
                                    :href="show(role.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ role.name }}
                                </Link>
                            </td>
                            <td
                                class="px-5 py-3.5 text-muted-foreground tabular-nums"
                            >
                                {{ role.permissions_count }}
                            </td>
                            <td
                                class="px-5 py-3.5 tabular-nums"
                                :class="
                                    role.users_count > 0
                                        ? ''
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ role.users_count }}
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
                                            :href="show(role.id)"
                                            aria-label="View role"
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
                                            :href="edit(role.id)"
                                            aria-label="Edit role"
                                            title="Edit"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </Button>
                                    <span v-else class="size-9" />
                                </div>
                            </td>
                        </tr>
                        <tr v-if="roles.data.length === 0">
                            <td
                                colspan="4"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No roles match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ roles.total }}
                    {{ roles.total === 1 ? 'role' : 'roles' }}
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