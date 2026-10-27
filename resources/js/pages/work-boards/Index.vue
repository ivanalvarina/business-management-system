<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Kanban, Plus, Users } from '@lucide/vue';
import { ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import EmptyState from '@/components/app/EmptyState.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { index, show, store } from '@/routes/work-boards';

type UserOption = { id: number; name: string; email: string };
type Board = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    status: 'active' | 'archived';
    members_count: number;
    tasks_count: number;
};

const props = defineProps<{
    company: { id: number; company_code: string; company_name: string } | null;
    boards: Board[];
    users: UserOption[];
    can: { create: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Boards & EOD', href: index() }] },
});

const form = useForm({
    company_id: props.company?.id ?? null,
    name: '',
    description: '',
    color: 'blue',
    member_ids: [] as number[],
});
const isCreateDialogOpen = ref(false);

const submit = () =>
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'description', 'color', 'member_ids');
            isCreateDialogOpen.value = false;
        },
    });

const setCreateDialogOpen = (open: boolean) => {
    isCreateDialogOpen.value = open;

    if (!open && !form.processing) {
        form.clearErrors();
    }
};

const colorClass = (color: string) =>
    ({
        blue: 'bg-blue-500',
        emerald: 'bg-emerald-500',
        amber: 'bg-amber-500',
        rose: 'bg-rose-500',
        violet: 'bg-violet-500',
        slate: 'bg-slate-500',
    })[color] ?? 'bg-blue-500';
</script>

<template>
    <Head title="Boards & EOD" />
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Boards & EOD"
            description="Create team boards, assign user tasks, and record daily accomplishments."
            :icon="Kanban"
        >
            <template #actions>
                <Button
                    v-if="can.create"
                    :disabled="!company"
                    @click="isCreateDialogOpen = true"
                >
                    <Plus /> New board
                </Button>
            </template>
        </PageHeader>

        <section class="min-w-0 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">
                        {{ company?.company_name }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ boards.length }}
                        {{ boards.length === 1 ? 'board' : 'boards' }}
                    </p>
                </div>
            </div>

            <div
                v-if="boards.length"
                class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3"
            >
                <Link
                    v-for="board in boards"
                    :key="board.id"
                    :href="show(board.id)"
                    class="group rounded-xl border bg-card p-5 transition hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span
                                    :class="[
                                        'size-2.5 rounded-full',
                                        colorClass(board.color),
                                    ]"
                                />
                                <h3 class="truncate font-semibold">
                                    {{ board.name }}
                                </h3>
                            </div>
                            <p
                                class="mt-2 line-clamp-2 min-h-10 text-sm text-muted-foreground"
                            >
                                {{ board.description || 'No description' }}
                            </p>
                        </div>
                        <span
                            v-if="board.status === 'archived'"
                            class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                            >Archived</span
                        >
                    </div>
                    <div
                        class="mt-5 flex items-center justify-between text-sm text-muted-foreground"
                    >
                        <span>{{ board.tasks_count }} tasks</span>
                        <span class="flex items-center gap-1"
                            ><Users class="size-4" />{{
                                board.members_count
                            }}</span
                        >
                    </div>
                </Link>
            </div>

            <EmptyState
                v-else
                title="No boards yet"
                description="Create a board for a project, department, or internal workflow."
                :icon="Kanban"
            />
        </section>

        <Dialog
            v-if="can.create"
            :open="isCreateDialogOpen"
            @update:open="setCreateDialogOpen"
        >
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Create board</DialogTitle>
                    <DialogDescription>
                        Add members now or update them later. Four starter
                        columns are created automatically.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label for="board-name" class="text-sm font-medium"
                            >Board name</label
                        >
                        <Input
                            id="board-name"
                            v-model="form.name"
                            placeholder="e.g. Payroll System"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="board-description"
                            class="text-sm font-medium"
                            >Description</label
                        >
                        <textarea
                            id="board-description"
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            placeholder="What is this board for?"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="space-y-2">
                        <label for="board-color" class="text-sm font-medium"
                            >Color</label
                        >
                        <select
                            id="board-color"
                            v-model="form.color"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="color in [
                                    'blue',
                                    'emerald',
                                    'amber',
                                    'rose',
                                    'violet',
                                    'slate',
                                ]"
                                :key="color"
                                :value="color"
                            >
                                {{
                                    color.charAt(0).toUpperCase() +
                                    color.slice(1)
                                }}
                            </option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm font-medium">Members</p>
                        <div
                            class="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3"
                        >
                            <label
                                v-for="user in users"
                                :key="user.id"
                                class="flex cursor-pointer items-start gap-2 text-sm"
                            >
                                <input
                                    v-model="form.member_ids"
                                    type="checkbox"
                                    :value="user.id"
                                    class="mt-1"
                                />
                                <span class="min-w-0"
                                    ><span class="block truncate font-medium">{{
                                        user.name
                                    }}</span
                                    ><span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ user.email }}</span
                                    ></span
                                >
                            </label>
                            <p
                                v-if="!users.length"
                                class="text-sm text-muted-foreground"
                            >
                                No company users available.
                            </p>
                        </div>
                        <InputError :message="form.errors.member_ids" />
                    </div>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button
                            type="submit"
                            :disabled="form.processing || !company"
                        >
                            <Plus /> Create board
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
