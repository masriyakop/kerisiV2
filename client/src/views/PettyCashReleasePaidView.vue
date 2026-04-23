<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { ArrowDownToLine, Download, Eye, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getPettyCashApplication, listPettyCashReleasePaidApplications, listPettyCashReleasePaidReceipts } from "@/api/cms";
import { downloadRequestPettyCashPdf } from "@/composables/usePettyCashFormPdf";
import { useToast } from "@/composables/useToast";
import type { PettyCashReleasePaidApplicationRow, PettyCashReleasePaidReceiptRow } from "@/types";

const PAGE_NAME = "List of Release Paid";
const PAGE_BREADCRUMB = "Petty Cash / List of Release Paid";

const toast = useToast();

const rows = ref<PettyCashReleasePaidApplicationRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(5);
const q = ref("");
const sortDir = ref<"asc" | "desc">("desc");
const sortBy = ref("pcd_paid_date");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const receiptDrawerOpen = ref(false);
const receiptLoading = ref(false);
const receiptRows = ref<PettyCashReleasePaidReceiptRow[]>([]);
const receiptPmsId = ref<number | null>(null);
const receiptApplicationNo = ref<string>("");

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function formatMoney(v: number | null | undefined): string {
  if (v == null || Number.isNaN(Number(v))) return "-";
  return new Intl.NumberFormat("en-MY", { style: "currency", currency: "MYR", minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v));
}

const columns: FimsColumn<PettyCashReleasePaidApplicationRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "pmsApplicationNo", label: "Application No", hideable: true, value: (r) => r.pmsApplicationNo ?? "" },
  { key: "pmsRequestBy", label: "Request By", hideable: true, value: (r) => r.pmsRequestBy ?? "" },
  { key: "pmsRequestDate", label: "Request Date", hideable: true, value: (r) => r.pmsRequestDate ?? "" },
  { key: "pmsPayToId", label: "Pay To", hideable: true, value: (r) => r.pmsPayToId ?? "" },
  { key: "pcdPaidDate", label: "Paid Date", hideable: true, value: (r) => r.pcdPaidDate ?? "" },
  { key: "pmsTotalAmt", label: "Total Amount", hideable: true, align: "right", value: (r) => formatMoney(r.pmsTotalAmt) },
  { key: "pmsReturnAmt", label: "Return Amount", hideable: true, align: "right", value: (r) => formatMoney(r.pmsReturnAmt) },
  { key: "pmsStatus", label: "Status", hideable: true, value: (r) => r.pmsStatus ?? "" },
  { key: "action", label: "Action" },
];

const receiptColumns: FimsColumn<PettyCashReleasePaidReceiptRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "pcdReceiptNo", label: "Receipt No", hideable: true, value: (r) => r.pcdReceiptNo ?? "" },
  { key: "pcdPaidDate", label: "Paid Date", hideable: true, value: (r) => r.pcdPaidDate ?? "" },
  { key: "pmsRequestBy", label: "Request By", hideable: true, value: (r) => r.pmsRequestBy ?? "" },
  { key: "pmsPayToId", label: "Pay To", hideable: true, value: (r) => r.pmsPayToId ?? "" },
  { key: "pcdTransAmt", label: "Amount", hideable: true, align: "right", value: (r) => formatMoney(r.pcdTransAmt) },
  { key: "pmsStatus", label: "Status", hideable: true, value: (r) => r.pmsStatus ?? "" },
];

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await listPettyCashReleasePaidApplications(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  } finally {
    loading.value = false;
  }
}

const downloadingId = ref<number | null>(null);

async function onDownloadRequestPdf(row: PettyCashReleasePaidApplicationRow) {
  if (downloadingId.value !== null) return;
  downloadingId.value = row.pmsId;
  try {
    const res = await getPettyCashApplication(row.pmsId);
    if (res.data) {
      await downloadRequestPettyCashPdf(res.data);
      toast.success("PDF downloaded");
    } else {
      toast.error("Download failed", "Could not load application data.");
    }
  } catch (e) {
    toast.error("Download failed", e instanceof Error ? e.message : "An error occurred while generating the PDF.");
  } finally {
    downloadingId.value = null;
  }
}

async function openReceipts(row: PettyCashReleasePaidApplicationRow) {
  receiptPmsId.value = row.pmsId;
  receiptApplicationNo.value = row.pmsApplicationNo ?? "";
  receiptDrawerOpen.value = true;
  receiptRows.value = [];
  receiptLoading.value = true;
  try {
    const params = new URLSearchParams({ pms_id: String(row.pmsId), limit: "100" });
    const res = await listPettyCashReleasePaidReceipts(`?${params.toString()}`);
    receiptRows.value = res.data;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load receipts.");
  } finally {
    receiptLoading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
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
  apiDataPath: "/petty-cash/release-paid/applications",
  defaultExportColumns: ["Application No", "Request By", "Request Date", "Pay To", "Paid Date", "Total Amount", "Return Amount", "Status"],
  getFilteredList: () => (datatableRef.value?.getExportConfig?.()?.data as Record<string, unknown>[]) ?? [],
  datatableRef,
  searchKeyword: q,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columnsOut = cfg?.columns ?? [];
    const data = (cfg?.data as Record<string, unknown>[]) ?? [];
    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...columnsOut]);
    data.forEach((row, idx) => {
      const values = columnsOut.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${PAGE_NAME.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
          <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
          <div ref="overflowRoot" class="relative">
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click.stop="overflowOpen = !overflowOpen">
              <MoreVertical class="h-4 w-4" />
            </button>
            <div v-if="overflowOpen" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" @click.stop>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleSaveTemplate()">Save template</button>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleLoadTemplate()">Load template</button>
              <button v-if="isGrouped" type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleUngroupList()">Ungroup list</button>
              <button v-else type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleGroupList()">Group list</button>
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
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; loadRows()">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>
          <div v-if="loading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading&hellip;</div>
          <FimsListTable
            v-else
            ref="datatableRef"
            :rows="rows"
            :columns="columns"
            :grouped="isGrouped"
            :sort-by="sortBy"
            :sort-dir="sortDir"
            :row-key="(r) => `${r.pmsId}-${r.pcmId}`"
            :group-by="(r) => `Paid ${r.pcdPaidDate ?? ''}`"
            min-width="1200px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <div class="flex items-center gap-1">
                <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="View receipts" @click="openReceipts(row as PettyCashReleasePaidApplicationRow)">
                  <Eye class="h-3.5 w-3.5" />
                </button>
                <button
                  type="button"
                  class="rounded p-1 text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                  title="Download Request Petty Cash PDF"
                  :disabled="downloadingId === (row as PettyCashReleasePaidApplicationRow).pmsId"
                  @click="onDownloadRequestPdf(row as PettyCashReleasePaidApplicationRow)"
                >
                  <ArrowDownToLine class="h-3.5 w-3.5" />
                </button>
              </div>
            </template>
          </FimsListTable>
          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="receiptDrawerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="receiptDrawerOpen = false">
        <div class="w-full max-w-4xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div>
              <h3 class="text-base font-semibold text-slate-900">Receipts — {{ receiptApplicationNo || `pms_id ${receiptPmsId}` }}</h3>
              <p class="text-xs text-slate-500">Paid receipts linked to this application.</p>
            </div>
            <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="receiptDrawerOpen = false"><X class="h-4 w-4" /></button>
          </div>
          <div class="p-4">
            <div v-if="receiptLoading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading&hellip;</div>
            <FimsListTable
              v-else
              :rows="receiptRows"
              :columns="receiptColumns"
              :grouped="false"
              sort-by=""
              sort-dir="desc"
              :row-key="(r) => r.pcdReceiptNo ?? Math.random()"
              min-width="900px"
            />
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
