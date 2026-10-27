<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CalendarDays,
    FileImage,
    GripVertical,
    Plus,
    Save,
    Settings2,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { show as attachmentShow } from '@/routes/eod-attachments';
import { store as eodStore } from '@/routes/eod-reports';
import {
    destroy as columnDestroy,
    store as columnStore,
} from '@/routes/work-board-columns';
import { store as labelStore } from '@/routes/task-labels';
import {
    destroy as taskDestroy,
    move,
    store as taskStore,
} from '@/routes/work-tasks';
import { destroy, index, update } from '@/routes/work-boards';

type UserOption = { id: number; name: string; email: string };
type Label = { id: number; name: string; color: string };
type Attachment = { id: number; original_name: string };
type EodItem = {
    id: number;
    accomplishment: string;
    minutes_spent: number | null;
    report: { work_date: string; blockers: string | null; user: UserOption };
    attachments: Attachment[];
};
type Task = {
    id: number;
    work_board_column_id: number;
    assigned_to: number | null;
    title: string;
    description: string | null;
    priority: 'low' | 'normal' | 'high' | 'urgent';
    due_date: string | null;
    assignee: UserOption | null;
    labels: Label[];
    eod_items: EodItem[];
};
type Column = {
    id: number;
    name: string;
    color: string;
    is_done: boolean;
    tasks: Task[];
};
type Board = {
    id: number;
    name: string;
    description: string | null;
    color: string;
    status: 'active' | 'archived';
    company: { id: number; company_name: string };
    members: UserOption[];
    labels: Label[];
    columns: Column[];
    created_by: number;
};

const props = defineProps<{
    board: Board;
    priorities: Task['priority'][];
    availableUsers: UserOption[];
    today: string;
    currentUserId: number;
    can: {
        editBoard: boolean;
        deleteBoard: boolean;
        createTask: boolean;
        deleteTask: boolean;
        moveTask: boolean;
        assignTask: boolean;
        submitEod: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Boards & EOD', href: index() },
            { title: 'Board', href: '#' },
        ],
    },
});

const taskForm = useForm({
    work_board_column_id: props.board.columns[0]?.id ?? null,
    assigned_to: null as number | null,
    title: '',
    description: '',
    priority: 'normal' as Task['priority'],
    due_date: '',
    label_ids: [] as number[],
});
const columnForm = useForm({ name: '', color: 'slate', is_done: false });
const labelForm = useForm({ name: '', color: 'blue' });
const settingsForm = useForm({
    name: props.board.name,
    description: props.board.description ?? '',
    color: props.board.color,
    status: props.board.status,
    member_ids: props.board.members.map((member) => member.id),
});

const selectedTask = ref<Task | null>(null);
const eodForm = useForm({
    work_date: props.today,
    accomplishment: '',
    minutes_spent: '' as number | '',
    summary: '',
    blockers: '',
    next_plan: '',
    screenshots: [] as File[],
});
const draggedTaskId = ref<number | null>(null);
const assigneeFilter = ref<'all' | 'mine' | 'unassigned' | number>('all');
const isTaskDialogOpen = ref(false);
const isBoardSetupDialogOpen = ref(false);
const selectedDetailsTask = ref<Task | null>(null);

const allTasks = computed(() =>
    props.board.columns.flatMap((column) => column.tasks),
);
const visibleTasks = (column: Column) => {
    if (assigneeFilter.value === 'all') return column.tasks;
    if (assigneeFilter.value === 'mine') {
        return column.tasks.filter(
            (task) => task.assigned_to === props.currentUserId,
        );
    }
    if (assigneeFilter.value === 'unassigned') {
        return column.tasks.filter((task) => task.assigned_to === null);
    }

    return column.tasks.filter(
        (task) => task.assigned_to === assigneeFilter.value,
    );
};
const priorityClass = (priority: Task['priority']) =>
    ({
        low: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
        normal: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        high: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        urgent: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
    })[priority];
const labelClass = (color: string) =>
    ({
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        emerald:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
        violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
        slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    })[color] ?? 'bg-muted text-muted-foreground';
const formatDate = (value: string) =>
    new Date(`${value.slice(0, 10)}T00:00:00`).toLocaleDateString('en-PH', {
        dateStyle: 'medium',
    });
const taskColumnName = (task: Task) =>
    props.board.columns.find(
        (column) => column.id === task.work_board_column_id,
    )?.name ?? 'Unknown column';

const createTask = () =>
    taskForm.post(taskStore.url(props.board.id), {
        preserveScroll: true,
        onSuccess: () => {
            taskForm.reset('title', 'description', 'due_date', 'label_ids');
            isTaskDialogOpen.value = false;
        },
    });
const setTaskDialogOpen = (open: boolean) => {
    isTaskDialogOpen.value = open;

    if (!open && !taskForm.processing) {
        taskForm.clearErrors();
    }
};
const createColumn = () =>
    columnForm.post(columnStore.url(props.board.id), {
        preserveScroll: true,
        onSuccess: () => columnForm.reset('name'),
    });
const removeColumn = (column: Column) => {
    if (column.tasks.length > 0) {
        window.alert('Move or delete all tasks in this column first.');

        return;
    }

    if (props.board.columns.length <= 1) {
        window.alert('A board must have at least one column.');

        return;
    }

    if (window.confirm(`Delete the "${column.name}" column?`)) {
        router.delete(columnDestroy.url([props.board.id, column.id]), {
            preserveScroll: true,
            onSuccess: () => {
                if (taskForm.work_board_column_id === column.id) {
                    taskForm.work_board_column_id =
                        props.board.columns[0]?.id ?? null;
                }
            },
        });
    }
};
const createLabel = () =>
    labelForm.post(labelStore.url(props.board.id), {
        preserveScroll: true,
        onSuccess: () => labelForm.reset('name'),
    });
const saveSettings = () =>
    settingsForm.patch(update.url(props.board.id), { preserveScroll: true });
const moveTask = (task: Task, columnId: number) => {
    if (!props.can.moveTask || task.work_board_column_id === columnId) return;
    router.patch(
        move.url([props.board.id, task.id]),
        { work_board_column_id: columnId },
        { preserveScroll: true },
    );
};
const dropTask = (columnId: number) => {
    const task = allTasks.value.find((item) => item.id === draggedTaskId.value);
    if (task) moveTask(task, columnId);
    draggedTaskId.value = null;
};
const removeTask = (task: Task) => {
    if (
        window.confirm(
            `Delete task “${task.title}”? Its EOD history must be empty before it can be deleted.`,
        )
    ) {
        selectedDetailsTask.value = null;
        router.delete(taskDestroy.url([props.board.id, task.id]), {
            preserveScroll: true,
        });
    }
};
const selectEodTask = (task: Task) => {
    selectedDetailsTask.value = null;
    selectedTask.value = task;
    eodForm.clearErrors();
    eodForm.reset();
    eodForm.work_date = props.today;
};
const setScreenshots = (event: Event) => {
    eodForm.screenshots = Array.from(
        (event.target as HTMLInputElement).files ?? [],
    );
};
const submitEod = () => {
    if (!selectedTask.value) return;
    eodForm.post(eodStore.url([props.board.id, selectedTask.value.id]), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            selectedTask.value = null;
            eodForm.reset();
        },
    });
};
const deleteBoard = () => {
    if (
        window.confirm(
            `Delete board “${props.board.name}” and all of its tasks?`,
        )
    ) {
        router.delete(destroy.url(props.board.id));
    }
};
</script>

<template>
    <Head :title="board.name" />
    <div class="flex min-w-0 flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="board.name"
            :description="`${board.company.company_name} · ${board.description || 'Work board'}`"
        >
            <template #actions>
                <Button
                    v-if="can.editBoard"
                    variant="outline"
                    @click="isBoardSetupDialogOpen = true"
                >
                    <Settings2 /> Board setup
                </Button>
                <Button v-if="can.createTask" @click="isTaskDialogOpen = true">
                    <Plus /> New task
                </Button>
                <Button variant="outline" as-child
                    ><Link :href="index()"><ArrowLeft /> Boards</Link></Button
                >
            </template>
        </PageHeader>

        <div class="min-w-0 space-y-4">
            <section class="min-w-0 space-y-4">
                <div
                    class="flex flex-col gap-2 rounded-xl border bg-card p-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium">Task view</p>
                        <p class="text-xs text-muted-foreground">
                            Filter the same board by user without creating
                            duplicate boards.
                        </p>
                    </div>
                    <select
                        v-model="assigneeFilter"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm sm:w-56"
                        aria-label="Filter tasks by assignee"
                    >
                        <option value="all">All tasks</option>
                        <option value="mine">My tasks</option>
                        <option value="unassigned">Unassigned</option>
                        <option
                            v-for="member in availableUsers"
                            :key="member.id"
                            :value="member.id"
                        >
                            {{ member.name }}
                        </option>
                    </select>
                </div>
                <div class="overflow-x-auto pb-3">
                    <div class="flex min-w-max items-start gap-4">
                        <section
                            v-for="column in board.columns"
                            :key="column.id"
                            class="w-80 shrink-0 rounded-xl border bg-muted/30 p-3"
                            @dragover.prevent
                            @drop="dropTask(column.id)"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <h2 class="font-semibold">{{ column.name }}</h2>
                                <div class="flex items-center gap-1">
                                    <span
                                        class="rounded-full bg-background px-2 py-0.5 text-xs text-muted-foreground"
                                        >{{ visibleTasks(column).length }}</span
                                    >
                                    <Button
                                        v-if="can.editBoard"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-7 text-muted-foreground hover:text-destructive"
                                        :aria-label="`Delete ${column.name} column`"
                                        :title="`Delete ${column.name} column`"
                                        @click="removeColumn(column)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <article
                                    v-for="task in visibleTasks(column)"
                                    :key="task.id"
                                    :draggable="can.moveTask"
                                    role="button"
                                    tabindex="0"
                                    class="cursor-pointer rounded-lg border bg-card p-3 shadow-xs transition hover:border-foreground/20 hover:shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    @click="selectedDetailsTask = task"
                                    @keydown.enter.prevent="
                                        selectedDetailsTask = task
                                    "
                                    @dragstart="draggedTaskId = task.id"
                                    @dragend="draggedTaskId = null"
                                >
                                    <div class="flex items-start gap-2">
                                        <GripVertical
                                            v-if="can.moveTask"
                                            class="mt-0.5 size-4 shrink-0 cursor-grab text-muted-foreground"
                                        />
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-start justify-between gap-2"
                                            >
                                                <h3
                                                    class="leading-snug font-medium"
                                                >
                                                    {{ task.title }}
                                                </h3>
                                                <Button
                                                    v-if="can.deleteTask"
                                                    variant="ghost"
                                                    size="icon"
                                                    class="size-7 shrink-0 text-muted-foreground hover:text-destructive"
                                                    @click.stop="
                                                        removeTask(task)
                                                    "
                                                    ><Trash2 class="size-3.5"
                                                /></Button>
                                            </div>
                                            <p
                                                v-if="task.description"
                                                class="mt-1 line-clamp-3 text-sm text-muted-foreground"
                                            >
                                                {{ task.description }}
                                            </p>
                                            <div
                                                class="mt-3 flex flex-wrap gap-1.5"
                                            >
                                                <span
                                                    :class="[
                                                        'rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                                        priorityClass(
                                                            task.priority,
                                                        ),
                                                    ]"
                                                >
                                                    <AlertTriangle
                                                        v-if="
                                                            task.priority ===
                                                            'urgent'
                                                        "
                                                        class="mr-1 inline size-3"
                                                    />{{ task.priority }}
                                                </span>
                                                <span
                                                    v-for="label in task.labels"
                                                    :key="label.id"
                                                    :class="[
                                                        'rounded-full px-2 py-0.5 text-xs',
                                                        labelClass(label.color),
                                                    ]"
                                                    >{{ label.name }}</span
                                                >
                                            </div>
                                            <div
                                                class="mt-3 space-y-1.5 text-xs text-muted-foreground"
                                            >
                                                <p
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <UserRound
                                                        class="size-3.5"
                                                    />{{
                                                        task.assignee?.name ||
                                                        'Unassigned'
                                                    }}
                                                </p>
                                                <p
                                                    v-if="task.due_date"
                                                    class="flex items-center gap-1.5"
                                                >
                                                    <CalendarDays
                                                        class="size-3.5"
                                                    />Due
                                                    {{
                                                        formatDate(
                                                            task.due_date,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <Button
                                                v-if="
                                                    can.submitEod &&
                                                    task.assigned_to ===
                                                        currentUserId
                                                "
                                                variant="outline"
                                                size="sm"
                                                class="mt-2 w-full"
                                                @click.stop="
                                                    selectEodTask(task)
                                                "
                                                ><Save /> Add today’s
                                                EOD</Button
                                            >

                                            <details
                                                v-if="task.eod_items.length"
                                                class="mt-3 border-t pt-2"
                                                @click.stop
                                            >
                                                <summary
                                                    class="cursor-pointer text-xs font-medium"
                                                >
                                                    {{ task.eod_items.length }}
                                                    recent EOD update{{
                                                        task.eod_items
                                                            .length === 1
                                                            ? ''
                                                            : 's'
                                                    }}
                                                </summary>
                                                <div class="mt-2 space-y-2">
                                                    <div
                                                        v-for="item in task.eod_items"
                                                        :key="item.id"
                                                        class="rounded-md bg-muted/50 p-2 text-xs"
                                                    >
                                                        <p class="font-medium">
                                                            {{
                                                                item.report.user
                                                                    .name
                                                            }}
                                                            ·
                                                            {{
                                                                formatDate(
                                                                    item.report
                                                                        .work_date,
                                                                )
                                                            }}
                                                        </p>
                                                        <p
                                                            class="mt-1 whitespace-pre-wrap text-muted-foreground"
                                                        >
                                                            {{
                                                                item.accomplishment
                                                            }}
                                                        </p>
                                                        <p
                                                            v-if="
                                                                item.report
                                                                    .blockers
                                                            "
                                                            class="mt-1 text-rose-600"
                                                        >
                                                            Blocked:
                                                            {{
                                                                item.report
                                                                    .blockers
                                                            }}
                                                        </p>
                                                        <div
                                                            v-if="
                                                                item.attachments
                                                                    .length
                                                            "
                                                            class="mt-2 flex flex-wrap gap-1"
                                                        >
                                                            <a
                                                                v-for="attachment in item.attachments"
                                                                :key="
                                                                    attachment.id
                                                                "
                                                                :href="
                                                                    attachmentShow.url(
                                                                        attachment.id,
                                                                    )
                                                                "
                                                                target="_blank"
                                                                rel="noopener"
                                                                class="inline-flex items-center gap-1 rounded border bg-background px-2 py-1 hover:bg-accent"
                                                                ><FileImage
                                                                    class="size-3"
                                                                />
                                                                Screenshot</a
                                                            >
                                                        </div>
                                                    </div>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                </article>
                                <p
                                    v-if="!visibleTasks(column).length"
                                    class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
                                >
                                    No tasks in this view
                                </p>
                            </div>
                        </section>
                    </div>
                </div>

                <section
                    v-if="selectedTask"
                    class="rounded-xl border bg-card p-5"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">EOD update</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ selectedTask.title }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            @click="selectedTask = null"
                            >Cancel</Button
                        >
                    </div>
                    <form
                        class="grid gap-4 md:grid-cols-2"
                        @submit.prevent="submitEod"
                    >
                        <div class="space-y-2">
                            <label for="work-date" class="text-sm font-medium"
                                >Work date</label
                            ><Input
                                id="work-date"
                                v-model="eodForm.work_date"
                                type="date"
                                :max="today"
                            /><InputError :message="eodForm.errors.work_date" />
                        </div>
                        <div class="space-y-2">
                            <label for="minutes" class="text-sm font-medium"
                                >Minutes spent (optional)</label
                            ><Input
                                id="minutes"
                                v-model="eodForm.minutes_spent"
                                type="number"
                                min="1"
                                max="1440"
                            /><InputError
                                :message="eodForm.errors.minutes_spent"
                            />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label
                                for="accomplishment"
                                class="text-sm font-medium"
                                >What did you accomplish?</label
                            ><textarea
                                id="accomplishment"
                                v-model="eodForm.accomplishment"
                                rows="4"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            /><InputError
                                :message="eodForm.errors.accomplishment"
                            />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label for="eod-summary" class="text-sm font-medium"
                                >Daily summary (optional)</label
                            ><textarea
                                id="eod-summary"
                                v-model="eodForm.summary"
                                rows="2"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            /><InputError :message="eodForm.errors.summary" />
                        </div>
                        <div class="space-y-2">
                            <label for="blockers" class="text-sm font-medium"
                                >Blockers</label
                            ><textarea
                                id="blockers"
                                v-model="eodForm.blockers"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="space-y-2">
                            <label for="next-plan" class="text-sm font-medium"
                                >Next action</label
                            ><textarea
                                id="next-plan"
                                v-model="eodForm.next_plan"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <label for="screenshots" class="text-sm font-medium"
                                >Screenshots (up to 3, 5 MB each)</label
                            ><Input
                                id="screenshots"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                multiple
                                @change="setScreenshots"
                            /><InputError
                                :message="eodForm.errors.screenshots"
                            /><progress
                                v-if="eodForm.progress"
                                class="h-2 w-full"
                                :value="eodForm.progress.percentage"
                                max="100"
                            />
                        </div>
                        <div class="flex justify-end md:col-span-2">
                            <Button type="submit" :disabled="eodForm.processing"
                                ><Save /> Submit EOD</Button
                            >
                        </div>
                    </form>
                </section>
            </section>

            <Dialog
                v-if="can.editBoard"
                :open="isBoardSetupDialogOpen"
                @update:open="isBoardSetupDialogOpen = $event"
            >
                <DialogScrollContent class="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Board setup</DialogTitle>
                        <DialogDescription>
                            Manage columns, labels, board details, and members.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="space-y-5">
                        <form class="space-y-2" @submit.prevent="createColumn">
                            <p class="text-sm font-medium">Add column</p>
                            <div class="flex gap-2">
                                <Input
                                    v-model="columnForm.name"
                                    placeholder="For Review"
                                /><Button
                                    type="submit"
                                    size="icon"
                                    :disabled="columnForm.processing"
                                    ><Plus
                                /></Button>
                            </div>
                            <label
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                                ><input
                                    v-model="columnForm.is_done"
                                    type="checkbox"
                                />Tasks here are completed</label
                            ><InputError :message="columnForm.errors.name" />
                        </form>
                        <form
                            class="space-y-2 border-t pt-4"
                            @submit.prevent="createLabel"
                        >
                            <p class="text-sm font-medium">Add label</p>
                            <div class="flex gap-2">
                                <Input
                                    v-model="labelForm.name"
                                    placeholder="Client Request"
                                /><Button
                                    type="submit"
                                    size="icon"
                                    :disabled="labelForm.processing"
                                    ><Plus
                                /></Button>
                            </div>
                            <select
                                v-model="labelForm.color"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
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
                                    {{ color }}
                                </option></select
                            ><InputError :message="labelForm.errors.name" />
                        </form>
                        <form
                            class="space-y-3 border-t pt-4"
                            @submit.prevent="saveSettings"
                        >
                            <p class="text-sm font-medium">
                                Settings & members
                            </p>
                            <Input v-model="settingsForm.name" /><textarea
                                v-model="settingsForm.description"
                                rows="2"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            /><select
                                v-model="settingsForm.status"
                                class="h-9 w-full rounded-md border border-input bg-background px-2 text-sm"
                            >
                                <option value="active">Active</option>
                                <option value="archived">Archived</option>
                            </select>
                            <div
                                class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-2"
                            >
                                <label
                                    v-for="member in availableUsers"
                                    :key="member.id"
                                    class="flex items-center gap-2 text-sm"
                                    ><input
                                        v-model="settingsForm.member_ids"
                                        type="checkbox"
                                        :value="member.id"
                                        :disabled="
                                            member.id === board.created_by
                                        "
                                    />{{ member.name }}</label
                                >
                            </div>
                            <InputError
                                :message="settingsForm.errors.member_ids"
                            /><Button
                                type="submit"
                                variant="outline"
                                class="w-full"
                                :disabled="settingsForm.processing"
                                ><Save /> Save settings</Button
                            >
                        </form>
                        <Button
                            v-if="can.deleteBoard"
                            variant="destructive"
                            class="w-full"
                            @click="deleteBoard"
                            ><Trash2 /> Delete board</Button
                        >
                    </div>
                </DialogScrollContent>
            </Dialog>
        </div>

        <Dialog
            :open="selectedDetailsTask !== null"
            @update:open="
                (open) => {
                    if (!open) selectedDetailsTask = null;
                }
            "
        >
            <DialogScrollContent class="sm:max-w-2xl">
                <template v-if="selectedDetailsTask">
                    <DialogHeader>
                        <DialogTitle>{{
                            selectedDetailsTask.title
                        }}</DialogTitle>
                        <DialogDescription>
                            {{ taskColumnName(selectedDetailsTask) }} task
                            details
                        </DialogDescription>
                    </DialogHeader>

                    <div class="space-y-5">
                        <div class="flex flex-wrap gap-2">
                            <span
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                    priorityClass(selectedDetailsTask.priority),
                                ]"
                            >
                                <AlertTriangle
                                    v-if="
                                        selectedDetailsTask.priority ===
                                        'urgent'
                                    "
                                    class="mr-1 inline size-3"
                                />
                                {{ selectedDetailsTask.priority }} priority
                            </span>
                            <span
                                v-for="label in selectedDetailsTask.labels"
                                :key="label.id"
                                :class="[
                                    'rounded-full px-2.5 py-1 text-xs',
                                    labelClass(label.color),
                                ]"
                            >
                                {{ label.name }}
                            </span>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border bg-muted/30 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Column
                                </p>
                                <p class="mt-1 text-sm font-medium">
                                    {{ taskColumnName(selectedDetailsTask) }}
                                </p>
                            </div>
                            <div class="rounded-lg border bg-muted/30 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Assignee
                                </p>
                                <p class="mt-1 text-sm font-medium">
                                    {{
                                        selectedDetailsTask.assignee?.name ||
                                        'Unassigned'
                                    }}
                                </p>
                            </div>
                            <div class="rounded-lg border bg-muted/30 p-3">
                                <p class="text-xs text-muted-foreground">
                                    Due date
                                </p>
                                <p class="mt-1 text-sm font-medium">
                                    {{
                                        selectedDetailsTask.due_date
                                            ? formatDate(
                                                  selectedDetailsTask.due_date,
                                              )
                                            : 'No due date'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-medium">Description</h3>
                            <p
                                class="mt-2 text-sm whitespace-pre-wrap text-muted-foreground"
                            >
                                {{
                                    selectedDetailsTask.description ||
                                    'No description provided.'
                                }}
                            </p>
                        </div>

                        <div class="border-t pt-4">
                            <h3 class="text-sm font-medium">
                                Recent EOD updates
                            </h3>
                            <div
                                v-if="selectedDetailsTask.eod_items.length"
                                class="mt-3 space-y-3"
                            >
                                <article
                                    v-for="item in selectedDetailsTask.eod_items"
                                    :key="item.id"
                                    class="rounded-lg border bg-muted/30 p-3 text-sm"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p class="font-medium">
                                            {{ item.report.user.name }}
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formatDate(
                                                    item.report.work_date,
                                                )
                                            }}
                                            <template v-if="item.minutes_spent">
                                                — {{ item.minutes_spent }} min
                                            </template>
                                        </p>
                                    </div>
                                    <p
                                        class="mt-2 whitespace-pre-wrap text-muted-foreground"
                                    >
                                        {{ item.accomplishment }}
                                    </p>
                                    <p
                                        v-if="item.report.blockers"
                                        class="mt-2 text-rose-600"
                                    >
                                        Blocked: {{ item.report.blockers }}
                                    </p>
                                    <div
                                        v-if="item.attachments.length"
                                        class="mt-3 flex flex-wrap gap-2"
                                    >
                                        <a
                                            v-for="attachment in item.attachments"
                                            :key="attachment.id"
                                            :href="
                                                attachmentShow.url(
                                                    attachment.id,
                                                )
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-md border bg-background px-2 py-1 text-xs hover:bg-accent"
                                        >
                                            <FileImage class="size-3.5" />
                                            {{ attachment.original_name }}
                                        </a>
                                    </div>
                                </article>
                            </div>
                            <p
                                v-else
                                class="mt-3 rounded-lg border border-dashed p-4 text-center text-sm text-muted-foreground"
                            >
                                No EOD updates yet.
                            </p>
                        </div>
                    </div>

                    <DialogFooter class="gap-2 sm:justify-between">
                        <Button
                            v-if="can.deleteTask"
                            type="button"
                            variant="destructive"
                            @click="removeTask(selectedDetailsTask)"
                        >
                            <Trash2 /> Delete task
                        </Button>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row">
                            <DialogClose as-child>
                                <Button type="button" variant="outline">
                                    Close
                                </Button>
                            </DialogClose>
                            <Button
                                v-if="
                                    can.submitEod &&
                                    selectedDetailsTask.assigned_to ===
                                        currentUserId
                                "
                                type="button"
                                @click="selectEodTask(selectedDetailsTask)"
                            >
                                <Save /> Add today’s EOD
                            </Button>
                        </div>
                    </DialogFooter>
                </template>
            </DialogScrollContent>
        </Dialog>

        <Dialog
            v-if="can.createTask"
            :open="isTaskDialogOpen"
            @update:open="setTaskDialogOpen"
        >
            <DialogScrollContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>New task</DialogTitle>
                    <DialogDescription>
                        Add a task, choose its workflow column, and assign it to
                        an active company user.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="grid gap-4 md:grid-cols-2"
                    @submit.prevent="createTask"
                >
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="task-title" class="text-sm font-medium"
                            >Title</label
                        >
                        <Input id="task-title" v-model="taskForm.title" />
                        <InputError :message="taskForm.errors.title" />
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <label
                            for="task-description"
                            class="text-sm font-medium"
                            >Description</label
                        >
                        <textarea
                            id="task-description"
                            v-model="taskForm.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="taskForm.errors.description" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="task-column" class="text-sm font-medium"
                            >Column</label
                        >
                        <select
                            id="task-column"
                            v-model="taskForm.work_board_column_id"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option
                                v-for="column in board.columns"
                                :key="column.id"
                                :value="column.id"
                            >
                                {{ column.name }}
                            </option>
                        </select>
                        <InputError
                            :message="taskForm.errors.work_board_column_id"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label for="task-priority" class="text-sm font-medium"
                            >Priority</label
                        >
                        <select
                            id="task-priority"
                            v-model="taskForm.priority"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm capitalize"
                        >
                            <option
                                v-for="priority in priorities"
                                :key="priority"
                                :value="priority"
                                class="capitalize"
                            >
                                {{ priority }}
                            </option>
                        </select>
                        <InputError :message="taskForm.errors.priority" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="task-assignee" class="text-sm font-medium"
                            >Assignee</label
                        >
                        <select
                            id="task-assignee"
                            v-model="taskForm.assigned_to"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option :value="null">Unassigned</option>
                            <option
                                v-for="member in availableUsers"
                                :key="member.id"
                                :value="member.id"
                                :disabled="
                                    !can.assignTask &&
                                    member.id !== currentUserId
                                "
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <InputError :message="taskForm.errors.assigned_to" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="due-date" class="text-sm font-medium"
                            >Due date</label
                        >
                        <Input
                            id="due-date"
                            v-model="taskForm.due_date"
                            type="date"
                        />
                        <InputError :message="taskForm.errors.due_date" />
                    </div>

                    <div
                        v-if="board.labels.length"
                        class="space-y-2 md:col-span-2"
                    >
                        <p class="text-sm font-medium">Labels</p>
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="label in board.labels"
                                :key="label.id"
                                :class="[
                                    'inline-flex cursor-pointer items-center gap-2 rounded-full px-3 py-1.5 text-sm',
                                    labelClass(label.color),
                                ]"
                            >
                                <input
                                    v-model="taskForm.label_ids"
                                    type="checkbox"
                                    :value="label.id"
                                />
                                {{ label.name }}
                            </label>
                        </div>
                        <InputError :message="taskForm.errors.label_ids" />
                    </div>

                    <DialogFooter class="gap-2 md:col-span-2">
                        <DialogClose as-child>
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </DialogClose>
                        <Button type="submit" :disabled="taskForm.processing">
                            <Plus /> Add task
                        </Button>
                    </DialogFooter>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
