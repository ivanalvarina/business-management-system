<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import { Activity, LayoutDashboard } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/app/PageHeader.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type Metric = {
    label: string;
    value: string | number;
    description: string;
    /** optional: mga huling values para sa sparkline */
    trend?: number[];
    /** optional: hal. "+12% vs last month" */
    delta?: string;
};

type ActivityLog = {
    id: number;
    module: string;
    action: string;
    subject: string | null;
    created_at: string | null;
    user: string | null;
};

type Point = { label: string; value: number };

/** Lahat optional: kapag walang data, hindi lalabas ang chart card. */
type Charts = {
    quotationValueByMonth?: Point[]; // [{ label: 'Sep', value: 29450 }]
    purchaseRequestsByStatus?: Point[]; // [{ label: 'Approved', value: 5 }]
    documentsThisMonth?: Point[]; // [{ label: 'Quotations', value: 3 }]
    weeklyPurchaseOrders?: {
        labels: string[];
        client: number[];
        vendor: number[];
    } | null;
};

const props = defineProps<{
    metrics: Metric[];
    recentActivity: ActivityLog[];
    charts?: Charts;
}>();

usePoll(
    30000,
    {
        only: ['metrics', 'charts', 'recentActivity'],
    },
);

const chartData = computed<Charts>(() => props.charts ?? {});

const metricsView = computed<Metric[]>(() => props.metrics);

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const label = (value: string) =>
    value
        .replaceAll('_', ' ')
        .replaceAll('-', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

/* ---------- KPI cards ---------- */
const featuredLabels = [
    'Quotation value',
    'Quotations this month',
    'Client POs received',
    'Vendor POs this month',
];
const featured = computed(() => {
    const list = metricsView.value.filter((m) =>
        featuredLabels.includes(m.label),
    );

    return list.length > 0 ? list : metricsView.value;
});
const secondary = computed(() =>
    featured.value === metricsView.value
        ? []
        : metricsView.value.filter((m) => !featuredLabels.includes(m.label)),
);
const needsAction = (m: Metric) =>
    m.label.toLowerCase().includes('pending') && Number(m.value) > 0;
const pendingItems = computed(() => metricsView.value.filter(needsAction));

const sparkPoints = (trend: number[], w = 110, h = 34) => {
    const max = Math.max(...trend);
    const min = Math.min(...trend);

    return trend
        .map((v, i) => {
            const x = (i * w) / Math.max(trend.length - 1, 1);
            const y = h - 3 - ((v - min) / (max - min || 1)) * (h - 6);

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
};

/* ---------- Shared chart helpers ---------- */
const W = 680;
const H = 270;
const L = 48;
const B = 28;
const T = 10;
const compact = (v: number) =>
    new Intl.NumberFormat(undefined, { notation: 'compact' }).format(v);
const money = (v: number) =>
    v.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const niceMax = (max: number) => {
    if (max <= 0) {
        return 1;
    }

    const step = 10 ** Math.floor(Math.log10(max));

    return Math.ceil(max / step) * step;
};

/* ---------- Bar chart: quotation value ---------- */
const range = ref<3 | 6>(6);
const bar = computed(() => {
    const data = (chartData.value.quotationValueByMonth ?? []).slice(
        -range.value,
    );
    const max = niceMax(Math.max(0, ...data.map((d) => d.value)));
    const ih = H - B - T;
    const bw = (W - L) / Math.max(data.length, 1);

    return {
        ticks: [0, 1, 2, 3, 4].map((i) => ({
            y: T + ih - (ih * i) / 4,
            label: compact((max * i) / 4),
        })),
        items: data.map((d, i) => {
            const h = (ih * d.value) / max;

            return {
                ...d,
                h,
                y: T + ih - h,
                w: bw * 0.6,
                x: L + bw * i + bw * 0.2,
                cx: L + bw * i + bw / 2,
            };
        }),
    };
});

/* ---------- Donut: PR by status ---------- */
const donutClasses = [
    'stroke-foreground',
    'stroke-muted-foreground',
    'stroke-muted-foreground/40',
    'stroke-muted',
];
const dotClasses = [
    'bg-foreground',
    'bg-muted-foreground',
    'bg-muted-foreground/40',
    'bg-muted',
];
const donut = computed(() => {
    const data = chartData.value.purchaseRequestsByStatus ?? [];
    const total = data.reduce((sum, d) => sum + d.value, 0);
    const c = 2 * Math.PI * 54;
    let offset = 0;

    return {
        total,
        segments: data.map((d, i) => {
            const len = total ? (c * d.value) / total : 0;
            const seg = {
                ...d,
                cls: donutClasses[i % donutClasses.length],
                dot: dotClasses[i % dotClasses.length],
                dash: `${Math.max(len - 2, 0)} ${c - len + 2}`,
                offset: -offset,
            };
            offset += len;

            return seg;
        }),
    };
});

/* ---------- Line chart: weekly POs ---------- */
const LW = 560;
const LH = 230;
const line = computed(() => {
    const d = chartData.value.weeklyPurchaseOrders;

    if (!d) {
        return null;
    }

    const max = niceMax(Math.max(0, ...d.client, ...d.vendor));
    const ih = LH - B + 2 - T;
    const xs = (i: number) =>
        L - 18 + (i * (LW - L)) / Math.max(d.labels.length - 1, 1);
    const ys = (v: number) => T + ih - (ih * v) / max;
    const pts = (values: number[]) =>
        values.map((v, i) => ({ x: xs(i), y: ys(v), v, l: d.labels[i] }));

    return {
        ticks: [0, 1, 2, 3, 4].map((i) => ({
            y: ys((max * i) / 4),
            label: compact((max * i) / 4),
        })),
        labels: d.labels.map((l, i) => ({ l, x: xs(i) })),
        client: pts(d.client),
        vendor: pts(d.vendor),
    };
});
const asLine = (p: { x: number; y: number }[]) =>
    p.map((q) => `${q.x},${q.y}`).join(' ');

/* ---------- Documents this month ---------- */
const docs = computed(() => {
    const data = chartData.value.documentsThisMonth ?? [];
    const max = Math.max(1, ...data.map((d) => d.value));

    return data.map((d) => ({ ...d, pct: `${(d.value / max) * 100}%` }));
});

/* ---------- Activity ---------- */
const dot = (action: string) => {
    if (action.includes('approved')) {
        return 'bg-green-600';
    }

    if (action.includes('submitted') || action.includes('pending')) {
        return 'bg-amber-500';
    }

    if (action.includes('rejected') || action.includes('cancel')) {
        return 'bg-red-500';
    }

    return 'bg-foreground';
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- <PageHeader
            title="Dashboard"
            description="Operational snapshot based on your permissions and accessible companies."
            :icon="LayoutDashboard"
        /> -->

        <!-- Featured KPIs (may sparkline) -->
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="metric in featured" :key="metric.label" class="gap-2">
                <CardHeader>
                    <CardDescription>{{ metric.label }}</CardDescription>
                    <CardTitle class="text-3xl">{{ metric.value }}</CardTitle>
                </CardHeader>
                <CardContent
                    class="flex items-end justify-between gap-3 text-sm text-muted-foreground"
                >
                    <span
                        :class="{
                            'font-medium text-green-600': metric.delta?.startsWith('+'),
                        }"
                    >
                        {{ metric.delta ?? metric.description }}
                    </span>
                    <svg
                        v-if="metric.trend && metric.trend.length > 1"
                        viewBox="0 0 110 34"
                        class="h-8 w-[110px] shrink-0"
                    >
                        <polyline
                            :points="sparkPoints(metric.trend)"
                            fill="none"
                            stroke-width="2"
                            stroke-linejoin="round"
                            class="stroke-foreground"
                        />
                    </svg>
                </CardContent>
            </Card>
            <Card v-if="metrics.length === 0" class="md:col-span-2">
                <CardHeader>
                    <CardTitle>No visible metrics</CardTitle>
                    <CardDescription>
                        Metrics appear when your user has access to the related
                        modules.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>

        <!-- Secondary KPIs -->
        <div
            v-if="secondary.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <Card v-for="metric in secondary" :key="metric.label" class="py-4">
                <CardContent class="flex items-center justify-between gap-2">
                    <span class="text-sm text-muted-foreground">{{
                        metric.label
                    }}</span>
                    <span class="flex items-center gap-2 text-2xl font-semibold">
                        {{ metric.value }}
                        <span
                            v-if="needsAction(metric)"
                            class="shrink-0 rounded-md border border-amber-500 px-2 py-0.5 text-xs font-normal whitespace-nowrap text-amber-600"
                            >Needs action</span
                        >
                    </span>
                </CardContent>
            </Card>
        </div>

        <!-- Quotation value + status donut -->
        <div
            v-if="chartData.quotationValueByMonth?.length || donut.segments.length"
            class="grid gap-4 xl:grid-cols-[2fr_1fr]"
        >
            <Card v-if="chartData.quotationValueByMonth?.length">
                <CardHeader class="flex flex-row items-start justify-between gap-3">
                    <div>
                        <CardTitle>Quotation value</CardTitle>
                        <CardDescription>
                            Total value of quotations created per month
                        </CardDescription>
                    </div>
                    <div class="inline-flex overflow-hidden rounded-md border text-xs">
                        <button
                            v-for="n in [3, 6] as const"
                            :key="n"
                            type="button"
                            class="px-3 py-1.5"
                            :class="
                                range === n
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground'
                            "
                            @click="range = n"
                        >
                            {{ n }}M
                        </button>
                    </div>
                </CardHeader>
                <CardContent>
                    <svg :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full">
                        <g v-for="t in bar.ticks" :key="t.y">
                            <line :x1="L" :x2="W" :y1="t.y" :y2="t.y" class="stroke-border" />
                            <text :x="L - 8" :y="t.y + 4" text-anchor="end" class="fill-muted-foreground text-[11px]">{{ t.label }}</text>
                        </g>
                        <g v-for="(b, i) in bar.items" :key="b.label">
                            <rect
                                :x="b.x"
                                :y="b.y"
                                :width="b.w"
                                :height="b.h"
                                rx="6"
                                :class="i === bar.items.length - 1 ? 'fill-foreground' : 'fill-muted-foreground/30'"
                            >
                                <title>{{ b.label }}: {{ money(b.value) }}</title>
                            </rect>
                            <text :x="b.cx" :y="H - 8" text-anchor="middle" class="fill-muted-foreground text-[11px]">{{ b.label }}</text>
                        </g>
                    </svg>
                </CardContent>
            </Card>

            <Card v-if="donut.segments.length">
                <CardHeader>
                    <CardTitle>Purchase requests by status</CardTitle>
                    <CardDescription>Current company</CardDescription>
                </CardHeader>
                <CardContent class="flex flex-wrap items-center gap-5">
                    <svg viewBox="0 0 140 140" class="w-40">
                        <circle
                            v-for="s in donut.segments"
                            :key="s.label"
                            cx="70"
                            cy="70"
                            r="54"
                            fill="none"
                            stroke-width="18"
                            transform="rotate(-90 70 70)"
                            :stroke-dasharray="s.dash"
                            :stroke-dashoffset="s.offset"
                            :class="s.cls"
                        >
                            <title>{{ s.label }}: {{ s.value }}</title>
                        </circle>
                        <text x="70" y="72" text-anchor="middle" class="fill-foreground text-2xl font-semibold">{{ donut.total }}</text>
                        <text x="70" y="90" text-anchor="middle" class="fill-muted-foreground text-[11px]">total</text>
                    </svg>
                    <ul class="min-w-32 flex-1 space-y-1 text-sm">
                        <li
                            v-for="s in donut.segments"
                            :key="s.label"
                            class="flex items-center justify-between"
                        >
                            <span class="flex items-center gap-2">
                                <span class="size-2.5 rounded-sm" :class="s.dot" />
                                {{ s.label }}
                            </span>
                            <span class="font-medium">{{ s.value }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <!-- Weekly POs + documents this month -->
        <div v-if="line || docs.length" class="grid gap-4 xl:grid-cols-2">
            <Card v-if="line">
                <CardHeader>
                    <CardTitle>Client POs vs vendor POs</CardTitle>
                    <CardDescription>Weekly count</CardDescription>
                </CardHeader>
                <CardContent>
                    <svg :viewBox="`0 0 ${LW} ${LH}`" class="h-auto w-full">
                        <g v-for="t in line.ticks" :key="t.y">
                            <line :x1="L - 18" :x2="LW" :y1="t.y" :y2="t.y" class="stroke-border" />
                            <text :x="L - 26" :y="t.y + 4" text-anchor="end" class="fill-muted-foreground text-[11px]">{{ t.label }}</text>
                        </g>
                        <polyline :points="asLine(line.vendor)" fill="none" stroke-width="2.5" stroke-linejoin="round" class="stroke-muted-foreground" />
                        <polyline :points="asLine(line.client)" fill="none" stroke-width="2.5" stroke-linejoin="round" class="stroke-foreground" />
                        <circle v-for="p in line.vendor" :key="`v${p.l}`" :cx="p.x" :cy="p.y" r="4" stroke-width="2" class="fill-card stroke-muted-foreground"><title>{{ p.l }} vendor POs: {{ p.v }}</title></circle>
                        <circle v-for="p in line.client" :key="`c${p.l}`" :cx="p.x" :cy="p.y" r="4" stroke-width="2" class="fill-card stroke-foreground"><title>{{ p.l }} client POs: {{ p.v }}</title></circle>
                        <text v-for="x in line.labels" :key="x.l" :x="x.x" :y="LH - 6" text-anchor="middle" class="fill-muted-foreground text-[11px]">{{ x.l }}</text>
                    </svg>
                    <div class="mt-2 flex gap-4 text-sm text-muted-foreground">
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-foreground" />Client POs received</span>
                        <span class="flex items-center gap-2"><span class="size-2.5 rounded-sm bg-muted-foreground" />Vendor POs issued</span>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="docs.length">
                <CardHeader>
                    <CardTitle>Documents this month</CardTitle>
                    <CardDescription>Created within current month</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="(d, i) in docs" :key="d.label">
                        <div class="mb-1 flex justify-between text-sm">
                            <span>{{ d.label }}</span>
                            <span class="font-semibold">{{ d.value }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-muted">
                            <div
                                class="h-2 rounded-full"
                                :class="i === 0 ? 'bg-foreground' : 'bg-muted-foreground/40'"
                                :style="{ width: d.pct }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Recent activity + needs action -->
        <div class="grid gap-4 xl:grid-cols-[2fr_1fr]">
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Activity class="size-5 text-muted-foreground" />
                        <CardTitle>Recent activity</CardTitle>
                    </div>
                    <CardDescription>
                        Latest audited activity visible to audit trail users.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li
                            v-for="activity in recentActivity"
                            :key="activity.id"
                            class="flex items-start gap-3 py-3 text-sm first:pt-0"
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full"
                                :class="dot(activity.action)"
                            />
                            <div class="min-w-0">
                                <p class="font-medium">
                                    {{ label(activity.module) }} /
                                    {{ label(activity.action) }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ activity.user ?? 'System' }}
                                    <span v-if="activity.subject">
                                        on {{ activity.subject }}
                                    </span>
                                </p>
                            </div>
                            <time class="ml-auto shrink-0 text-xs text-muted-foreground">
                                {{ activity.created_at ?? 'Unknown time' }}
                            </time>
                        </li>
                    </ul>
                    <p
                        v-if="recentActivity.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No recent activity available.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Needs your action</CardTitle>
                    <CardDescription>Approvals waiting on you</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div
                        v-for="m in pendingItems"
                        :key="m.label"
                        class="flex items-center justify-between rounded-lg border p-3"
                    >
                        <span>{{ m.label }}</span>
                        <span class="font-semibold">{{ m.value }}</span>
                    </div>
                    <p
                        v-if="pendingItems.length === 0"
                        class="text-muted-foreground"
                    >
                        All caught up.
                    </p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
