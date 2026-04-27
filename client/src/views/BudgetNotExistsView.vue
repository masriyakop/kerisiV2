<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listBudgetNotExists } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { BudgetNotExistsRow } from "@/types";

// Setup and Maintenance > Report > Budget Not Exists (PAGEID 2200 / MENUID 2657).
// Legacy BL: NAD_API_SM_REPORT_BUDGET_NOT_EXIST — read-only datatable listing
// approved postings whose `pde_document_no` has not yet been registered in
// `budget_transaction.bgt_ref` and whose account is budget-flagged.
const PAGE_NAME = "Budget Not Exists";
const PAGE_BREADCRUMB = "Setup and Maintenance / Report / Budget Not Exists";

const toast = useToast();

const rows = ref<BudgetNotExistsRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref("pde_trans_date");
const sortDir = ref<"asc" | "desc">("desc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const columns: FimsColumn<BudgetNotExistsRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "ftyFundType", label: "Fund Type", sortable: true, sortKey: "fty_fund_type", hideable: true, value: (r) => r.ftyFundType ?? "" },
  { key: "atActivityCode", label: "Activity Code", sortable: true, sortKey: "at_activity_code", hideable: true, value: (r) => r.atActivityCode ?? "" },
  { key: "ounCode", label: "PTJ Code", sortable: true, sortKey: "oun_code", hideable: true, value: (r) => r.ounCode ?? "" },
  { key: "ccrCostcentre", label: "Cost Centre", sortable: true, sortKey: "ccr_costcentre", hideable: true, value: (r) => r.ccrCostcentre ?? "" },
  { key: "acmAcctCode", label: "Account Code", sortable: true, sortKey: "acm_acct_code", hideable: true, value: (r) => r.acmAcctCode ?? "" },
  { key: "cpaProjectNo", label: "Project No", sortable: true, sortKey: "cpa_project_no", hideable: true, value: (r) => r.cpaProjectNo ?? "" },
  { key: "pdeDocumentNo", label: "Document No", sortable: true, sortKey: "pde_document_no", hideable: true, value: (r) => r.pdeDocumentNo ?? "" },
  { key: "pdeReference", label: "Reference", sortable: true, sortKey: "pde_reference", hideable: true, value: (r) => r.pdeReference ?? "" },
  { key: "pdeReference1", label: "Reference 1", hideable: true, value: (r) => r.pdeReference1 ?? "" },
  { key: "pdeTransType", label: "Trans Type", hideable: true, value: (r) => r.pdeTransType ?? "" },
  { key: "pdeTransAmt", label: "Amount", sortable: true, sortKey: "pde_trans_amt", hideable: true, align: "right", value: (r) => (r.pdeTransAmt != null ? Number(r.pdeTransAmt).toFixed(2) : "") },
  { key: "pdeTransDate", label: "Date", sortable: true, sortKey: "pde_trans_date", hideable: true, value: (r) => r.pdeTransDate ?? "" },
  { key: "pdePaytoName", label: "Payee", hideable: true, value: (r) => r.pdePaytoName ?? "" },
  { key: "pdeStatus", label: "PDE Status", hideable: true, value: (r) => r.pdeStatus ?? "" },
  { key: "pmtSystemId", label: "System Id", hideable: true, value: (r) => r.pmtSystemId ?? "" },
  { key: "pmtPostingDesc", label: "Posting Desc", hideable: true, value: (r) => r.pmtPostingDesc ?? "" },
  { key: "pmtStatus", label: "Posting Status", hideable: true, value: (r) => r.pmtStatus ?? "" },
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
    const res = await listBudgetNotExists(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load budget-not-exists report.");
  } finally {
    loading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = sortKey; sortDir.value = "desc"; }
  page.value = 1;
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

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
  apiDataPath: "/general-ledger/budget-not-exists",
  defaultExportColumns: [
    "Fund Type", "Activity Code", "PTJ Code", "Cost Centre", "Account Code",
    "Project No", "Document No", "Reference", "Reference 1", "Trans Type",
    "Amount", "Date", "Payee", "PDE Status", "System Id", "Posting Desc", "Posting Status",
  ],
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
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>

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
            :row-key="(r) => `${r.pmtPostingId}-${r.pdeDocumentNo ?? ''}`"
            :group-by="(r) => `Fund ${r.ftyFundType ?? '(none)'}`"
            min-width="2000px"
            @sort="onSort"
          />

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" /> PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" /> CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" /> Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
