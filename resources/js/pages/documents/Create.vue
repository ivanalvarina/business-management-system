<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import DocumentController from '@/actions/App/Http/Controllers/DocumentController';
import PageHeader from '@/components/app/PageHeader.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/documents';

type ParentOption = {
    id: number;
    type: string;
    label: string;
    meta: string | null;
};

const props = defineProps<{
    parentOptions: ParentOption[];
    types: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Documents', href: index() },
            { title: 'Upload', href: '#' },
        ],
    },
});

const fileInput = ref<HTMLInputElement | null>(null);

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const availableTypes = computed(() =>
    props.types.filter((type) =>
        props.parentOptions.some((option) => option.type === type),
    ),
);

const form = useForm<{
    documentable_type: string;
    documentable_id: number | null;
    document_type: string;
    expiration_date: string;
    file: File | null;
}>({
    documentable_type: availableTypes.value[0] ?? '',
    documentable_id: props.parentOptions[0]?.id ?? null,
    document_type: '',
    expiration_date: '',
    file: null,
});

const filteredParents = computed(() =>
    props.parentOptions.filter(
        (option) => option.type === form.documentable_type,
    ),
);

watch(
    () => form.documentable_type,
    () => {
        form.documentable_id = filteredParents.value[0]?.id ?? null;
    },
);

const setFile = (event: Event) => {
    const input = event.target as HTMLInputElement;

    form.file = input.files?.[0] ?? null;
};

const submit = () => {
    form.post(DocumentController.store.url(), {
        forceFormData: true,
        onSuccess: () => router.visit(index()),
    });
};
</script>

<template>
    <Head title="Upload document" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Upload document"
            description="Attach a private document to an authorized company, client, vendor, or transaction record."
        />

        <Card>
            <CardContent>
                <form
                    v-if="parentOptions.length > 0"
                    class="max-w-7xl space-y-6"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="documentable_type">Parent type</Label>
                            <select
                                id="documentable_type"
                                v-model="form.documentable_type"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="typeOption in availableTypes"
                                    :key="typeOption"
                                    :value="typeOption"
                                >
                                    {{ label(typeOption) }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.documentable_type"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="documentable_id">Parent record</Label>
                            <select
                                id="documentable_id"
                                v-model.number="form.documentable_id"
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="parent in filteredParents"
                                    :key="`${parent.type}-${parent.id}`"
                                    :value="parent.id"
                                >
                                    {{ parent.label }}
                                    {{ parent.meta ? `(${parent.meta})` : '' }}
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.documentable_id"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="document_type">Document type</Label>
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
                            <InputError
                                :message="form.errors.expiration_date"
                            />
                        </div>

                        <div class="grid gap-2 md:col-span-2">
                            <Label for="file">File</Label>
                            <Input
                                id="file"
                                ref="fileInput"
                                type="file"
                                @change="setFile"
                            />
                            <InputError :message="form.errors.file" />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button :disabled="form.processing">
                            <Upload />
                            Upload document
                        </Button>
                        <Button variant="outline" as-child>
                            <Link :href="index()">Cancel</Link>
                        </Button>
                    </div>
                </form>

                <div
                    v-else
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No authorized parent records are available for upload.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
