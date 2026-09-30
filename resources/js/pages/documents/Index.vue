<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { create, index } from '@/routes/documents';

type DocumentRecord = {
    id: number;
    document_type: string;
    original_filename: string;
    mime_type: string;
    file_size: number;
    expiration_date: string | null;
    created_at: string | null;
    uploader: { id: number; name: string } | null;
    parent: { type: string; label: string };
};

type PaginationLink = { url: string | null; label: string; active: boolean };

const props = defineProps<{
    filters: { search: string; type: string };
    types: string[];
    documents: {
        data: DocumentRecord[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    can: { create: boolean; download: boolean; delete: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Documents', href: index() }] },
});

const search = ref(props.filters.search ?? '');
const type = ref(props.filters.type ?? 'all');

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const formatBytes = (bytes: number) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const applyFilters = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 300);
});

const deleteDocument = (document: DocumentRecord) => {
    if (!window.confirm(`Delete ${document.original_filename}?`)) {
        return;
    }

    router.delete(DocumentController.destroy.url(document.id), {
        preserveScroll: true,
    });
};

const prevLink = computed(() => props.documents.links[0]);
const nextLink = computed(
    () => props.documents.links[props.documents.links.length - 1],
);
const currentPage = computed(
    () => props.documents.links.find((link) => link.active)?.label ?? '1',
);
const showPagination = computed(() => props.documents.links.length > 3);
</script>

<template>
    <Head title="Documents" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Documents"
            description="Search uploaded documents and download the ones you can access."
        >
            <template #actions>
                <Button v-if="can.create" as-child>
                    <Link :href="create()">
                        <Plus />
                        New document
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="space-y-3">
            <div class="flex flex-col gap-2 md:flex-row">
                <div class="relative w-full md:max-w-xs">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search filename or type"
                        class="pl-9"
                    />
                </div>
                <select
                    v-model="type"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm text-muted-foreground md:w-52"
                    aria-label="Filter by parent type"
                    @change="applyFilters"
                >
                    <option value="all">All parent types</option>
                    <option
                        v-for="typeOption in types"
                        :key="typeOption"
                        :value="typeOption"
                    >
                        {{ label(typeOption) }}
                    </option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border bg-card">
                <table class="w-full min-w-[880px] text-sm">
                    <thead class="text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-normal">File</th>
                            <th class="px-5 py-3 font-normal">Parent</th>
                            <th class="px-5 py-3 font-normal">Type</th>
                            <th class="px-5 py-3 font-normal">Expiration</th>
                            <th class="px-5 py-3 font-normal">Uploaded</th>
                            <th class="w-24 px-5 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="document in documents.data"
                            :key="document.id"
                            class="border-t transition-colors hover:bg-muted/40"
                        >
                            <td class="px-5 py-3.5">
                                <p class="max-w-xs truncate font-medium">
                                    {{ document.original_filename }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ document.mime_type }},
                                    {{ formatBytes(document.file_size) }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="truncate">
                                    {{ document.parent.label }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ label(document.parent.type) }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <span
                                    class="rounded border px-1.5 py-0.5 text-xs text-muted-foreground"
                                >
                                    {{ label(document.document_type) }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-muted-foreground">
                                {{ document.expiration_date ?? 'None' }}
                            </td>
                            <td class="px-5 py-3.5">
                                <p class="text-muted-foreground">
                                    {{ document.created_at ?? 'Unknown' }}
                                </p>
                                <p
                                    v-if="document.uploader"
                                    class="text-xs text-muted-foreground"
                                >
                                    by {{ document.uploader.name }}
                                </p>
                            </td>
                            <td class="px-5 py-3.5">
                                <div class="flex items-center justify-end">
                                    <Button
                                        v-if="can.download"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-foreground"
                                        as-child
                                    >
                                        <a
                                            :href="
                                                DocumentController.download.url(
                                                    document.id,
                                                )
                                            "
                                            aria-label="Download document"
                                            title="Download"
                                        >
                                            <Download class="size-4" />
                                        </a>
                                    </Button>
                                    <Button
                                        v-if="can.delete"
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="text-muted-foreground hover:text-destructive"
                                        aria-label="Delete document"
                                        title="Delete"
                                        @click="deleteDocument(document)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="documents.data.length === 0">
                            <td
                                colspan="6"
                                class="border-t px-5 py-12 text-center text-muted-foreground"
                            >
                                No documents match your search.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between px-1">
                <p class="text-sm text-muted-foreground">
                    {{ documents.total }}
                    {{ documents.total === 1 ? 'document' : 'documents' }}
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