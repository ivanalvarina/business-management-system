<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Printer } from '@lucide/vue';
import { onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/product-services';

type QrItem = {
    id: number;
    code: string;
    name: string;
    public_url: string;
};

defineProps<{
    items: QrItem[];
    title: string;
    layout: 'single' | 'bulk';
}>();

const qrUrl = (item: QrItem) =>
    `https://api.qrserver.com/v1/create-qr-code/?size=420x420&data=${encodeURIComponent(item.public_url)}`;

const printPage = () => window.print();

onMounted(() => {
    window.setTimeout(() => printPage(), 500);
});
</script>

<template>
    <Head :title="title" />

    <main class="min-h-screen bg-white text-black">
        <div
            class="no-print flex items-center justify-between border-b px-6 py-4"
        >
            <div>
                <h1 class="text-lg font-semibold">{{ title }}</h1>
                <p class="text-sm text-neutral-500">
                    {{ items.length }} product QR
                </p>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()">Back</Link>
                </Button>
                <Button type="button" @click="printPage">
                    <Printer />
                    Print
                </Button>
            </div>
        </div>

        <section v-if="items.length > 0" class="qr-sheet">
            <article
                v-for="item in items"
                :key="item.id"
                class="qr-label"
                :aria-label="`${item.code} ${item.name}`"
            >
                <img
                    class="qr-image"
                    :src="qrUrl(item)"
                    :alt="`QR code for ${item.name}`"
                />
                <div class="qr-caption">
                    <p class="qr-code">{{ item.code }}</p>
                    <p class="qr-name">{{ item.name }}</p>
                </div>
            </article>
        </section>

        <div
            v-else
            class="no-print flex min-h-[60vh] items-center justify-center text-sm text-neutral-500"
        >
            No printable public product QR codes found.
        </div>
    </main>
</template>

<style scoped>
.qr-sheet {
    background: white;
    display: grid;
    grid-template-columns: repeat(4, 45mm);
    gap: 8mm;
    justify-content: center;
    padding: 12mm;
}

.qr-label {
    break-inside: avoid;
    display: flex;
    width: 45mm;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    gap: 1.5mm;
    text-align: center;
}

.qr-image {
    display: block;
    height: 38mm;
    width: 38mm;
    object-fit: contain;
}

.qr-caption {
    width: 100%;
    color: #000;
    line-height: 1.15;
}

.qr-code {
    font-size: 8pt;
    font-weight: 700;
}

.qr-name {
    display: -webkit-box;
    overflow: hidden;
    font-size: 7pt;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

@media print {
    @page {
        size: A4;
        margin: 10mm;
    }

    .no-print {
        display: none !important;
    }

    .qr-sheet {
        grid-template-columns: repeat(4, 45mm);
        gap: 6mm;
        padding: 0;
    }

    .qr-label {
        page-break-inside: avoid;
    }

    .qr-image {
        height: 38mm;
        width: 38mm;
    }
}
</style>
