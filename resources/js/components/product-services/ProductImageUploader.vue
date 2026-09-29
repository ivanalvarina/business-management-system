<script setup lang="ts">
import { ImagePlus, Star, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ExistingImage = {
    id: number;
    url: string;
    original_filename: string | null;
    is_primary: boolean;
};

type PreviewImage = {
    id: string;
    file: File;
    url: string;
};

const props = withDefaults(
    defineProps<{
        existingImages?: ExistingImage[];
        errors?: Record<string, string>;
    }>(),
    {
        existingImages: () => [],
        errors: () => ({}),
    },
);

const emit = defineEmits<{
    'update:existingImageIds': [value: number[]];
    'update:newImages': [value: File[]];
    'update:primaryExistingImageId': [value: number | null];
    'update:primaryNewImageIndex': [value: number | null];
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const keptExistingIds = ref<number[]>(
    props.existingImages.map((image) => image.id),
);
const previews = ref<PreviewImage[]>([]);
const primary = ref(
    props.existingImages.find((image) => image.is_primary)
        ? `existing:${props.existingImages.find((image) => image.is_primary)?.id}`
        : props.existingImages[0]
          ? `existing:${props.existingImages[0].id}`
          : null,
);

const keptExistingImages = computed(() =>
    props.existingImages.filter((image) =>
        keptExistingIds.value.includes(image.id),
    ),
);
const totalCount = computed(
    () => keptExistingImages.value.length + previews.value.length,
);
const remainingSlots = computed(() => Math.max(0, 5 - totalCount.value));
const newFiles = computed(() => previews.value.map((preview) => preview.file));

const sync = () => {
    if (totalCount.value === 0) {
        primary.value = null;
    }

    if (primary.value?.startsWith('existing:')) {
        const imageId = Number(primary.value.replace('existing:', ''));

        if (!keptExistingIds.value.includes(imageId)) {
            primary.value = keptExistingImages.value[0]
                ? `existing:${keptExistingImages.value[0].id}`
                : previews.value[0]
                  ? `new:${previews.value[0].id}`
                  : null;
        }
    }

    emit('update:existingImageIds', keptExistingIds.value);
    emit('update:newImages', newFiles.value);
    emit(
        'update:primaryExistingImageId',
        primary.value?.startsWith('existing:')
            ? Number(primary.value.replace('existing:', ''))
            : null,
    );
    emit(
        'update:primaryNewImageIndex',
        primary.value?.startsWith('new:')
            ? previews.value.findIndex(
                  (preview) => `new:${preview.id}` === primary.value,
              )
            : null,
    );
};

watch([keptExistingIds, previews, primary], sync, {
    deep: true,
    immediate: true,
});

const revokePreview = (preview: PreviewImage) =>
    URL.revokeObjectURL(preview.url);

const addImages = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const selectedFiles = Array.from(input.files ?? []).slice(
        0,
        remainingSlots.value,
    );

    previews.value.push(
        ...selectedFiles.map((file) => ({
            id: crypto.randomUUID(),
            file,
            url: URL.createObjectURL(file),
        })),
    );

    if (primary.value === null && previews.value[0]) {
        primary.value = `new:${previews.value[0].id}`;
    }

    input.value = '';
};

const removeExisting = (image: ExistingImage) => {
    keptExistingIds.value = keptExistingIds.value.filter(
        (imageId) => imageId !== image.id,
    );
};

const removePreview = (preview: PreviewImage) => {
    revokePreview(preview);
    previews.value = previews.value.filter((item) => item.id !== preview.id);
};

onBeforeUnmount(() => previews.value.forEach(revokePreview));
</script>

<template>
    <div class="grid gap-3">
        <div
            class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <Label>Product images</Label>
                <p class="text-sm text-muted-foreground">Maximum 5 images</p>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ totalCount }} / 5 images
            </p>
        </div>

        <div
            v-if="totalCount > 0"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
        >
            <div
                v-for="image in keptExistingImages"
                :key="`existing-${image.id}`"
                class="relative overflow-hidden rounded-lg border bg-muted/30"
            >
                <img
                    :src="image.url"
                    :alt="image.original_filename ?? 'Product image'"
                    class="aspect-square w-full object-cover"
                />
                <div class="flex items-center justify-between gap-2 p-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            primary === `existing:${image.id}`
                                ? 'default'
                                : 'outline'
                        "
                        @click="primary = `existing:${image.id}`"
                    >
                        <Star />
                        Primary
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="removeExisting(image)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>

            <div
                v-for="preview in previews"
                :key="preview.id"
                class="relative overflow-hidden rounded-lg border bg-muted/30"
            >
                <img
                    :src="preview.url"
                    :alt="preview.file.name"
                    class="aspect-square w-full object-cover"
                />
                <div class="flex items-center justify-between gap-2 p-2">
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            primary === `new:${preview.id}`
                                ? 'default'
                                : 'outline'
                        "
                        @click="primary = `new:${preview.id}`"
                    >
                        <Star />
                        Primary
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="removePreview(preview)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground"
        >
            No product images selected.
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <Input
                ref="fileInput"
                type="file"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                multiple
                :disabled="remainingSlots === 0"
                @change="addImages"
            />
            <Button
                type="button"
                variant="outline"
                :disabled="remainingSlots === 0"
                @click="fileInput?.click()"
            >
                <ImagePlus />
                Add images
            </Button>
        </div>

        <p class="text-sm text-muted-foreground">
            {{ remainingSlots }} image slots remaining.
            <span v-if="remainingSlots === 0">Maximum reached.</span>
        </p>
        <InputError :message="errors.images" />
        <InputError :message="errors['images.0']" />
    </div>
</template>
