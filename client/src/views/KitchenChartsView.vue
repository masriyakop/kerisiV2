<script setup lang="ts">
import { computed } from "vue";
import {
  BarChart3,
  TrendingUp,
  Layers,
  Filter,
  PieChart,
  Table2,
  BookOpen,
  Database,
  CheckCircle2,
  XCircle,
  Activity,
  Target,
  Gauge,
  GitBranch,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";

// ── Data ──
const monthlyViews = [120, 180, 150, 220, 260, 310, 280];
const monthlyPosts = [4, 6, 5, 8, 9, 12, 10];
const labels = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const monthlyTraffic = [
  { month: "Jan", organic: 4200, social: 1500, referral: 900 },
  { month: "Feb", organic: 4700, social: 1800, referral: 1100 },
  { month: "Mar", organic: 5100, social: 2100, referral: 1300 },
  { month: "Apr", organic: 5600, social: 2400, referral: 1450 },
  { month: "May", organic: 6000, social: 2600, referral: 1600 },
  { month: "Jun", organic: 6400, social: 2900, referral: 1850 },
];

const funnel = [
  { stage: "Visitors", value: 12000 },
  { stage: "Signups", value: 3600 },
  { stage: "Qualified Leads", value: 1250 },
  { stage: "Customers", value: 420 },
];

const sourceShare = [
  { source: "Organic", value: 52 },
  { source: "Social", value: 26 },
  { source: "Referral", value: 14 },
  { source: "Direct", value: 8 },
];

const sparkData = [
  { label: "Page Views", value: "24.8K", change: "+12.3%", positive: true, data: [30, 45, 38, 52, 48, 65, 58, 72, 68, 80, 75, 88] },
  { label: "Bounce Rate", value: "34.2%", change: "-2.1%", positive: true, data: [60, 55, 58, 50, 52, 48, 45, 47, 42, 40, 38, 36] },
  { label: "Avg. Duration", value: "3m 42s", change: "+8.5%", positive: true, data: [20, 25, 22, 30, 28, 35, 32, 40, 38, 42, 45, 48] },
  { label: "Conversions", value: "1,247", change: "-0.8%", positive: false, data: [40, 42, 38, 45, 40, 43, 41, 39, 42, 40, 38, 37] },
];

const horizontalBars = [
  { label: "Chrome", value: 64 },
  { label: "Safari", value: 19 },
  { label: "Firefox", value: 9 },
  { label: "Edge", value: 5 },
  { label: "Other", value: 3 },
];

const progressRings = [
  { label: "Uptime", value: 99.9, color: "text-emerald-500" },
  { label: "CPU", value: 42, color: "text-violet-500" },
  { label: "Memory", value: 67, color: "text-amber-500" },
  { label: "Disk", value: 28, color: "text-blue-500" },
];

const heatmapData = [
  [3, 7, 2, 8, 5, 1, 4],
  [6, 2, 9, 4, 7, 3, 8],
  [1, 5, 3, 7, 2, 9, 6],
  [8, 4, 6, 1, 9, 5, 3],
];
const heatmapRows = ["Week 1", "Week 2", "Week 3", "Week 4"];
const heatmapCols = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

// ── Computed ──
const maxViews = computed(() => Math.max(...monthlyViews));
const maxPosts = computed(() => Math.max(...monthlyPosts));
const maxTraffic = computed(() =>
  Math.max(...monthlyTraffic.map((item) => item.organic + item.social + item.referral)),
);
const maxFunnel = computed(() => Math.max(...funnel.map((item) => item.value)));

const linePoints = computed(() => {
  const width = 560;
  const height = 180;
  const step = width / (monthlyViews.length - 1);
  return monthlyViews
    .map((value, index) => {
      const x = index * step;
      const y = height - (value / maxViews.value) * height;
      return `${x},${y}`;
    })
    .join(" ");
});

const areaPath = computed(() => {
  const width = 560;
  const height = 180;
  const step = width / (monthlyViews.length - 1);
  const points = monthlyViews.map((value, index) => {
    const x = index * step;
    const y = height - (value / maxViews.value) * height;
    return `${x},${y}`;
  });
  return `M0,${height} L${points.join(" L")} L${width},${height} Z`;
});

const totalShare = computed(() => sourceShare.reduce((sum, row) => sum + row.value, 0));
const pieGradient = computed(() => {
  let start = 0;
  const colors = ["#7c3aed", "#a78bfa", "#c4b5fd", "#ddd6fe"];
  const segments = sourceShare.map((row, index) => {
    const end = start + row.value;
    const segment = `${colors[index % colors.length]} ${start}% ${end}%`;
    start = end;
    return segment;
  });
  return `conic-gradient(${segments.join(", ")})`;
});

function sparklinePath(data: number[], w = 120, h = 32) {
  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;
  const step = w / (data.length - 1);
  return data.map((v, i) => {
    const x = i * step;
    const y = h - ((v - min) / range) * h;
    return `${i === 0 ? "M" : "L"}${x},${y}`;
  }).join(" ");
}

function sparklineArea(data: number[], w = 120, h = 32) {
  const path = sparklinePath(data, w, h);
  return `${path} L${w},${h} L0,${h} Z`;
}

function heatmapOpacity(value: number) {
  return 0.15 + (value / 9) * 0.85;
}

const ringCircumference = 2 * Math.PI * 40;

const sectionLinks = [
  { id: "stat-cards", label: "Stat Cards", icon: Activity },
  { id: "bar-chart", label: "Bar Chart", icon: BarChart3 },
  { id: "line-chart", label: "Line Chart", icon: TrendingUp },
  { id: "horizontal-bar", label: "Horizontal Bar", icon: BarChart3 },
  { id: "stacked-bar", label: "Stacked Bar", icon: Layers },
  { id: "funnel", label: "Funnel", icon: Filter },
  { id: "pie-chart", label: "Pie Chart", icon: PieChart },
  { id: "progress-rings", label: "Progress Rings", icon: Gauge },
  { id: "heatmap", label: "Heatmap", icon: Target },
  { id: "timeline", label: "Timeline", icon: GitBranch },
  { id: "data-table", label: "Data Table", icon: Table2 },
];
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Kitchen Sink: Charts</h1>
        <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-medium text-violet-700">{{ sectionLinks.length }} Components</span>
      </div>

      <!-- ───── Quick Jump Nav ───── -->
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Jump</p>
        <div class="flex flex-wrap gap-1.5">
          <a
            v-for="link in sectionLinks"
            :key="link.id"
            :href="`#${link.id}`"
            class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition-all hover:bg-violet-50 hover:text-violet-700"
          >
            <component :is="link.icon" class="h-4 w-4 text-slate-400 transition-colors group-hover:text-violet-500" />
            {{ link.label }}
          </a>
        </div>
      </div>

      <!-- ═══════ STAT CARDS WITH SPARKLINES ═══════ -->
      <section id="stat-cards" class="scroll-mt-24 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article v-for="stat in sparkData" :key="stat.label" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <p class="text-xs font-medium text-slate-500">{{ stat.label }}</p>
            <span
              class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="stat.positive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'"
            >{{ stat.change }}</span>
          </div>
          <p class="mt-1 text-xl font-bold text-slate-900">{{ stat.value }}</p>
          <svg :viewBox="`0 0 120 32`" class="mt-3 h-8 w-full">
            <defs>
              <linearGradient :id="`spark-fill-${stat.label}`" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.2" />
                <stop offset="100%" stop-color="#7c3aed" stop-opacity="0" />
              </linearGradient>
            </defs>
            <path :d="sparklineArea(stat.data)" :fill="`url(#spark-fill-${stat.label})`" />
            <path :d="sparklinePath(stat.data)" fill="none" stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ BAR CHART ═══════ -->
        <article id="bar-chart" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <BarChart3 class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Bar Chart</h2>
          </div>
          <div class="p-4">
            <div class="flex h-64 items-end justify-between gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div v-for="(value, index) in monthlyPosts" :key="labels[index]" class="flex flex-1 flex-col items-center gap-2">
                <div class="text-xs font-medium text-slate-500">{{ value }}</div>
                <div
                  class="w-full rounded-t-md bg-gradient-to-t from-violet-600 to-violet-400 transition-all"
                  :style="{ height: `${(value / maxPosts) * 180}px`, minHeight: '10px' }"
                />
                <div class="text-xs font-medium text-slate-600">{{ labels[index] }}</div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ LINE CHART ═══════ -->
        <article id="line-chart" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <TrendingUp class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Line Chart</h2>
          </div>
          <div class="p-4">
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <svg viewBox="0 0 560 220" class="h-64 w-full">
                <defs>
                  <linearGradient id="areaFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#7c3aed" stop-opacity="0.15" />
                    <stop offset="100%" stop-color="#7c3aed" stop-opacity="0" />
                  </linearGradient>
                </defs>
                <line v-for="i in 4" :key="`grid-${i}`" x1="0" :y1="(i - 1) * 60" x2="560" :y2="(i - 1) * 60" stroke="#e2e8f0" stroke-width="1" stroke-dasharray="4 4" />
                <line x1="0" y1="180" x2="560" y2="180" stroke="#cbd5e1" stroke-width="1" />
                <path :d="areaPath" fill="url(#areaFill)" />
                <polyline :points="linePoints" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                <g v-for="(value, index) in monthlyViews" :key="`point-${labels[index]}`">
                  <circle
                    :cx="(560 / (monthlyViews.length - 1)) * index"
                    :cy="180 - (value / maxViews) * 180"
                    r="5"
                    fill="white"
                    stroke="#7c3aed"
                    stroke-width="2.5"
                  />
                </g>
              </svg>
              <div class="mt-2 grid grid-cols-7 gap-2 text-center text-xs font-medium text-slate-600">
                <span v-for="label in labels" :key="label">{{ label }}</span>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ HORIZONTAL BAR ═══════ -->
        <article id="horizontal-bar" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <BarChart3 class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Horizontal Bar (Browser Share)</h2>
          </div>
          <div class="p-4">
            <div class="space-y-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div v-for="bar in horizontalBars" :key="bar.label" class="space-y-1">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-slate-700">{{ bar.label }}</span>
                  <span class="font-mono text-slate-500">{{ bar.value }}%</span>
                </div>
                <div class="h-4 rounded-full bg-violet-100">
                  <div
                    class="h-full rounded-full bg-gradient-to-r from-violet-600 to-violet-400 transition-all"
                    :style="{ width: `${bar.value}%` }"
                  />
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ STACKED BAR ═══════ -->
        <article id="stacked-bar" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Layers class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Stacked Bar (Traffic Mix)</h2>
          </div>
          <div class="p-4">
            <div class="space-y-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div v-for="item in monthlyTraffic" :key="item.month" class="space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-slate-700">{{ item.month }}</span>
                  <span class="font-mono text-slate-500">{{ (item.organic + item.social + item.referral).toLocaleString() }}</span>
                </div>
                <div class="flex h-5 overflow-hidden rounded-md bg-violet-100">
                  <div class="bg-violet-700 transition-all" :style="{ width: `${((item.organic / maxTraffic) * 100).toFixed(2)}%` }" />
                  <div class="bg-violet-400 transition-all" :style="{ width: `${((item.social / maxTraffic) * 100).toFixed(2)}%` }" />
                  <div class="bg-violet-200 transition-all" :style="{ width: `${((item.referral / maxTraffic) * 100).toFixed(2)}%` }" />
                </div>
              </div>
              <div class="flex items-center gap-5 border-t border-slate-200 pt-3 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded bg-violet-700" />Organic</span>
                <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded bg-violet-400" />Social</span>
                <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded bg-violet-200" />Referral</span>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ FUNNEL ═══════ -->
        <article id="funnel" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Filter class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Funnel Chart</h2>
          </div>
          <div class="p-4">
            <div class="space-y-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div v-for="(step, idx) in funnel" :key="step.stage" class="space-y-1.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-medium text-slate-700">{{ step.stage }}</span>
                  <span class="font-mono text-slate-500">{{ step.value.toLocaleString() }}</span>
                </div>
                <div class="h-8 rounded-lg bg-violet-100 p-1">
                  <div
                    class="flex h-full items-center rounded-md px-2.5 text-xs font-medium text-white transition-all"
                    :class="idx === 0 ? 'bg-violet-700' : idx === 1 ? 'bg-violet-600' : idx === 2 ? 'bg-violet-500' : 'bg-violet-400'"
                    :style="{ width: `${Math.max((step.value / maxFunnel) * 100, 10).toFixed(2)}%` }"
                  >
                    {{ Math.round((step.value / maxFunnel) * 100) }}%
                  </div>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ PIE CHART ═══════ -->
        <article id="pie-chart" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <PieChart class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Pie Chart Breakdown</h2>
          </div>
          <div class="p-4">
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div class="flex items-center gap-8">
                <div class="relative h-40 w-40 shrink-0">
                  <div class="h-full w-full rounded-full shadow-inner" :style="{ background: pieGradient }" />
                  <div class="absolute inset-4 rounded-full bg-white" />
                </div>
                <div class="flex-1 space-y-2.5">
                  <div v-for="(row, idx) in sourceShare" :key="row.source" class="flex items-center justify-between text-sm">
                    <span class="flex items-center gap-2 text-slate-700">
                      <span
                        class="inline-block h-3 w-3 rounded"
                        :class="idx === 0 ? 'bg-violet-600' : idx === 1 ? 'bg-violet-400' : idx === 2 ? 'bg-violet-300' : 'bg-violet-200'"
                      />
                      {{ row.source }}
                    </span>
                    <span class="font-mono text-sm font-medium text-slate-900">{{ row.value }}%</span>
                  </div>
                  <div class="border-t border-slate-200 pt-2 text-xs text-slate-400">Total: {{ totalShare }}%</div>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ PROGRESS RINGS ═══════ -->
        <article id="progress-rings" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Gauge class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Progress Rings</h2>
          </div>
          <div class="p-4">
            <div class="grid grid-cols-4 gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div v-for="ring in progressRings" :key="ring.label" class="flex flex-col items-center gap-2">
                <div class="relative">
                  <svg viewBox="0 0 100 100" class="h-20 w-20 -rotate-90">
                    <circle cx="50" cy="50" r="40" fill="none" stroke="#e2e8f0" stroke-width="8" />
                    <circle
                      cx="50" cy="50" r="40" fill="none" stroke="currentColor"
                      :class="ring.color"
                      stroke-width="8"
                      stroke-linecap="round"
                      :stroke-dasharray="ringCircumference"
                      :stroke-dashoffset="ringCircumference - (ring.value / 100) * ringCircumference"
                    />
                  </svg>
                  <span class="absolute inset-0 flex items-center justify-center text-sm font-bold text-slate-900">{{ ring.value }}%</span>
                </div>
                <span class="text-xs font-medium text-slate-600">{{ ring.label }}</span>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ HEATMAP ═══════ -->
        <article id="heatmap" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Target class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Heatmap (Activity)</h2>
          </div>
          <div class="p-4">
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div class="space-y-1.5">
                <!-- Header row -->
                <div class="flex gap-1.5">
                  <div class="w-16 shrink-0" />
                  <div v-for="col in heatmapCols" :key="col" class="flex-1 text-center text-[11px] font-medium text-slate-500">{{ col }}</div>
                </div>
                <!-- Data rows -->
                <div v-for="(row, ri) in heatmapData" :key="heatmapRows[ri]" class="flex items-center gap-1.5">
                  <div class="w-16 shrink-0 text-right text-[11px] font-medium text-slate-500 pr-2">{{ heatmapRows[ri] }}</div>
                  <div
                    v-for="(cell, ci) in row"
                    :key="ci"
                    class="flex flex-1 items-center justify-center rounded-md py-3 text-xs font-semibold transition-all"
                    :style="{ backgroundColor: `rgba(124, 58, 237, ${heatmapOpacity(cell)})` }"
                    :class="cell > 5 ? 'text-white' : 'text-violet-700'"
                  >
                    {{ cell }}
                  </div>
                </div>
              </div>
              <div class="mt-3 flex items-center justify-end gap-1.5">
                <span class="text-[11px] text-slate-400">Low</span>
                <div class="flex gap-0.5">
                  <div v-for="i in 5" :key="i" class="h-3 w-6 rounded-sm" :style="{ backgroundColor: `rgba(124, 58, 237, ${0.15 + (i / 5) * 0.85})` }" />
                </div>
                <span class="text-[11px] text-slate-400">High</span>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ TIMELINE ═══════ -->
        <article id="timeline" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <GitBranch class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Timeline</h2>
          </div>
          <div class="p-4">
            <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <div class="relative space-y-6 border-l-2 border-violet-200 pl-6">
                <div class="relative">
                  <div class="absolute -left-[31px] flex h-4 w-4 items-center justify-center rounded-full bg-violet-600"><div class="h-1.5 w-1.5 rounded-full bg-white" /></div>
                  <p class="text-xs text-violet-600 font-semibold">Today</p>
                  <p class="text-sm text-slate-700">Deployed v2.4.0 to production</p>
                </div>
                <div class="relative">
                  <div class="absolute -left-[31px] flex h-4 w-4 items-center justify-center rounded-full bg-violet-400"><div class="h-1.5 w-1.5 rounded-full bg-white" /></div>
                  <p class="text-xs text-slate-500 font-medium">2 days ago</p>
                  <p class="text-sm text-slate-700">Merged feature branch: user-roles</p>
                </div>
                <div class="relative">
                  <div class="absolute -left-[31px] flex h-4 w-4 items-center justify-center rounded-full bg-violet-300"><div class="h-1.5 w-1.5 rounded-full bg-white" /></div>
                  <p class="text-xs text-slate-500 font-medium">5 days ago</p>
                  <p class="text-sm text-slate-700">Database migration completed</p>
                </div>
                <div class="relative">
                  <div class="absolute -left-[31px] flex h-4 w-4 items-center justify-center rounded-full bg-violet-200"><div class="h-1.5 w-1.5 rounded-full bg-violet-500" /></div>
                  <p class="text-xs text-slate-500 font-medium">1 week ago</p>
                  <p class="text-sm text-slate-700">Initial project scaffolding</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ DATA TABLE ═══════ -->
        <article id="data-table" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Table2 class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Sample Dataset Table</h2>
          </div>
          <div class="p-4">
            <div class="overflow-x-auto rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-slate-200">
                    <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Month</th>
                    <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Organic</th>
                    <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Social</th>
                    <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Referral</th>
                    <th class="pb-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Total</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in monthlyTraffic" :key="`row-${item.month}`" class="border-b border-slate-100 transition-colors hover:bg-white">
                    <td class="py-3 font-medium text-slate-900">{{ item.month }}</td>
                    <td class="py-3 font-mono text-slate-600">{{ item.organic.toLocaleString() }}</td>
                    <td class="py-3 font-mono text-slate-600">{{ item.social.toLocaleString() }}</td>
                    <td class="py-3 font-mono text-slate-600">{{ item.referral.toLocaleString() }}</td>
                    <td class="py-3 text-right font-mono font-medium text-violet-700">{{ (item.organic + item.social + item.referral).toLocaleString() }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </article>
      </section>

      <section class="grid gap-4 md:grid-cols-2">
        <!-- ═══════ USAGE NOTES ═══════ -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <BookOpen class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Usage Notes</h2>
          </div>
          <div class="space-y-4 p-4">
            <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
              <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
              <div class="text-sm text-emerald-900">
                <p class="mb-1 font-semibold">Do</p>
                <p>Use consistent scales and short time labels for quick scanning.</p>
              </div>
            </div>
            <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
              <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
              <div class="text-sm text-amber-900">
                <p class="mb-1 font-semibold">Don&apos;t</p>
                <p>Do not mix too many metrics in one chart without clear legends.</p>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ SAMPLING NOTES ═══════ -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Database class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Sampling Notes</h2>
          </div>
          <div class="p-4">
            <div class="space-y-2.5 rounded-lg border border-slate-200 bg-slate-50/50 p-5 text-sm text-slate-600">
              <div class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                <p>Dataset window: 7-day and 6-month samples.</p>
              </div>
              <div class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                <p>Traffic mix includes organic, social, and referral channels.</p>
              </div>
              <div class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                <p>Funnel values are sampled for demo, not real production telemetry.</p>
              </div>
              <div class="flex items-start gap-2">
                <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-violet-400" />
                <p>Recommended refresh cadence: daily aggregate updates.</p>
              </div>
            </div>
          </div>
        </article>
      </section>
    </div>
  </AdminLayout>
</template>
