<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import PettyCashApplicationListTable from "@/components/fims/PettyCashApplicationListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getPettyCashApplicationListOptions, listPettyCashApplications } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { PettyCashApplicationListRow } from "@/types";

const PAGE_NAME = "List of Petty Cash Application";
const PAGE_BREADCRUMB = "Petty Cash / List of Petty Cash Application";

const toast = useToast();

const rows = ref<PettyCashApplicationListRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(5);
const q = ref("");
const sortBy = ref("pms_application_no");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref({
  pms_request_by: "",
  pms_status: "",
});
const statusOptions = ref<{ id: string; label: string }[]>([]);

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getPettyCashApplicationListOptions();
    statusOptions.value = res.data.status ?? [];
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load filter options.");
  }
}

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
      ...(smartFilter.value.pms_request_by.trim() ? { pms_request_by: smartFilter.value.pms_request_by.trim() } : {}),
      ...(smartFilter.value.pms_status ? { pms_status: smartFilter.value.pms_status } : {}),
    });
    const res = await listPettyCashApplications(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load applications.");
  } finally {
    loading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) {
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  } else {
    sortBy.value = sortKey;
    sortDir.value = "asc";
  }
  page.value = 1;
  void loadRows();
}

function prevPage() {
  if (page.value > 1) {
    page.value -= 1;
    void loadRows();
  }
}

function nextPage() {
  if (page.value < totalPages.value) {
    page.value += 1;
    void loadRows();
  }
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { pms_request_by: "", pms_status: "" };
}

const {
  templateFileInputRef,
  isGrouped,
  handleSaveTemplate,
  handleLoadTemplate,
  onTemplateFileChange,
  handleUngroupList,
  handleGroupList,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: PAGE_NAME,
  apiDataPath: "/petty-cash/applications",
  defaultExportColumns: [
    "Application No",
    "Request By",
    "Application Date",
    "Total Amount",
    "Reject Amount",
    "Cancel Amount",
    "Paid Amount",
    "Status",
  ],
  getFilteredList: () => {
    const cfg = datatableRef.value?.getExportConfig?.();
    return (cfg?.data as Record<string, unknown>[]) ?? [];
  },
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => {
    void loadRows();
  },
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columns = cfg?.columns ?? [];
    const data = (cfg?.data as Record<string, unknown>[]) ?? [];

    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }

    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...columns]);
    data.forEach((row, idx) => {
      const values = columns.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Petty_Cash_Applications_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (!overflowOpen.value) return;
  if (!overflowRoot.value?.contains(event.target as Node)) overflowOpen.value = false;
}

let qSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  qSearchDebounce = setTimeout(() => {
    qSearchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadOptions();
  await loadRows();
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  document.removeEventListener("click", onClickOutside);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <nav class="text-base font-semibold text-slate-500" aria-label="Breadcrumb">{{ PAGE_BREADCRUMB }}</nav>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
          </div>
          <div ref="overflowRoot" class="relative">
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click.stop="overflowOpen = !overflowOpen">
              <MoreVertical class="h-4 w-4" />
            </button>
            <div
              v-if="overflowOpen"
              class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
              @click.stop
            >
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="overflowOpen = false; handleSaveTemplate()"
              >
                Save template
              </button>
              <button
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="overflowOpen = false; handleLoadTemplate()"
              >
                Load template
              </button>
              <button
                v-if="isGrouped"
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="overflowOpen = false; handleUngroupList()"
              >
                Ungroup list
              </button>
              <button
                v-else
                type="button"
                class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50"
                @click="overflowOpen = false; handleGroupList()"
              >
                Group list
              </button>
            </div>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm" @change="page = 1; loadRows()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm"
                  @keyup.enter="page = 1; void loadRows()"
                />
                <button
                  v-if="q"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  @click="q = ''; page = 1; loadRows()"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />
                Filter
              </button>
            </div>
          </div>

          <div v-if="loading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading…</div>
          <PettyCashApplicationListTable
            v-else
            ref="datatableRef"
            :rows="rows"
            :grouped="isGrouped"
            :sort-by="sortBy"
            :sort-dir="sortDir"
            @sort="onSort"
          />

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button
                type="button"
                :disabled="page <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button
                type="button"
                :disabled="page >= totalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />
                PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="handleDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />
                CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50"
                @click="exportExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />
                Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div
        v-if="showSmartFilter"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="showSmartFilter = false"
      >
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Request By</label>
              <input
                v-model="smartFilter.pms_request_by"
                type="search"
                placeholder="Filter by requester…"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.pms_status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in statusOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
