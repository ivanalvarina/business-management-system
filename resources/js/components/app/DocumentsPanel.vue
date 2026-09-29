<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Download, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type DocumentRecord = {
    id: number;
    document_type: string;
    original_filename: string;
    mime_type: string;
    file_size: number;
    expiration_date: string | null;
    created_at: string | null;
    uploader: { id: number; name: string } | null;
};

const props = defineProps<{
    documentableType: string;
    documentableId: number;
    documents: DocumentRecord[];
    can: {
        upload: boolean;
        download: boolean;
        delete: boolean;
    };
}>();

const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    documentable_type: string;
    documentable_id: number;
    document_type: string;
    expiration_date: string;
    file: File | null;
}>({
    documentable_type: props.documentableType,
    documentable_id: props.documentableId,
    document_type: '',
    expiration_date: '',
    file: null,
});

const formatBytes = (bytes: number) => {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
};

const setFile = (event: Event) => {
    const input = event.target as HTMLInputElement;

    form.file = input.files?.[0] ?? null;
};

const submit = () => {
    form.post(DocumentController.store.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('document_type', 'expiration_date', 'file');

            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
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
    <Card>
        <CardHeader>
            <CardTitle>Documents</CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <form
                v-if="can.upload"
                class="grid gap-3 rounded-lg border p-4 lg:grid-cols-[180px_180px_1fr_auto]"
                @submit.prevent="submit"
            >
                <div class="grid gap-2">
                    <Label for="document_type">Type</Label>
                    <Input
                        id="document_type"
                        v-model="form.document_type"
                        placeholder="Contract"
                    />
                    <InputError :message="form.errors.document_type" />
                </div>
                <div class="grid gap-2">
                    <Label for="expiration_date">Expiration</Label>
                    <Input
                        id="expiration_date"
                        v-model="form.expiration_date"
                        type="date"
                    />
                    <InputError :message="form.errors.expiration_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="file">File</Label>
                    <Input
                        id="file"
                        ref="fileInput"
                        type="file"
                        @change="setFile"
                    />
                    <InputError :message="form.errors.file" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="form.processing">
                        <Upload />
                        Upload
                    </Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">File</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Expiration</th>
                            <th class="px-4 py-3 font-medium">Uploaded</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="document in documents" :key="document.id">
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
                                {{ document.document_type }}
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">
                                {{ document.expiration_date ?? 'None' }}
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">
                                {{ document.created_at ?? 'Unknown' }}
                                <span v-if="document.uploader">
                                    by {{ document.uploader.name }}
                                </span>
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
                        <tr v-if="documents.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-10 text-center text-muted-foreground"
                            >
                                No documents uploaded.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </CardContent>
    </Card>
</template>
