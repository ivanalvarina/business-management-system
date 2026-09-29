<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CheckSquare,
    Eye,
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
import { computed, onMounted, onUnmounted, ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
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

const activeItemsCount = computed(
    () => props.items.data.filter((item) => item.status === 'active').length,
);

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

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
            status: status.value === 'all' ? undefined : status.value,
        },
        { preserveState: true, replace: true },
    );
};
</script>

<template>
    <Head title="Products & Services" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <PageHeader
            title="Products & Services"
            description="Manage reusable catalog records for future transaction documents."
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
            <!-- Filters -->
            <form
                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_170px_170px_auto]"
                @submit.prevent="submitSearch"
            >
                <Input
                    v-model="search"
                    placeholder="Search code or name"
                    class="sm:col-span-2 lg:col-span-1"
                />
                <select
                    v-model="type"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="all">All types</option>
                    <option value="product">Products</option>
                    <option value="service">Services</option>
                </select>
                <select
                    v-model="status"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                >
                    <option value="all">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <Button type="submit" variant="outline">
                    <Search />
                    Search
                </Button>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-muted-foreground">
                    Showing {{ items.from ?? 0 }} to {{ items.to ?? 0 }} of
                    {{ items.total }} items
                </p>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <p class="text-sm text-muted-foreground">
                        {{ activeItemsCount }} active,
                        {{ printableItems.length }} printable QR on this page
                    </p>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="printableItems.length === 0"
                        @click="toggleAllPageQr"
                    >
                        <CheckSquare />
                        {{ allPageSelected ? 'Clear page' : 'Select page QR' }}
                    </Button>
                    <Button
                        v-if="selectedPrintableIds.length > 0"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="
                                bulkQrPrint.url({
                                    query: { ids: selectedQrQuery },
                                })
                            "
                            target="_blank"
                        >
                            <Printer />
                            Print selected
                        </Link>
                    </Button>
                    <Button
                        v-if="pageQrQuery"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="
                                bulkQrPrint.url({
                                    query: { ids: pageQrQuery },
                                })
                            "
                            target="_blank"
                        >
                            <Printer />
                            Print page
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" as-child>
                        <Link
                            :href="
                                bulkQrPrint.url({
                                    query: filteredQrQuery,
                                })
                            "
                            target="_blank"
                        >
                            <Printer />
                            Print all filtered
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Cards -->
            <div
                v-if="items.data.length > 0"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 2xl:grid-cols-6"
            >
                <article
                    v-for="item in items.data"
                    :key="item.id"
                    class="group relative flex min-w-0 flex-col rounded-xl border bg-card transition-colors focus-within:ring-2 focus-within:ring-ring/50 hover:border-foreground/25"
                    :class="{ 'opacity-75': item.status === 'inactive' }"
                >
                    <!-- Image -->
                    <div
                        class="relative h-32 overflow-hidden rounded-t-xl bg-muted/40"
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
                            class="flex h-full w-full items-center justify-center text-muted-foreground"
                        >
                            <ImageIcon class="size-8" />
                        </div>

                        <div class="absolute top-2 left-2 flex gap-1">
                            <StatusBadge tone="info">
                                {{
                                    item.type === 'product'
                                        ? 'Product'
                                        : 'Service'
                                }}
                            </StatusBadge>
                            <StatusBadge
                                :tone="
                                    item.status === 'active'
                                        ? 'success'
                                        : 'warning'
                                "
                            >
                                {{
                                    item.status === 'active'
                                        ? 'Active'
                                        : 'Inactive'
                                }}
                            </StatusBadge>
                        </div>

                        <span
                            class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full bg-background/90 text-muted-foreground"
                            :title="item.is_public ? 'Public' : 'Private'"
                        >
                            <Globe v-if="item.is_public" class="size-3.5" />
                            <Lock v-else class="size-3.5" />
                        </span>

                        <label
                            v-if="isPrintableQrItem(item)"
                            class="absolute top-2 right-10 z-10 flex size-6 items-center justify-center rounded-full bg-background/90 text-foreground shadow-sm"
                            title="Select for bulk QR print"
                            @click.stop
                        >
                            <span class="sr-only"
                                >Select {{ item.name }} QR</span
                            >
                            <input
                                type="checkbox"
                                class="size-3.5 accent-foreground"
                                :checked="selectedQrIds.includes(item.id)"
                                @change="toggleSelectedQr(item.id)"
                            />
                        </label>
                    </div>

                    <!-- Info -->
                    <div class="flex flex-1 flex-col gap-1 p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <!-- Stretched link: whole card is clickable -->
                            <Link
                                :href="show(item.id)"
                                class="min-w-0 truncate text-sm font-semibold after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                            >
                                {{ item.name }}
                            </Link>
                            <span
                                class="shrink-0 text-sm font-semibold tabular-nums"
                            >
                                {{ formatPrice(item.default_price) }}
                            </span>
                        </div>

                        <div
                            class="flex items-center justify-between gap-2 text-xs text-muted-foreground"
                        >
                            <span class="truncate">{{ item.code }}</span>
                            <span
                                v-if="item.type === 'product'"
                                class="shrink-0"
                                :class="stockClass(item)"
                            >
                                {{ item.quantity }} {{ item.unit }} in stock
                            </span>
                            <span v-else class="shrink-0"
                                >per {{ item.unit }}</span
                            >
                        </div>

                        <p
                            v-if="item.description"
                            class="line-clamp-1 text-xs text-muted-foreground"
                        >
                            {{ item.description }}
                        </p>

                        <!-- Actions (z-10 keeps them above the stretched link) -->
                        <div
                            class="relative z-10 mt-2 flex items-center gap-1.5"
                        >
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 flex-1"
                                as-child
                            >
                                <Link :href="show(item.id)">
                                    <Eye />
                                    View
                                </Link>
                            </Button>
                            <Button
                                v-if="can.edit"
                                size="icon"
                                variant="outline"
                                class="size-8"
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
                                variant="outline"
                                class="size-8"
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

                    <!-- QR popover -->
                    <template v-if="openQrId === item.id">
                        <button
                            type="button"
                            class="fixed inset-0 z-20 cursor-default"
                            aria-label="Close QR code"
                            @click="openQrId = null"
                        />
                        <div
                            class="absolute right-3 bottom-14 z-30 w-52 rounded-xl border bg-popover p-3 text-popover-foreground shadow-lg"
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

            <!-- Empty state -->
            <div
                v-else
                class="flex flex-col items-center gap-3 rounded-xl border border-dashed py-14 text-center"
            >
                <ImageIcon class="size-8 text-muted-foreground" />
                <div>
                    <p class="text-sm font-medium">
                        No products or services found
                    </p>
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

            <!-- Pagination -->
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    Showing {{ items.from ?? 0 }} to {{ items.to ?? 0 }} of
                    {{ items.total }} items
                </p>
                <div class="flex flex-wrap gap-1">
                    <template v-for="link in items.links" :key="link.label">
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
        </section>
    </div>
</template>
