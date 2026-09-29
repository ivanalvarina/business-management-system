<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    Building2,
    CircleAlert,
    CircleCheck,
    CircleX,
    ImageIcon,
    Mail,
    MessageCircle,
    Phone,
    Share2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';

type ProductImage = {
    id: number;
    url: string;
    is_primary: boolean;
};

type Availability = 'in_stock' | 'low_stock' | 'out_of_stock';

const props = defineProps<{
    product: {
        code: string;
        name: string;
        description: string | null;
        unit: string;
        availability: Availability;
        primary_image: ProductImage | null;
        images: ProductImage[];
        type?: 'product' | 'service';
        default_price?: string | null;
        company?: {
            name: string;
            logo_url?: string | null;
            phone?: string | null;
            email?: string | null;
        } | null;
    };
}>();

const selectedImage = ref<ProductImage | null>(
    props.product.primary_image ?? props.product.images[0] ?? null,
);

const copied = ref(false);

const availability = computed(() => {
    const map = {
        in_stock: {
            label: 'In stock',
            icon: CircleCheck,
            class: 'bg-green-500/10 text-green-700 dark:text-green-400',
        },
        low_stock: {
            label: 'Low stock',
            icon: CircleAlert,
            class: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
        },
        out_of_stock: {
            label: 'Out of stock',
            icon: CircleX,
            class: 'bg-red-500/10 text-red-700 dark:text-red-400',
        },
    } as const;

    return map[props.product.availability];
});

const price = computed(() => {
    const value = props.product.default_price;

    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Number(value);

    return Number.isNaN(number)
        ? value
        : number.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          });
});

const typeLabel = computed(() =>
    props.product.type === 'service' ? 'Service' : 'Product',
);

const inquiryHref = computed(() => {
    const company = props.product.company;

    if (company?.email) {
        const subject = encodeURIComponent(
            `Inquiry: ${props.product.name} (${props.product.code})`,
        );

        return `mailto:${company.email}?subject=${subject}`;
    }

    return company?.phone ? `tel:${company.phone}` : null;
});

const share = async () => {
    const url = window.location.href;

    if (navigator.share) {
        try {
            await navigator.share({ title: props.product.name, url });
        } catch {
            // User dismissed the share sheet.
        }

        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable; nothing else to do.
    }
};
</script>

<template>
    <Head :title="product.name" />

    <main class="min-h-screen bg-background text-foreground">
        <!-- Company header: centered logo (falls back to icon + name) -->
        <header v-if="product.company" class="border-b">
            <div
                class="mx-auto flex max-w-4xl items-center justify-center px-4 py-3"
            >
                <img
                    v-if="product.company.logo_url"
                    :src="product.company.logo_url"
                    :alt="product.company.name"
                    class="h-9 w-auto max-w-64 object-contain"
                />
                <div v-else class="flex items-center gap-2 text-sm font-medium">
                    <Building2 class="size-4 shrink-0" />
                    <span class="truncate">{{ product.company.name }}</span>
                </div>
            </div>
        </header>

        <div
            class="mx-auto grid max-w-4xl gap-6 px-4 py-6 md:grid-cols-2 md:items-start md:gap-10 md:py-10"
        >
            <!-- Gallery -->
            <section class="space-y-3 md:sticky md:top-6">
                <div
                    class="flex aspect-square items-center justify-center overflow-hidden rounded-xl border bg-muted/30 md:aspect-[4/3]"
                >
                    <img
                        v-if="selectedImage"
                        :src="selectedImage.url"
                        :alt="product.name"
                        class="h-full w-full object-contain p-2"
                    />
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 text-muted-foreground"
                    >
                        <ImageIcon class="size-10" />
                        <span class="text-sm">No image available</span>
                    </div>
                </div>

                <!-- p-1 + border (not ring) so the selected state is never clipped -->
                <div
                    v-if="product.images.length > 1"
                    class="flex gap-2 overflow-x-auto p-1"
                >
                    <button
                        v-for="image in product.images"
                        :key="image.id"
                        type="button"
                        class="size-16 shrink-0 overflow-hidden rounded-lg bg-muted/30 transition"
                        :class="
                            selectedImage?.id === image.id
                                ? 'border-2 border-foreground'
                                : 'border opacity-70 hover:opacity-100'
                        "
                        :aria-label="`View image ${image.id}`"
                        :aria-pressed="selectedImage?.id === image.id"
                        @click="selectedImage = image"
                    >
                        <img
                            :src="image.url"
                            :alt="product.name"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                    </button>
                </div>
            </section>

            <!-- Details -->
            <section class="flex flex-col gap-4">
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold sm:text-3xl">
                        {{ product.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Code: {{ product.code }}
                    </p>
                </div>

                <div v-if="price" class="flex items-baseline gap-2">
                    <span class="text-3xl font-semibold tabular-nums">
                        ₱{{ price }}
                    </span>
                    <span class="text-sm text-muted-foreground">
                        / {{ product.unit }}
                    </span>
                </div>

                <div>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium"
                        :class="availability.class"
                    >
                        <component :is="availability.icon" class="size-4" />
                        {{ availability.label }}
                    </span>
                </div>

                <dl
                    class="grid grid-cols-2 divide-x overflow-hidden rounded-lg border text-sm"
                >
                    <div class="px-3 py-2">
                        <dt class="text-muted-foreground">Unit</dt>
                        <dd class="font-medium">{{ product.unit }}</dd>
                    </div>
                    <div class="px-3 py-2">
                        <dt class="text-muted-foreground">Type</dt>
                        <dd class="font-medium">{{ typeLabel }}</dd>
                    </div>
                </dl>

                <p
                    v-if="product.description"
                    class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ product.description }}
                </p>

                <!-- Actions -->
                <div class="mt-2 flex flex-col gap-2">
                    <Button v-if="inquiryHref" size="lg" as-child>
                        <a :href="inquiryHref">
                            <MessageCircle />
                            Inquire about this item
                        </a>
                    </Button>

                    <div class="flex gap-2">
                        <Button
                            v-if="product.company?.phone"
                            variant="outline"
                            class="flex-1"
                            as-child
                        >
                            <a :href="`tel:${product.company.phone}`">
                                <Phone />
                                Call
                            </a>
                        </Button>

                        <Button
                            v-if="product.company?.email"
                            variant="outline"
                            class="flex-1"
                            as-child
                        >
                            <a :href="`mailto:${product.company.email}`">
                                <Mail />
                                Email
                            </a>
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            aria-label="Share this product"
                            @click="share"
                        >
                            <Share2 />
                        </Button>
                    </div>

                    <p
                        v-if="copied"
                        class="text-xs text-muted-foreground"
                        role="status"
                    >
                        Link copied
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
