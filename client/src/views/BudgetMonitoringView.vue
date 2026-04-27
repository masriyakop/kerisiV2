<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { getBudgetMonitoringOptions, listBudgetMonitoring } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type {
  BudgetLookupOption,
  BudgetMonitoringFooter,
  BudgetMonitoringOptions,
  BudgetMonitoringRow,
  BudgetPtjOption,
} from "@/types";

// Page labels mirror legacy PAGETITLE / PAGEBREADCRUMBS for PAGEID 1201
// (docs/migration/fims-budget/PAGE_1201.json).
const PAGE_NAME = "Budget Monitoring";
const PAGE_BREADCRUMB = "Budget / Monitoring";

const toast = useToast();
const rows = ref<BudgetMonitoringRow[]>([]);
const footer = ref<BudgetMonitoringFooter | null>(null);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);

// Top filter reflects the legacy "Monitoring Filter" form (COMPONENTID 4296).
const topFilter = ref({
  topYear: "",
  topFund: "",
  topPtjLevel: "",
  topPtj: "",
  topCostCentre: "",
  topActivityCode: "",
});

// Smart filter modal reflects the legacy "Smart Filter" form (COMPONENTID 3290).
const showSmartFilter = ref(false);
const smartFilter = ref({
  smBudgetId: "",
  smStatus: "",
  smAcmAcctCode: "",
  smKodSo: "",
  smBudgetCode: "",
});

const options = ref<BudgetMonitoringOptions>({
  topFilter: { year: [], fund: [], ptjLevel: [], ptj: [], costCentre: [] },
  smartFilter: { status: [] },
});

const ptjOptionsFiltered = computed<BudgetPtjOption[]>(() => {
  if (!topFilter.value.topPtjLevel) return options.value.topFilter.ptj;
  return options.value.topFilter.ptj.filter((p) => String(p.level ?? "") === topFilter.value.topPtjLevel);
});

async function loadOptions() {
  try {
    const res = await getBudgetMonitoringOptions();
    options.value = res.data;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load filter options.");
  }
}

async function loadRows() {
  try {
    const qp: Record<string, string> = {
      page: String(page.value),
      limit: String(limit.value),
    };
    if (q.value) qp.q = q.value;
    Object.entries(topFilter.value).forEach(([k, v]) => {
      if (v) qp[k] = String(v);
    });
    Object.entries(smartFilter.value).forEach(([k, v]) => {
      if (v) qp[k] = String(v);
    });
    const res = await listBudgetMonitoring(`?${new URLSearchParams(qp).toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    footer.value = (res.meta?.footer as BudgetMonitoringFooter | undefined) ?? null;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load rows.");
  }
}

const currency = new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function formatAmount(v: unknown): string {
  if (v === null || v === undefined || v === "") return "";
  const n = typeof v === "number" ? v : Number(String(v).replace(/,/g, ""));
  if (!Number.isFinite(n)) return String(v);
  return currency.format(n);
}

// Action column: the legacy dt_js renders a "View Budget" link that routes to
// menuID 1831 (Budget detail screen). That page is not part of this migration
// batch, so the button is rendered but emits a "not migrated" toast.
function notMigrated() {
  toast.info(
    "Not migrated yet",
    "The Budget detail screen (legacy menuID 1831) is not part of this migration batch.",
  );
}

// Export column layout — all visible numeric / text columns must be present
// per .cursor/rules/datatable-exports.mdc so exports match the rendered table.
const exportColumns = [
  "Structure Budget",
  "Budget Code Desc",
  "Activity Desc",
  "Opening",
  "Allocation Receive",
  "Initial",
  "Increment/Decrement",
  "Virement",
  "Allocated",
  "Lock",
  "Pre Request",
  "Request",
  "Commit",
  "Expenses",
  "Balance",
  "Status",
  "Fund Type",
  "Fund Desc",
  "Activity Code",
  "PTJ",
  "PTJ Desc",
  "Cost Centre",
  "Cost Centre Desc",
  "Budget Code",
  "Budget Closing",
  "Budget Closing By",
];

function toExportRow(r: BudgetMonitoringRow): Record<string, string | number> {
  return {
    "Structure Budget": r.budgetid ?? "",
    "Budget Code Desc": r.acmAcctDesc ?? "",
    "Activity Desc": r.atActivityDesc ?? "",
    Opening: formatAmount(r.bdgBalCarryforward),
    "Allocation Receive": formatAmount(r.bdgTopupAmt),
    Initial: formatAmount(r.bdgInitialAmt),
    "Increment/Decrement": formatAmount(r.bdgAdditionalAmt),
    Virement: formatAmount(r.bdgVirementAmt),
    Allocated: formatAmount(r.bdgAllocatedAmt),
    Lock: formatAmount(r.bdgLockAmt),
    "Pre Request": formatAmount(r.bdgPreRequestAmt),
    Request: formatAmount(r.bdgRequestAmt),
    Commit: formatAmount(r.bdgCommitAmt),
    Expenses: formatAmount(r.bdgExpensesAmt),
    Balance: formatAmount(r.bdgBalanceAmt),
    Status: r.bdgStatus ?? "",
    "Fund Type": r.ftyFundType ?? "",
    "Fund Desc": r.ftyFundDesc ?? "",
    "Activity Code": r.atActivityCode ?? "",
    PTJ: r.ounCode ?? "",
    "PTJ Desc": r.ounDesc ?? "",
    "Cost Centre": r.ccrCostcentre ?? "",
    "Cost Centre Desc": r.ccrCostcentreDesc ?? "",
    "Budget Code": r.lbcBudgetCode ?? "",
    "Budget Closing": r.bdgClosing ?? "",
    "Budget Closing By": r.bdgClosingBy ?? "",
  };
}

const datatableRef = ref<DatatableRefApi | null>(null);
const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: PAGE_NAME,
  apiDataPath: "/budget/monitoring",
  defaultExportColumns: exportColumns,
  getFilteredList: () => rows.value.map(toExportRow),
  datatableRef,
  searchKeyword: q,
  smartFilter: smartFilter as unknown as import("vue").Ref<Record<string, unknown>>,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      const row = toExportRow(r);
      ws.addRow([idx + 1, ...exportColumns.map((c) => row[c] ?? "")]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
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

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

function applyTopFilter() {
  page.value = 1;
  void loadRows();
}

function resetTopFilter() {
  topFilter.value = {
    topYear: "",
    topFund: "",
    topPtjLevel: "",
    topPtj: "",
    topCostCentre: "",
    topActivityCode: "",
  };
  page.value = 1;
  void loadRows();
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = {
    smBudgetId: "",
    smStatus: "",
    smAcmAcctCode: "",
    smKodSo: "",
    smBudgetCode: "",
  };
}

onMounted(async () => {
  await loadOptions();
  await loadRows();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / Math.max(1, limit.value))));

// Helper for the PTJ filter: keep the current selection if it no longer
// matches the chosen level, reset it to "any".
watch(
  () => topFilter.value.topPtjLevel,
  () => {
    if (!topFilter.value.topPtj) return;
    const found = ptjOptionsFiltered.value.some((p: BudgetLookupOption) => p.id === topFilter.value.topPtj);
    if (!found) topFilter.value.topPtj = "";
  },
);
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>

      <!-- Top filter (legacy "Monitoring Filter" form) -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-base font-semibold text-slate-900">Monitoring Filter</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-3 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Year <span class="text-red-500">*</span></label>
            <select
              v-model="topFilter.topYear"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Select year</option>
              <option v-for="opt in options.topFilter.year" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Fund</label>
            <select
              v-model="topFilter.topFund"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Any</option>
              <option v-for="opt in options.topFilter.fund" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">PTJ Level</label>
            <select
              v-model="topFilter.topPtjLevel"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Any</option>
              <option v-for="opt in options.topFilter.ptjLevel" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">PTJ <span class="text-red-500">*</span></label>
            <select
              v-model="topFilter.topPtj"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Any</option>
              <option v-for="opt in ptjOptionsFiltered" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Cost Center</label>
            <select
              v-model="topFilter.topCostCentre"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Any</option>
              <option v-for="opt in options.topFilter.costCentre" :key="opt.id" :value="opt.id">
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Activity Code</label>
            <input
              v-model="topFilter.topActivityCode"
              type="text"
              placeholder="e.g. 10001"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
          <button
            type="button"
            class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
            @click="resetTopFilter"
          >
            Reset
          </button>
          <button
            type="button"
            class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
            @click="applyTopFilter"
          >
            Apply
          </button>
        </div>
      </article>

      <!-- Datatable -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Monitoring</h1>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            aria-label="More options"
          >
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="
                  page = 1;
                  loadRows();
                "
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="
                    page = 1;
                    void loadRows();
                  "
                />
                <button
                  v-if="q"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="q = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[520px] overflow-y-auto' : ''">
              <table class="w-full min-w-[2400px] text-xs">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-2 py-2 font-semibold uppercase">No</th>
                    <th class="px-2 py-2 font-semibold uppercase">Structure Budget</th>
                    <th class="px-2 py-2 font-semibold uppercase">Budget Code Desc</th>
                    <th class="px-2 py-2 font-semibold uppercase">Activity Desc</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Opening</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Allocation Receive</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Initial</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Inc/Dec</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Virement</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Allocated</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Lock</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Pre Request</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Request</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Commit</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Expenses</th>
                    <th class="px-2 py-2 text-right font-semibold uppercase">Balance</th>
                    <th class="px-2 py-2 font-semibold uppercase">Status</th>
                    <th class="px-2 py-2 font-semibold uppercase">Fund Type</th>
                    <th class="px-2 py-2 font-semibold uppercase">Fund Desc</th>
                    <th class="px-2 py-2 font-semibold uppercase">Activity Code</th>
                    <th class="px-2 py-2 font-semibold uppercase">PTJ</th>
                    <th class="px-2 py-2 font-semibold uppercase">PTJ Desc</th>
                    <th class="px-2 py-2 font-semibold uppercase">Cost Centre</th>
                    <th class="px-2 py-2 font-semibold uppercase">Cost Centre Desc</th>
                    <th class="px-2 py-2 font-semibold uppercase">Budget Code</th>
                    <th class="px-2 py-2 font-semibold uppercase">Budget Closing</th>
                    <th class="px-2 py-2 font-semibold uppercase">Budget Closing By</th>
                    <th class="px-2 py-2 font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in rows"
                    :key="`${row.budgetid}-${row.bdgYear}-${row.bdgStatus}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-2 py-1.5">{{ row.index }}</td>
                    <td class="px-2 py-1.5 font-mono">{{ row.budgetid ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.acmAcctDesc ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.atActivityDesc ?? "" }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgBalCarryforward) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgTopupAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgInitialAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgAdditionalAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgVirementAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgAllocatedAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgLockAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgPreRequestAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgRequestAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgCommitAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgExpensesAmt) }}</td>
                    <td class="px-2 py-1.5 text-right">{{ formatAmount(row.bdgBalanceAmt) }}</td>
                    <td class="px-2 py-1.5">{{ row.bdgStatus ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ftyFundType ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ftyFundDesc ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.atActivityCode ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ounCode ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ounDesc ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ccrCostcentre ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.ccrCostcentreDesc ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.lbcBudgetCode ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.bdgClosing ?? "" }}</td>
                    <td class="px-2 py-1.5">{{ row.bdgClosingBy ?? "" }}</td>
                    <td class="px-2 py-1.5">
                      <button
                        type="button"
                        class="rounded p-1 text-slate-500 hover:bg-slate-100"
                        title="View Budget"
                        @click="notMigrated"
                      >
                        <Eye class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                  <tr v-if="rows.length === 0">
                    <td class="px-3 py-6 text-center text-sm text-slate-400" colspan="28">No records.</td>
                  </tr>
                </tbody>
                <tfoot v-if="footer" class="bg-slate-50 font-semibold">
                  <tr>
                    <td class="px-2 py-2" colspan="4">Grand Total</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgBalCarryforward) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgTopupAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgInitialAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgAdditionalAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgVirementAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgAllocatedAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgLockAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgPreRequestAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgRequestAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgCommitAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgExpensesAmt) }}</td>
                    <td class="px-2 py-2 text-right">{{ formatAmount(footer.bdgBalanceAmt) }}</td>
                    <td class="px-2 py-2" colspan="12"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">
              Page {{ page }} of {{ totalPages }} &middot; {{ total }} record(s)
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page <= 1"
                @click="
                  page = Math.max(1, page - 1);
                  void loadRows();
                "
              >
                Previous
              </button>
              <button
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="page >= totalPages"
                @click="
                  page = Math.min(totalPages, page + 1);
                  void loadRows();
                "
              >
                Next
              </button>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="handleDownloadPDF"
            >
              <Download class="h-3.5 w-3.5" />PDF
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="handleDownloadCSV"
            >
              <FileDown class="h-3.5 w-3.5" />CSV
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
              @click="exportExcel"
            >
              <FileSpreadsheet class="h-3.5 w-3.5" />Excel
            </button>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Budget ID</label>
              <input
                v-model="smartFilter.smBudgetId"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.smStatus"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account</label>
              <input
                v-model="smartFilter.smAcmAcctCode"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Kod SO</label>
              <input
                v-model="smartFilter.smKodSo"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Budget Code</label>
              <input
                v-model="smartFilter.smBudgetCode"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              @click="resetSmartFilter"
            >
              Reset
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
              @click="applySmartFilter"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
