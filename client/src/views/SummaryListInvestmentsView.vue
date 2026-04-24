<script setup lang="ts">
/**
 * Investment / Summary List of Investments (PAGEID 2316, MENUID 2808)
 *
 * Source: FIMS BL `API_SUMMARY_LIST_OF_NEW_INVESTMENT`
 * (action=listing_all_dt). Read-only datatable scoped to
 * ipf_status IN ('APPROVE','WITHDRAW','PENDING') with a 10-field
 * smart filter (Year of Batch / Batch No / Bank / Institution /
 * Investment Type / Fund Type / Activity / Tenure / Amount / Status).
 * Grand total footer = SUM(principal_amt) across filtered rows.
 *
 * Legacy detail page (menuID 2820) is NOT migrated yet; Action
 * column renders a disabled View button.
 */
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
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getSummaryListInvestmentOptions,
  listSummaryListInvestments,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  SummaryListInvestmentOptions,
  SummaryListInvestmentRow,
  SummaryListInvestmentSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<SummaryListInvestmentRow[]>([]);
const loading = ref(false);
const total = ref(0);
const grandTotal = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "dt_batch"
  | "dt_institution"
  | "dt_invest_no"
  | "dt_invest_type"
  | "dt_fund_type"
  | "dt_activity"
  | "dt_tenure"
  | "dt_amount"
  | "dt_rate"
  | "dt_status";

const sortBy = ref<SortKey>("dt_batch");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref<SummaryListInvestmentSmartFilter>({
  year: "",
  batch: "",
  bank: "",
  institution: "",
  investType: "",
  fundType: "",
  activity: "",
  tenure: "",
  amount: "",
  status: "",
});
const options = ref<SummaryListInvestmentOptions>({
  yearOfBatch: [],
  batchNo: [],
  bank: [],
  institution: [],
  investmentType: [],
  fundType: [],
  activity: [],
  tenure: [],
  status: [],
});

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getSummaryListInvestmentOptions();
    options.value = res.data;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load filter options.",
    );
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
  });
  if (q.value.trim()) params.set("q", q.value.trim());
  if (smartFilter.value.year) params.set("filter_year", smartFilter.value.year);
  if (smartFilter.value.batch) params.set("filter_batch", smartFilter.value.batch);
  if (smartFilter.value.bank) params.set("filter_bank", smartFilter.value.bank);
  if (smartFilter.value.institution)
    params.set("filter_institution", smartFilter.value.institution);
  if (smartFilter.value.investType)
    params.set("filter_invest_type", smartFilter.value.investType);
  if (smartFilter.value.fundType)
    params.set("filter_fund_type", smartFilter.value.fundType);
  if (smartFilter.value.activity)
    params.set("filter_activity", smartFilter.value.activity);
  if (smartFilter.value.tenure) params.set("filter_tenure", smartFilter.value.tenure);
  if (smartFilter.value.amount) params.set("filter_amount", smartFilter.value.amount);
  if (smartFilter.value.status) params.set("filter_status", smartFilter.value.status);
  try {
    const res = await listSummaryListInvestments(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    grandTotal.value = Number(res.meta?.grandTotal ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load list.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: SortKey) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
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
  smartFilter.value = {
    year: "",
    batch: "",
    bank: "",
    institution: "",
    investType: "",
    fundType: "",
    activity: "",
    tenure: "",
    amount: "",
    status: "",
  };
}

function formatMoney(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return value.toLocaleString("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function formatRate(value: number | null): string {
  if (value === null || Number.isNaN(value)) return "-";
  return Number(value).toFixed(2);
}

function institutionCell(row: SummaryListInvestmentRow): string {
  const parts: string[] = [];
  if (row.institutionCode) parts.push(`[${row.institutionCode}]`);
  if (row.institutionName) parts.push(row.institutionName);
  const base = parts.join(" ").trim();
  return row.institutionBranch ? `${base} - ${row.institutionBranch}` : base;
}

function investmentCell(row: SummaryListInvestmentRow): string {
  const lines = [row.investmentNo, row.certificateNo].filter((v): v is string => !!v);
  return lines.length === 0 ? "-" : lines.join(" / ");
}

function typeCell(code: string | null, desc: string | null): string {
  if (!code) return "-";
  return desc ? `${code} - ${desc}` : code;
}

function tenureCell(row: SummaryListInvestmentRow): string {
  const parts: string[] = [];
  if (row.period !== null) {
    parts.push(`${row.period}${row.tenureDesc ? " " + row.tenureDesc : ""}`);
  } else if (row.tenureDesc) {
    parts.push(row.tenureDesc);
  }
  const range = [row.startDate, row.endDate].filter((v): v is string => !!v);
  if (range.length > 0) parts.push(range.join(" - "));
  return parts.length === 0 ? "-" : parts.join(" / ");
}

const exportColumns = [
  "Batch No",
  "Institution",
  "Investment No / Certificate No",
  "Investment Type",
  "Fund Type",
  "Activity",
  "Tenure / Period Duration",
  "Amount (RM)",
  "Rate (%)",
  "Status",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Summary List of Investments",
  apiDataPath: "/investment/summary-list",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Batch No": r.batchNo ?? "",
      Institution: institutionCell(r),
      "Investment No / Certificate No": investmentCell(r),
      "Investment Type": typeCell(r.investmentTypeCode, r.investmentTypeDesc),
      "Fund Type": typeCell(r.fundTypeCode, r.fundTypeDesc),
      Activity: typeCell(r.activityCode, r.activityDesc),
      "Tenure / Period Duration": tenureCell(r),
      "Amount (RM)": r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
      "Rate (%)": r.rate !== null ? formatRate(r.rate) : "",
      Status: r.status ?? "",
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
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
    const ws = wb.addWorksheet("Summary List of Investments");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.batchNo ?? "",
        institutionCell(r),
        investmentCell(r),
        typeCell(r.investmentTypeCode, r.investmentTypeDesc),
        typeCell(r.fundTypeCode, r.fundTypeDesc),
        typeCell(r.activityCode, r.activityDesc),
        tenureCell(r),
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
        r.rate !== null ? formatRate(r.rate) : "",
        r.status ?? "",
      ]);
    });
    // Add Grand Total footer row
    ws.addRow([]);
    ws.addRow(["", "", "", "", "", "", "", "Grand Total", formatMoney(grandTotal.value), "", ""]);
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `SummaryListInvestments_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error(
      "Export failed",
      e instanceof Error ? e.message : "Excel export failed.",
    );
  }
}

function statusBadge(status: string | null): string {
  switch (status) {
    case "APPROVE":
    case "APPROVED":
      return "bg-emerald-100 text-emerald-700";
    case "PENDING":
      return "bg-amber-100 text-amber-700";
    case "WITHDRAW":
      return "bg-sky-100 text-sky-700";
    case "CANCEL":
    case "CANCELLED":
      return "bg-rose-100 text-rose-700";
    default:
      return "bg-slate-100 text-slate-500";
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

onMounted(async () => {
  await loadOptions();
  await loadRows();
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
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

      <h1 class="page-title">Investment / Summary List of Investments</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">
            Summary List of Investments
          </h1>
          <button
            type="button"
            class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
            aria-label="More"
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
                class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50"
                @click="showSmartFilter = true"
              >
                <Filter class="h-4 w-4" />
                Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_batch')"
                    >
                      Batch No
                      <span v-if="sortBy === 'dt_batch'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_institution')"
                    >
                      Institution
                      <span v-if="sortBy === 'dt_institution'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_invest_no')"
                    >
                      Investment No / Certificate No
                      <span v-if="sortBy === 'dt_invest_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_invest_type')"
                    >
                      Investment Type
                      <span v-if="sortBy === 'dt_invest_type'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_fund_type')"
                    >
                      Fund Type
                      <span v-if="sortBy === 'dt_fund_type'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_activity')"
                    >
                      Activity
                      <span v-if="sortBy === 'dt_activity'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_tenure')"
                    >
                      Tenure / Period Duration
                      <span v-if="sortBy === 'dt_tenure'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_amount')"
                    >
                      Amount (RM)
                      <span v-if="sortBy === 'dt_amount'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('dt_rate')"
                    >
                      Rate (%)
                      <span v-if="sortBy === 'dt_rate'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('dt_status')"
                    >
                      Status
                      <span v-if="sortBy === 'dt_status'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.investmentId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                      {{ row.batchNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ institutionCell(row) }}</td>
                    <td class="px-3 py-2">{{ investmentCell(row) }}</td>
                    <td class="px-3 py-2">
                      {{ typeCell(row.investmentTypeCode, row.investmentTypeDesc) }}
                    </td>
                    <td class="px-3 py-2">{{ typeCell(row.fundTypeCode, row.fundTypeDesc) }}</td>
                    <td class="px-3 py-2">{{ typeCell(row.activityCode, row.activityDesc) }}</td>
                    <td class="px-3 py-2">{{ tenureCell(row) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.principalAmount) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatRate(row.rate) }}
                    </td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusBadge(row.status)"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <button
                        type="button"
                        disabled
                        title="View investment detail (legacy menuID 2820 not yet migrated)"
                        class="cursor-not-allowed rounded p-1 text-slate-300"
                      >
                        <Eye class="h-3.5 w-3.5" />
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0">
                  <tr class="border-t-2 border-slate-300 bg-slate-50 font-semibold">
                    <td colspan="8" class="px-3 py-2 text-right text-slate-700">
                      Grand Total
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums text-slate-900">
                      {{ formatMoney(grandTotal) }}
                    </td>
                    <td colspan="3" />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ startIdx }}-{{ endIdx }} of {{ total }}
            </div>
            <div class="flex items-center gap-2">
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
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleDownloadCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
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
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Year of Batch</label>
              <select
                v-model="smartFilter.year"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.yearOfBatch" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Batch No</label>
              <select
                v-model="smartFilter.batch"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.batchNo" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank</label>
              <select
                v-model="smartFilter.bank"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.bank" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Institution</label>
              <select
                v-model="smartFilter.institution"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.institution" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Investment Type</label>
              <select
                v-model="smartFilter.investType"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.investmentType" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fund Type</label>
              <select
                v-model="smartFilter.fundType"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.fundType" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Activity</label>
              <select
                v-model="smartFilter.activity"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.activity" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Tenure</label>
              <select
                v-model="smartFilter.tenure"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.tenure" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount (RM)</label>
              <div class="flex items-stretch">
                <span
                  class="inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-100 px-3 text-sm text-slate-500"
                  >MYR</span
                >
                <input
                  v-model="smartFilter.amount"
                  type="text"
                  inputmode="decimal"
                  placeholder="e.g. 10000"
                  class="w-full rounded-r-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.status" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
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
    </Teleport>
  </AdminLayout>
</template>
