<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Globe,
    ImageIcon,
    Lock,
    Pencil,
    Plus,
    Printer,
    QrCode,
    Search,
    X,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    bulkQrPrint,
    create,
    edit,
    index,
    qrPrint,
    show,
} from '@/routes/product-services';

type Item = {
    id: number;
    code: string;
    type: 'product' | 'service';
    name: string;
    description: string | null;
    unit: string;
    default_price: string;
    quantity: number;
    status: 'active' | 'inactive';
    is_public: boolean;
    public_url: string | null;
    primary_image: { url: string } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; type: string; status: string };
    items: {
        data: Item[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; edit: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Products & Services', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');
const status = ref(props.filters.status ?? 'all');
const selectedQrIds = ref<number[]>([]);
const openQrId = ref<number | null>(null);

const typeOptions = [
    { value: 'all', label: 'All' },
    { value: 'product', label: 'Products' },
    { value: 'service', label: 'Services' },
];

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const setType = (value: string) => {
    type.value = value;
    applyFilters();
};

const toggleQr = (id: number) => {
    openQrId.value = openQrId.value === id ? null : id;
};

const closeOnEscape = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        openQrId.value = null;
    }
};

onMounted(() => window.addEventListener('keydown', closeOnEscape));
onUnmounted(() => window.removeEventListener('keydown', closeOnEscape));

const qrUrl = (item: Item) =>
    item.public_url
        ? `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(item.public_url)}`
        : null;

const isPrintableQrItem = (item: Item) =>
    item.type === 'product' &&
    item.status === 'active' &&
    item.is_public &&
    item.public_url !== null;

const formatPrice = (value: string) => {
    const number = Number(value);

    return Number.isNaN(number)
        ? value
        : number.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
};

const stockClass = (item: Item) => {
    if (item.quantity <= 0) {
        return 'text-red-600 dark:text-red-400';
    }

    return item.quantity <= 10
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-muted-foreground';
};

const printableItems = computed(() =>
    props.items.data.filter((item) => isPrintableQrItem(item)),
);

const selectedPrintableIds = computed(() =>
    selectedQrIds.value.filter((id) =>
        printableItems.value.some((item) => item.id === id),
    ),
);

const selectedQrQuery = computed(() => selectedPrintableIds.value.join(','));

const pageQrQuery = computed(() =>
    printableItems.value.map((item) => item.id).join(','),
);

const filteredQrQuery = computed(() => ({
    search: search.value || undefined,
}));

const allPageSelected = computed(
    () =>
        printableItems.value.length > 0 &&
        printableItems.value.every((item) =>
            selectedQrIds.value.includes(item.id),
        ),
);

const toggleSelectedQr = (id: number) => {
    selectedQrIds.value = selectedQrIds.value.includes(id)
        ? selectedQrIds.value.filter((selectedId) => selectedId !== id)
        : [...selectedQrIds.value, id];
};

const toggleAllPageQr = () => {
    if (allPageSelected.value) {
        selectedQrIds.value = selectedQrIds.value.filter(
            (id) => !printableItems.value.some((item) => item.id === id),
        );

        return;
    }

    selectedQrIds.value = Array.from(
        new Set([
            ...selectedQrIds.value,
            ...printableItems.value.map((item) => item.id),
        ]),
    );
};

const prevLink = computed(() => props.items.links[0]);
const nextLink = computed(
    () => props.items.links[props.items.links.length - 1],
);
const currentPage = computed(
    () => props.items.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.items.links.length > 3);
</script>

<template>
    <Head title="Products & Services" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Products & Services"
            description="Manage your reusable catalog of products and services."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New item
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="space-y-4">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <div class="relative w-full sm:max-w-xs">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="search"
                            placeholder="Search code or name"
                            class="pl-9"
                        />
                    </div>
                    <select
                        v-model="status"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground sm:w-40"
                        aria-label="Filter by status"
                        @change="applyFilters"
                    >
                        <option value="all">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="flex gap-1" role="group" aria-label="Filter by type">
                    <button
                        v-for="option in typeOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-md px-3 py-1.5 text-sm transition-colors"
                        :class="
                            type === option.value
                                ? 'bg-muted font-medium text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="type === option.value"
                        @click="setType(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="printableItems.length > 0"
                class="flex flex-wrap items-center gap-1 text-sm"
            >
                <span class="mr-1 text-muted-foreground">QR labels</span>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    @click="toggleAllPageQr"
                >
                    {{ allPageSelected ? 'Clear selection' : 'Select page' }}
                </Button>
                <Button
                    v-if="selectedPrintableIds.length > 0"
                    size="sm"
                    variant="outline"
                    as-child
                >
                    <Link
                        :href="
                            bulkQrPrint.url({ query: { ids: selectedQrQuery } })
                        "
                        target="_blank"
                    >
                        <Printer />
                        Print selected ({{ selectedPrintableIds.length }})
                    </Link>
                </Button>
                <Button
                    v-if="pageQrQuery"
                    size="sm"
                    variant="ghost"
                    as-child
                >
                    <Link
                        :href="bulkQrPrint.url({ query: { ids: pageQrQuery } })"
                        target="_blank"
                    >
                        Print page
                    </Link>
                </Button>
                <Button size="sm" variant="ghost" as-child>
                    <Link
                        :href="bulkQrPrint.url({ query: filteredQrQuery })"
                        target="_blank"
                    >
                        Print all filtered
                    </Link>
                </Button>
            </div>

            <div
                v-if="items.data.length > 0"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-5"
            >
                <article
                    v-for="item in items.data"
                    :key="item.id"
                    class="group relative flex min-w-0 flex-col rounded-xl border bg-card transition-colors focus-within:ring-2 focus-within:ring-ring/50 hover:border-foreground/25"
                    :class="{ 'opacity-60': item.status === 'inactive' }"
                >
                    <div
                        class="relative h-36 overflow-hidden rounded-t-xl bg-muted/40"
                    >
                        <img
                            v-if="item.primary_image"
                            :src="item.primary_image.url"
                            :alt="item.name"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-full w-full items-center justify-center text-muted-foreground/60"
                        >
                            <ImageIcon class="size-7" />
                        </div>

                        <label
                            v-if="isPrintableQrItem(item)"
                            class="absolute top-2 left-2 z-10 flex size-6 items-center justify-center rounded-md bg-background/90 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
                            :class="
                                selectedQrIds.includes(item.id)
                                    ? 'opacity-100'
                                    : 'opacity-0'
                            "
                            title="Select for QR print"
                        >
                            <span class="sr-only">
                                Select {{ item.name }} for QR print
                            </span>
                            <input
                                type="checkbox"
                                class="size-3.5 accent-foreground"
                                :checked="selectedQrIds.includes(item.id)"
                                @change="toggleSelectedQr(item.id)"
                            />
                        </label>

                        <span
                            class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-md bg-background/90 text-muted-foreground"
                            :title="item.is_public ? 'Public' : 'Private'"
                        >
                            <Globe v-if="item.is_public" class="size-3.5" />
                            <Lock v-else class="size-3.5" />
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col gap-0.5 px-3.5 pt-3 pb-2">
                        <div class="flex items-baseline justify-between gap-2">
                            <Link
                                :href="show(item.id)"
                                class="min-w-0 truncate text-sm font-medium after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                            >
                                {{ item.name }}
                            </Link>
                            <span
                                class="shrink-0 text-sm font-medium tabular-nums"
                            >
                                {{ formatPrice(item.default_price) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                        >
                            <span class="truncate font-mono">
                                {{ item.code }}
                            </span>
                            <span
                                v-if="item.type === 'product'"
                                class="shrink-0"
                                :class="stockClass(item)"
                            >
                                {{ item.quantity }} {{ item.unit }} in stock
                            </span>
                            <span v-else class="shrink-0">
                                Service, per {{ item.unit }}
                            </span>
                        </div>

                        <p
                            v-if="item.description"
                            class="mt-1 line-clamp-1 text-xs text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>
                    </div>

                    <div
                        class="relative z-10 flex items-center justify-between border-t px-2 py-1.5"
                    >
                        <span
                            v-if="item.status === 'inactive'"
                            class="inline-flex items-center gap-1.5 pl-1.5 text-xs text-muted-foreground"
                        >
                            <span class="size-1.5 rounded-full bg-amber-500" />
                            Inactive
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 pl-1.5 text-xs text-muted-foreground"
                        >
                            <span
                                class="size-1.5 rounded-full bg-emerald-500"
                            />
                            Active
                        </span>

                        <div class="flex items-center">
                            <Button
                                v-if="can.edit"
                                size="icon"
                                variant="ghost"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                as-child
                            >
                                <Link
                                    :href="edit(item.id)"
                                    aria-label="Edit item"
                                    title="Edit"
                                >
                                    <Pencil class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="size-8 text-muted-foreground hover:text-foreground"
                                aria-label="Show QR code"
                                title="QR code"
                                :disabled="!isPrintableQrItem(item)"
                                :aria-expanded="openQrId === item.id"
                                @click="toggleQr(item.id)"
                            >
                                <QrCode class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <template v-if="openQrId === item.id">
                        <button
                            type="button"
                            class="fixed inset-0 z-20 cursor-default"
                            aria-label="Close QR code"
                            @click="openQrId = null"
                        />
                        <div
                            class="absolute right-3 bottom-12 z-30 w-52 rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <p class="text-sm font-medium">
                                    {{
                                        item.public_url ? 'Public QR' : 'No QR'
                                    }}
                                </p>
                                <button
                                    type="button"
                                    class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                                    aria-label="Close"
                                    @click="openQrId = null"
                                >
                                    <X class="size-4" />
                                </button>
                            </div>

                            <template v-if="qrUrl(item)">
                                <img
                                    :src="qrUrl(item) ?? undefined"
                                    :alt="`QR code for ${item.name}`"
                                    loading="lazy"
                                    class="mx-auto size-40 rounded-md bg-white p-1"
                                />
                                <Button
                                    class="mt-3 w-full"
                                    variant="outline"
                                    as-child
                                >
                                    <Link
                                        :href="qrPrint(item.id)"
                                        target="_blank"
                                    >
                                        <Printer />
                                        Print QR
                                    </Link>
                                </Button>
                                <a
                                    :href="item.public_url ?? undefined"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="mt-2 block text-center text-xs text-muted-foreground hover:underline"
                                >
                                    Open public page
                                </a>
                            </template>
                            <p v-else class="text-xs text-muted-foreground">
                                Enable public access to show a QR code.
                            </p>
                        </div>
                    </template>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-14 text-center"
            >
                <ImageIcon class="size-7 text-muted-foreground" />
                <div>
                    <p class="text-sm font-medium">No items match your search</p>
                    <p class="text-sm text-muted-foreground">
                        Try changing your filters or add a new item.
                    </p>
                </div>
                <Button v-if="can.create" size="sm" as-child>
                    <Link :href="create()">
                        <Plus />
                        New item
                    </Link>
                </Button>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ items.total }}
                    {{ items.total === 1 ? 'item' : 'items' }}
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
        </section>
    </div>
</template>