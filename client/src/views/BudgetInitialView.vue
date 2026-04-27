<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Ban,
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  FileText,
  Filter,
  MoreVertical,
  Pencil,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { getBudgetInitialOptions, listBudgetInitial } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type {
  BudgetInitialOptions,
  BudgetInitialQuarterOption,
  BudgetInitialRow,
} from "@/types";

// Labels + columns mirror the legacy page (BUDGET.json, PAGEID 1264
// / MENUID 1541, COMPONENTIDs 3488 datatable, 3489 warrant filter,
// 4119 cancel-approval form, 6686 smart filter).
const PAGE_NAME = "Budget Initial V2";
const PAGE_BREADCRUMB = "Budget / Initial";

const toast = useToast();
const rows = ref<BudgetInitialRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);

// Warrant Filter (COMPONENTID 3489). The "Generate" action (legacy
// `generateWaran`) is not part of this migration batch, but the values
// do narrow the list so the user sees what they are about to warrant.
const topFilter = ref({
  reference: "",
  year: "",
  quarter: "",
  groupBy: "all" as "all" | "ptj",
  ptj: "",
});

// Smart filter modal (COMPONENTID 6686) — Year, Quarter, Status.
const showSmartFilter = ref(false);
const smartFilter = ref({
  smYear: "",
  smQuarter: "",
  smStatus: "",
});
const options = ref<BudgetInitialOptions>({
  smartFilter: { year: [], quarter: [], status: [] },
});

// Cancel-approval modal (COMPONENTID 4119). Kept UI-only for now.
const showCancelModal = ref(false);
const cancelRow = ref<BudgetInitialRow | null>(null);
const cancelRemark = ref("");

async function loadOptions() {
  try {
    const res = await getBudgetInitialOptions();
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
    Object.entries(smartFilter.value).forEach(([k, v]) => {
      if (v) qp[k] = String(v);
    });
    if (topFilter.value.reference) qp.reference = topFilter.value.reference;
    if (topFilter.value.year) qp.year = topFilter.value.year;
    if (topFilter.value.quarter) qp.quarter = topFilter.value.quarter;
    const res = await listBudgetInitial(`?${new URLSearchParams(qp).toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
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
function formatDate(v: unknown): string {
  if (!v) return "";
  const d = new Date(String(v));
  if (Number.isNaN(d.getTime())) return "";
  return `${String(d.getDate()).padStart(2, "0")}/${String(d.getMonth() + 1).padStart(2, "0")}/${d.getFullYear()}`;
}

// Legacy dt_js: Edit / Delete only when STAT === 'DRAFT', Cancel only
// when STAT === 'APPROVE' (legacy uses 'APPROVE', not 'APPROVED').
function canEdit(row: BudgetInitialRow): boolean {
  return row.stat === "DRAFT";
}
function canDelete(row: BudgetInitialRow): boolean {
  return row.stat === "DRAFT";
}
function canCancel(row: BudgetInitialRow): boolean {
  return row.stat === "APPROVE";
}

function notMigrated(kind = "editor"): void {
  toast.info(
    "Not migrated yet",
    `The Budget Initial V2 ${kind} is not part of this migration batch.`,
  );
}

function openCancelModal(row: BudgetInitialRow): void {
  cancelRow.value = row;
  cancelRemark.value = "";
  showCancelModal.value = true;
}
function submitCancel(): void {
  if (!cancelRemark.value.trim()) {
    toast.error("Remarks required", "Please provide a reason for cancelling.");
    return;
  }
  showCancelModal.value = false;
  notMigrated("cancel action");
}

// Quarter options are filtered against the currently-selected year so
// the Warrant Filter / Smart Filter quarter dropdowns stay coherent.
const warrantQuarterOptions = computed<BudgetInitialQuarterOption[]>(() => {
  if (!topFilter.value.year) return options.value.smartFilter.quarter;
  return options.value.smartFilter.quarter.filter((q) => !q.year || q.year === topFilter.value.year);
});
const smartQuarterOptions = computed<BudgetInitialQuarterOption[]>(() => {
  if (!smartFilter.value.smYear) return options.value.smartFilter.quarter;
  return options.value.smartFilter.quarter.filter((q) => !q.year || q.year === smartFilter.value.smYear);
});

// Legacy header explicitly uses the English labels below (dt_bi).
const exportColumns = [
  "Year",
  "Quarter",
  "Reference No",
  "Authority Approval",
  "Amount",
  "Status",
  "Date",
];

function toExportRow(r: BudgetInitialRow): Record<string, string | number> {
  return {
    Year: r.years ?? "",
    Quarter: r.quarter ?? "",
    "Reference No": r.allocateNo ?? "",
    "Authority Approval": r.endorse ?? "",
    Amount: formatAmount(r.amt),
    Status: r.stat ?? "",
    Date: formatDate(r.date),
  };
}

const datatableRef = ref<DatatableRefApi | null>(null);
const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: PAGE_NAME,
  apiDataPath: "/budget/initial",
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
  topFilter.value = { reference: "", year: "", quarter: "", groupBy: "all", ptj: "" };
  page.value = 1;
  void loadRows();
}
function generateWaran() {
  // Legacy form posts to a generate endpoint we have not migrated.
  notMigrated("Generate Warrant flow");
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}
function resetSmartFilter() {
  smartFilter.value = { smYear: "", smQuarter: "", smStatus: "" };
}

// Reset dependent quarter selections if the parent year changes.
watch(
  () => topFilter.value.year,
  (yr) => {
    if (yr && topFilter.value.quarter) {
      const still = warrantQuarterOptions.value.some((q) => q.id === topFilter.value.quarter);
      if (!still) topFilter.value.quarter = "";
    }
  },
);
watch(
  () => smartFilter.value.smYear,
  (yr) => {
    if (yr && smartFilter.value.smQuarter) {
      const still = smartQuarterOptions.value.some((q) => q.id === smartFilter.value.smQuarter);
      if (!still) smartFilter.value.smQuarter = "";
    }
  },
);

onMounted(async () => {
  await loadOptions();
  await loadRows();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});

const totalPages = computed(() => Math.max(1, Math.ceil(total.value / Math.max(1, limit.value))));
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

      <!-- Warrant Filter form (legacy COMPONENTID 3489). -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-base font-semibold text-slate-900">Warrant Filter</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 lg:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Reference</label>
            <input
              v-model="topFilter.reference"
              type="text"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Year <span class="text-red-500">*</span></label>
            <select
              v-model="topFilter.year"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Select year</option>
              <option v-for="opt in options.smartFilter.year" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Quarter <span class="text-red-500">*</span></label>
            <select
              v-model="topFilter.quarter"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Select quarter</option>
              <option v-for="opt in warrantQuarterOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">PTJ</label>
            <input
              v-model="topFilter.ptj"
              type="text"
              placeholder="Organization unit"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              :disabled="topFilter.groupBy !== 'ptj'"
            />
          </div>
          <div class="md:col-span-2 lg:col-span-4">
            <label class="mb-1 block text-xs font-medium text-slate-700">Group By <span class="text-red-500">*</span></label>
            <div class="flex flex-wrap items-center gap-4 text-sm text-slate-700">
              <label class="inline-flex items-center gap-2">
                <input v-model="topFilter.groupBy" type="radio" value="all" />All
              </label>
              <label class="inline-flex items-center gap-2">
                <input v-model="topFilter.groupBy" type="radio" value="ptj" />PTJ
              </label>
            </div>
          </div>
          <div class="md:col-span-2 lg:col-span-4 flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="resetTopFilter"
            >
              Reset
            </button>
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="applyTopFilter"
            >
              Apply
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
              @click="generateWaran"
            >
              Generate
            </button>
          </div>
        </div>
      </article>

      <!-- Datatable (legacy COMPONENTID 3488). -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Budget Initial</h1>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="notMigrated('Add Budget Initial')"
            >
              Add
            </button>
            <button
              type="button"
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              aria-label="More options"
            >
              <MoreVertical class="h-4 w-4" />
            </button>
          </div>
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
                  void loadRows();
                "
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
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
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1100px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Year</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Quarter</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Reference No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Authority Approval</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Amount</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Date</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in rows"
                    :key="row.id ?? `${row.allocateNo}-${row.years}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ row.years ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.quarter ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.allocateNo ?? "" }}</td>
                    <td class="px-3 py-2">{{ row.endorse ?? "" }}</td>
                    <td class="px-3 py-2 text-right">{{ formatAmount(row.amt) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                          'bg-emerald-100 text-emerald-800': row.stat === 'APPROVE',
                          'bg-amber-100 text-amber-800': row.stat === 'DRAFT',
                          'bg-red-100 text-red-800': row.stat === 'REJECT',
                        }"
                      >
                        {{ row.stat ?? "" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ formatDate(row.date) }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canEdit(row)"
                          title="Edit"
                          @click="notMigrated('editor')"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          title="View"
                          @click="notMigrated('viewer')"
                        >
                          <Eye class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          title="Warrant PDF"
                          @click="notMigrated('Warrant Initial PDF')"
                        >
                          <FileText class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100"
                          title="Excel"
                          @click="notMigrated('Excel export per row')"
                        >
                          <Download class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canDelete(row)"
                          title="Delete"
                          @click="notMigrated('delete action')"
                        >
                          <Trash2 class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-slate-500 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!canCancel(row)"
                          title="Cancel approval"
                          @click="openCancelModal(row)"
                        >
                          <Ban class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="rows.length === 0">
                    <td class="px-3 py-6 text-center text-sm text-slate-400" colspan="9">No records.</td>
                  </tr>
                </tbody>
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
      <!-- Smart filter modal (COMPONENTID 6686) -->
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
              <select v-model="smartFilter.smYear" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.year" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Quarter</label>
              <select v-model="smartFilter.smQuarter" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in smartQuarterOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.smStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
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

      <!-- Cancel approval modal (COMPONENTID 4119). Remark required. -->
      <div
        v-if="showCancelModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="showCancelModal = false"
      >
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">
              Are you sure to cancel the approved application?
            </h3>
            <p v-if="cancelRow" class="mt-1 text-xs text-slate-500">
              Reference: <span class="font-medium text-slate-700">{{ cancelRow.allocateNo }}</span>
            </p>
          </div>
          <div class="space-y-3 p-4">
            <label class="mb-1 block text-sm font-medium text-slate-700">
              Remarks <span class="text-red-500">*</span>
            </label>
            <textarea
              v-model="cancelRemark"
              rows="3"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              required
            ></textarea>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg border border-slate-300 px-4 py-2 text-sm"
              @click="showCancelModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
              @click="submitCancel"
            >
              OK
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
