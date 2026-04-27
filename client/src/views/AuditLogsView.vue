<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { ScrollText, ChevronLeft, ChevronRight, ChevronDown, ChevronUp, Filter } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { listAuditLogs } from "@/api/cms";
import type { AuditLog } from "@/types";

const logs = ref<AuditLog[]>([]);
const loading = ref(false);
const page = ref(1);
const totalPages = ref(1);
const total = ref(0);
const limit = 20;

// Filters
const filterAction = ref("");
const filterType = ref("");
const filterDateFrom = ref("");
const filterDateTo = ref("");

// Expanded rows
const expandedId = ref<number | null>(null);

const actionOptions = ["created", "updated", "deleted", "login", "logout"];
const typeOptions = ["App\\Models\\Post", "App\\Models\\Page", "App\\Models\\Category", "App\\Models\\Media", "App\\Models\\User", "App\\Models\\Role", "App\\Models\\Setting"];

function typeLabel(fullType: string | null): string {
  if (!fullType) return "—";
  const parts = fullType.split("\\");
  return parts[parts.length - 1];
}

const actionColors: Record<string, string> = {
  created: "bg-emerald-100 text-emerald-700",
  updated: "bg-blue-100 text-blue-700",
  deleted: "bg-rose-100 text-rose-700",
  login: "bg-violet-100 text-violet-700",
  logout: "bg-slate-200 text-slate-600",
};

function buildParams(): string {
  const params = new URLSearchParams();
  params.set("page", String(page.value));
  params.set("limit", String(limit));
  if (filterAction.value) params.set("action", filterAction.value);
  if (filterType.value) params.set("auditable_type", filterType.value);
  if (filterDateFrom.value) params.set("date_from", filterDateFrom.value);
  if (filterDateTo.value) params.set("date_to", filterDateTo.value);
  return "?" + params.toString();
}

async function load() {
  loading.value = true;
  try {
    const res = await listAuditLogs(buildParams());
    logs.value = res.data;
    const meta = res.meta || {};
    totalPages.value = (meta.totalPages as number) || 1;
    total.value = (meta.total as number) || 0;
  } catch {
    logs.value = [];
  } finally {
    loading.value = false;
  }
}

function applyFilters() {
  page.value = 1;
  load();
}

function clearFilters() {
  filterAction.value = "";
  filterType.value = "";
  filterDateFrom.value = "";
  filterDateTo.value = "";
  page.value = 1;
  load();
}

function prevPage() {
  if (page.value > 1) {
    page.value--;
    load();
  }
}

function nextPage() {
  if (page.value < totalPages.value) {
    page.value++;
    load();
  }
}

function toggleExpand(id: number) {
  expandedId.value = expandedId.value === id ? null : id;
}

function formatDate(dateStr: string): string {
  const d = new Date(dateStr);
  return d.toLocaleString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function formatJson(obj: Record<string, unknown> | null): string {
  if (!obj || Object.keys(obj).length === 0) return "—";
  return JSON.stringify(obj, null, 2);
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Audit Trail</h1>
        <span class="text-sm text-slate-400">{{ total }} total entries</span>
      </div>

      <!-- ───── Filters Card ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Filter class="h-4 w-4 text-slate-500" />
          <h2 class="text-sm font-semibold text-slate-900">Filters</h2>
        </div>
        <div class="p-4">
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-500">Action</label>
              <select v-model="filterAction" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All actions</option>
                <option v-for="a in actionOptions" :key="a" :value="a">{{ a }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-500">Model Type</label>
              <select v-model="filterType" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                <option value="">All types</option>
                <option v-for="t in typeOptions" :key="t" :value="t">{{ typeLabel(t) }}</option>
              </select>
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-500">Date From</label>
              <input v-model="filterDateFrom" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-medium text-slate-500">Date To</label>
              <input v-model="filterDateTo" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
            </div>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <button class="rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="applyFilters">Apply</button>
            <button class="rounded-lg border border-slate-300 px-4 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50" @click="clearFilters">Clear</button>
          </div>
        </div>
      </article>

      <!-- ───── Logs List ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <ScrollText class="h-4 w-4 text-amber-600" />
          <h2 class="text-sm font-semibold text-slate-900">Activity Log</h2>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="px-4 py-8 text-center text-sm text-slate-400">Loading...</div>

        <!-- Empty -->
        <div v-else-if="logs.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">No audit logs found.</div>

        <!-- Rows -->
        <div v-else class="divide-y divide-slate-100">
          <div v-for="log in logs" :key="log.id">
            <div
              class="flex cursor-pointer items-center gap-3 px-4 py-2.5 transition-colors hover:bg-slate-50"
              @click="toggleExpand(log.id)"
            >
              <!-- Expand icon -->
              <component :is="expandedId === log.id ? ChevronUp : ChevronDown" class="h-3.5 w-3.5 shrink-0 text-slate-400" />

              <!-- Action badge -->
              <span
                class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="actionColors[log.action] || 'bg-slate-100 text-slate-600'"
              >{{ log.action }}</span>

              <!-- Model type -->
              <span class="shrink-0 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-mono text-slate-500">
                {{ typeLabel(log.auditableType) }}
                <template v-if="log.auditableId">#{{ log.auditableId }}</template>
              </span>

              <!-- User -->
              <span class="min-w-0 truncate text-sm text-slate-700">
                {{ log.user?.name || "System" }}
              </span>

              <!-- Spacer -->
              <span class="flex-1"></span>

              <!-- IP -->
              <span v-if="log.ipAddress" class="hidden shrink-0 text-xs text-slate-400 sm:inline">{{ log.ipAddress }}</span>

              <!-- Timestamp -->
              <span class="shrink-0 text-xs text-slate-400">{{ formatDate(log.createdAt) }}</span>
            </div>

            <!-- Expanded detail -->
            <div v-if="expandedId === log.id" class="border-t border-slate-100 bg-slate-50 px-4 py-3">
              <div class="grid gap-4 lg:grid-cols-2">
                <div>
                  <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Old Values</p>
                  <pre class="max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">{{ formatJson(log.oldValues) }}</pre>
                </div>
                <div>
                  <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">New Values</p>
                  <pre class="max-h-48 overflow-auto rounded-lg border border-slate-200 bg-white p-3 text-xs text-slate-600">{{ formatJson(log.newValues) }}</pre>
                </div>
              </div>
              <div class="mt-2 flex gap-4 text-xs text-slate-400">
                <span>IP: {{ log.ipAddress || "—" }}</span>
                <span class="truncate">UA: {{ log.userAgent || "—" }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-slate-100 px-4 py-2.5">
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="page <= 1"
            @click="prevPage"
          >
            <ChevronLeft class="h-3.5 w-3.5" />
            Previous
          </button>
          <span class="text-sm text-slate-500">Page {{ page }} of {{ totalPages }}</span>
          <button
            class="flex items-center gap-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 disabled:opacity-40"
            :disabled="page >= totalPages"
            @click="nextPage"
          >
            Next
            <ChevronRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
