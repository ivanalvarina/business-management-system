<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Plus, Search, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import PageHeader from '@/components/app/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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

const submitSearch = () => {
    router.get(
        index.url(),
        {
            search: search.value || undefined,
            type: type.value === 'all' ? undefined : type.value,
        },
        { preserveState: true, replace: true },
    );
};

const deleteDocument = (document: DocumentRecord) => {
    if (!window.confirm(`Delete ${document.original_filename}?`)) {
        return;
    }

    router.delete(DocumentController.destroy.url(document.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Documents" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Documents"
            description="Search uploaded document metadata and access authorized downloads."
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

        <Card>
            <CardContent class="space-y-4">
                <form
                    class="grid gap-2 lg:grid-cols-[1fr_220px_auto]"
                    @submit.prevent="submitSearch"
                >
                    <Input
                        v-model="search"
                        placeholder="Search filename or type"
                    />
                    <select
                        v-model="type"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm"
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
                    <Button type="submit" variant="outline">
                        <Search />
                        Search
                    </Button>
                </form>

                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-3 font-medium">File</th>
                                <th class="px-4 py-3 font-medium">Parent</th>
                                <th class="px-4 py-3 font-medium">Type</th>
                                <th class="px-4 py-3 font-medium">
                                    Expiration
                                </th>
                                <th class="px-4 py-3 font-medium">Uploaded</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="document in documents.data"
                                :key="document.id"
                            >
                                <td class="px-4 py-4">
                                    <p class="font-medium">
                                        {{ document.original_filename }}
                                    </p>
                                    <p class="text-muted-foreground">
                                        {{ document.mime_type }} ·
                                        {{ formatBytes(document.file_size) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ document.parent.label }}
                                    <p class="text-muted-foreground">
                                        {{ label(document.parent.type) }}
                                    </p>
                                </td>
                                <td class="px-4 py-4">
                                    {{ document.document_type }}
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ document.expiration_date ?? 'None' }}
                                </td>
                                <td class="px-4 py-4 text-muted-foreground">
                                    {{ document.created_at ?? 'Unknown' }}
                                    <span v-if="document.uploader"
                                        >by {{ document.uploader.name }}</span
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="can.download"
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <a
                                                :href="
                                                    DocumentController.download.url(
                                                        document.id,
                                                    )
                                                "
                                            >
                                                <Download />
                                                Download
                                            </a>
                                        </Button>
                                        <Button
                                            v-if="can.delete"
                                            variant="outline"
                                            size="sm"
                                            @click="deleteDocument(document)"
                                        >
                                            <Trash2 />
                                            Delete
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="documents.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-10 text-center text-muted-foreground"
                                >
                                    No documents found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm text-muted-foreground">
                        Showing {{ documents.from ?? 0 }} to
                        {{ documents.to ?? 0 }} of
                        {{ documents.total }} documents
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <template
                            v-for="link in documents.links"
                            :key="link.label"
                        >
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
