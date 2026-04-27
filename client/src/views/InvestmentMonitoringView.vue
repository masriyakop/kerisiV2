<script setup lang="ts">
/**
 * Investment / Monitoring (PAGEID 1183, MENUID 1458)
 *
 * Source: FIMS BL `ATR_INVESTMENT_MONITORING`.
 *
 * Two-level drill-down:
 *   1. Batches: investment_profile grouped by ipf_batch_no, sum of
 *      ipf_principal_amt. Row action "Open" expands the batch.
 *   2. Investments in the selected batch: joins manual_journal_master
 *      (system_id='JOURNAL_INVEST') + receipt_details + receipt_master.
 *      Receipt No / Receipt Date columns are kept in the payload but
 *      hidden from the UI (legacy d-none, hidden yet "sortable" per
 *      dt_sort — exposed via the extra sort keys if needed).
 *
 * Scope (both levels): ipf_status IN ('APPROVE','MATURED')
 *   AND ipf_ref_investment_no IS NULL
 *   AND (bim_bills_no <> 'RENEW' OR bim_bills_no IS NULL).
 *
 * Legacy PDF report buttons:
 *   - `action=summary` (investmentSummary_pdf) IS migrated —
 *     downloaded via `downloadInvestmentMonitoringSummaryPdf`
 *     (jsPDF landscape A4). Entry points:
 *       a) per-batch "Download Summary PDF" icon in the Level-1
 *          batch-list row-action column, and
 *       b) "Download Summary" button at the Level-2 header.
 *   - `action=billBatch` (billRegistrationInvestBatch_pdf) +
 *     `action=reportUrl` (billRegistrationInvest_pdf) render
 *     DISABLED — deferred. They require joining `bills_master`,
 *     `bills_details`, `wf_task`, `wf_task_history`, `staff`,
 *     `bank_master`, `lookup_details` and rendering a per-bill
 *     workflow document with 5 signer levels.
 *
 * Row-action "Download Journal" IS wired up: reuses the existing
 * Manual Journal PDF flow (`getManualJournalDetail` +
 * `downloadManualJournalFormPdf`) against the row's `journalId`.
 *
 * Row-action "View" (legacy menuID 3226) remains disabled — the
 * target page has no migrated route yet.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  ArrowLeft,
  CornerDownRight,
  Download,
  FileDown,
  FileSpreadsheet,
  FileText,
  MoreVertical,
  Search,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  getInvestmentMonitoringSummaryPdf,
  getManualJournalDetail,
  listInvestmentMonitoringBatches,
  listInvestmentMonitoringInvestments,
} from "@/api/cms";
import { downloadInvestmentMonitoringSummaryPdf } from "@/composables/useInvestmentMonitoringPdf";
import { downloadManualJournalFormPdf } from "@/composables/useManualJournalPdf";
import { useToast } from "@/composables/useToast";
import type {
  InvestmentMonitoringBatchRow,
  InvestmentMonitoringInvestmentRow,
} from "@/types";

const toast = useToast();

// ---------------- Level 1: batches ---------------------------------
const batchesDatatableRef = ref<DatatableRefApi | null>(null);
const batchRows = ref<InvestmentMonitoringBatchRow[]>([]);
const batchLoading = ref(false);
const batchTotal = ref(0);
const batchPage = ref(1);
const batchLimit = ref(10);
const batchQ = ref("");

type BatchSortKey = "dt_batch" | "dt_total";
const batchSortBy = ref<BatchSortKey>("dt_batch");
const batchSortDir = ref<"asc" | "desc">("desc");

const batchTotalPages = computed(() =>
  batchTotal.value ? Math.max(1, Math.ceil(batchTotal.value / batchLimit.value)) : 1,
);
const batchStartIdx = computed(() =>
  batchTotal.value === 0 ? 0 : (batchPage.value - 1) * batchLimit.value + 1,
);
const batchEndIdx = computed(() =>
  Math.min(batchPage.value * batchLimit.value, batchTotal.value),
);

async function loadBatches() {
  batchLoading.value = true;
  const params = new URLSearchParams({
    page: String(batchPage.value),
    limit: String(batchLimit.value),
    sort_by: batchSortBy.value,
    sort_dir: batchSortDir.value,
  });
  if (batchQ.value.trim()) params.set("q", batchQ.value.trim());
  try {
    const res = await listInvestmentMonitoringBatches(`?${params.toString()}`);
    batchRows.value = res.data;
    batchTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load batches.",
    );
  } finally {
    batchLoading.value = false;
  }
}

function toggleBatchSort(col: BatchSortKey) {
  if (batchSortBy.value === col) {
    batchSortDir.value = batchSortDir.value === "asc" ? "desc" : "asc";
  } else {
    batchSortBy.value = col;
    batchSortDir.value = "asc";
  }
  void loadBatches();
}

function prevBatchPage() {
  if (batchPage.value > 1) {
    batchPage.value -= 1;
    void loadBatches();
  }
}

function nextBatchPage() {
  if (batchPage.value < batchTotalPages.value) {
    batchPage.value += 1;
    void loadBatches();
  }
}

// ---------------- Level 2: investments -----------------------------
const selectedBatch = ref<string | null>(null);
const selectedBatchTotal = ref(0);
const investDatatableRef = ref<DatatableRefApi | null>(null);
const investRows = ref<InvestmentMonitoringInvestmentRow[]>([]);
const investLoading = ref(false);
const investTotal = ref(0);
const investGrandTotal = ref(0);
const investPage = ref(1);
const investLimit = ref(10);
const investQ = ref("");

type InvestSortKey =
  | "dt_institution"
  | "dt_journal_no"
  | "dt_principal"
  | "dt_rate"
  | "dt_receipt_no"
  | "dt_receipt_date";

const investSortBy = ref<InvestSortKey>("dt_institution");
const investSortDir = ref<"asc" | "desc">("asc");

const investTotalPages = computed(() =>
  investTotal.value ? Math.max(1, Math.ceil(investTotal.value / investLimit.value)) : 1,
);
const investStartIdx = computed(() =>
  investTotal.value === 0 ? 0 : (investPage.value - 1) * investLimit.value + 1,
);
const investEndIdx = computed(() =>
  Math.min(investPage.value * investLimit.value, investTotal.value),
);

async function loadInvestments() {
  if (!selectedBatch.value) return;
  investLoading.value = true;
  const params = new URLSearchParams({
    page: String(investPage.value),
    limit: String(investLimit.value),
    sort_by: investSortBy.value,
    sort_dir: investSortDir.value,
    batch: selectedBatch.value,
  });
  if (investQ.value.trim()) params.set("q", investQ.value.trim());
  try {
    const res = await listInvestmentMonitoringInvestments(`?${params.toString()}`);
    investRows.value = res.data;
    investTotal.value = Number(res.meta?.total ?? 0);
    investGrandTotal.value = Number(res.meta?.grandTotalPrincipal ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load investments.",
    );
  } finally {
    investLoading.value = false;
  }
}

function toggleInvestSort(col: InvestSortKey) {
  if (investSortBy.value === col) {
    investSortDir.value = investSortDir.value === "asc" ? "desc" : "asc";
  } else {
    investSortBy.value = col;
    investSortDir.value = "asc";
  }
  void loadInvestments();
}

function prevInvestPage() {
  if (investPage.value > 1) {
    investPage.value -= 1;
    void loadInvestments();
  }
}

function nextInvestPage() {
  if (investPage.value < investTotalPages.value) {
    investPage.value += 1;
    void loadInvestments();
  }
}

function openBatch(row: InvestmentMonitoringBatchRow) {
  if (!row.batchNo) return;
  selectedBatch.value = row.batchNo;
  selectedBatchTotal.value = row.totalAmount ?? 0;
  investPage.value = 1;
  investQ.value = "";
  investSortBy.value = "dt_institution";
  investSortDir.value = "asc";
  void loadInvestments();
}

function backToBatches() {
  selectedBatch.value = null;
  investRows.value = [];
  investTotal.value = 0;
  investGrandTotal.value = 0;
}

// Row-action: Download Journal. Mirrors the per-row PDF button on
// ManualJournalListingView — fetches the journal detail for the
// attached mjm_journal_id and renders it via the shared jsPDF
// form generator. Legacy called `investment.downloadjournal()`
// which ultimately resolved to `downloadPDFmj.php`; we reuse the
// migrated flow end-to-end.
const downloadingJournalId = ref<number | null>(null);

async function onDownloadJournal(row: InvestmentMonitoringInvestmentRow) {
  if (!row.journalId) {
    toast.info(
      "No journal attached",
      "This investment has no linked manual journal to download.",
    );
    return;
  }
  if (downloadingJournalId.value !== null) return;
  downloadingJournalId.value = row.journalId;
  try {
    const res = await getManualJournalDetail(row.journalId);
    await downloadManualJournalFormPdf(res.data);
    toast.success("PDF downloaded");
  } catch (e) {
    toast.error(
      "Download failed",
      e instanceof Error ? e.message : "Unable to generate the journal PDF.",
    );
  } finally {
    downloadingJournalId.value = null;
  }
}

// --- Batch-level "Investment Summary" PDF (action=summary in the
// legacy `ATR_INVESTMENT_MONITORING`, binary
// investmentSummary_pdf.php). Pulls the full row set (capped by the
// backend at PDF_ROW_LIMIT) + batch totals and hands it to the
// jsPDF composable. `downloadingSummaryBatch` gates both the
// Level-1 per-row icon and the Level-2 header button so they
// can't double-fire.
const downloadingSummaryBatch = ref<string | null>(null);

async function onDownloadSummary(batch: string | null) {
  const target = (batch ?? "").trim();
  if (!target) {
    toast.error("Batch required", "No batch number available for the report.");
    return;
  }
  if (downloadingSummaryBatch.value !== null) return;
  downloadingSummaryBatch.value = target;
  try {
    const params = new URLSearchParams({ batch: target });
    const res = await getInvestmentMonitoringSummaryPdf(`?${params.toString()}`);
    await downloadInvestmentMonitoringSummaryPdf(res.data);
    if (res.data.truncated) {
      toast.info(
        "Report truncated",
        `Showing the first ${res.data.limit} investments — narrow filters to see more.`,
      );
    } else {
      toast.success("PDF downloaded");
    }
  } catch (e) {
    toast.error(
      "Download failed",
      e instanceof Error ? e.message : "Unable to generate the summary PDF.",
    );
  } finally {
    downloadingSummaryBatch.value = null;
  }
}

// ---------------- shared formatters -------------------------------
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

function institutionLabel(row: InvestmentMonitoringInvestmentRow): string {
  const code = row.institutionCode ? `[${row.institutionCode}]` : "";
  const desc = row.institutionDesc ?? "";
  const branch = row.institutionBranch ? ` - ${row.institutionBranch}` : "";
  return `${code} ${desc}${branch}`.trim();
}

function tenureLine(row: InvestmentMonitoringInvestmentRow): string {
  const head = [row.period !== null ? String(row.period) : "", row.tenureDesc ?? ""]
    .filter(Boolean)
    .join(" ");
  const range = [row.startDate, row.endDate].filter(Boolean).join(" - ");
  return [head, range].filter(Boolean).join(" / ");
}

function journalCell(row: InvestmentMonitoringInvestmentRow): string {
  if (!row.journalNo && !row.journalStatus) return "";
  if (row.journalStatus) {
    return `${row.journalNo ?? ""} (${row.journalStatus})`.trim();
  }
  return row.journalNo ?? "";
}

function statusBadge(status: string | null): string {
  const map: Record<string, string> = {
    APPROVE: "bg-emerald-100 text-emerald-700",
    MATURED: "bg-sky-100 text-sky-700",
  };
  return map[status ?? ""] ?? "bg-slate-100 text-slate-700";
}

// ---------------- exports -----------------------------------------
const batchExportColumns = ["Batch No", "Total Amount (RM)"];

const {
  templateFileInputRef: batchTemplateFileInputRef,
  onTemplateFileChange: onBatchTemplateFileChange,
  handleDownloadPDF: handleBatchPDF,
  handleDownloadCSV: handleBatchCSV,
} = useDatatableFeatures({
  pageName: "Investment Monitoring - Batches",
  apiDataPath: "/investment/monitoring/batches",
  defaultExportColumns: batchExportColumns,
  getFilteredList: () =>
    batchRows.value.map((r) => ({
      "Batch No": r.batchNo ?? "",
      "Total Amount (RM)": r.totalAmount !== null ? formatMoney(r.totalAmount) : "",
    })),
  datatableRef: batchesDatatableRef,
  searchKeyword: batchQ,
  applyFilters: () => void loadBatches(),
});

const investExportColumns = [
  "Institution",
  "Investment No. / Certification No.",
  "Journal No. / Status",
  "Tenure / Period Duration",
  "Principal (RM)",
  "Rate (%)",
  "Status",
];

const {
  templateFileInputRef: investTemplateFileInputRef,
  onTemplateFileChange: onInvestTemplateFileChange,
  handleDownloadPDF: handleInvestPDF,
  handleDownloadCSV: handleInvestCSV,
} = useDatatableFeatures({
  pageName: "Investment Monitoring - Investments",
  apiDataPath: "/investment/monitoring/investments",
  defaultExportColumns: investExportColumns,
  getFilteredList: () =>
    investRows.value.map((r) => ({
      Institution: institutionLabel(r),
      "Investment No. / Certification No.": [r.investmentNo, r.certificateNo]
        .filter(Boolean)
        .join(" / "),
      "Journal No. / Status": journalCell(r),
      "Tenure / Period Duration": tenureLine(r),
      "Principal (RM)":
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
      "Rate (%)": r.rate !== null ? formatRate(r.rate) : "",
      Status: r.status ?? "",
    })),
  datatableRef: investDatatableRef,
  searchKeyword: investQ,
  applyFilters: () => void loadInvestments(),
});

async function exportBatchExcel() {
  try {
    if (batchRows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Batches");
    ws.addRow(["No", ...batchExportColumns]);
    batchRows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.batchNo ?? "",
        r.totalAmount !== null ? formatMoney(r.totalAmount) : "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `InvestmentMonitoring_Batches_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

async function exportInvestExcel() {
  try {
    if (investRows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Investments");
    ws.addRow(["No", ...investExportColumns]);
    investRows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        institutionLabel(r),
        [r.investmentNo, r.certificateNo].filter(Boolean).join(" / "),
        journalCell(r),
        tenureLine(r),
        r.principalAmount !== null ? formatMoney(r.principalAmount) : "",
        r.rate !== null ? formatRate(r.rate) : "",
        r.status ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `InvestmentMonitoring_${selectedBatch.value ?? "Batch"}_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

// ---------------- debounced search --------------------------------
let batchSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(batchQ, () => {
  if (batchSearchDebounce) clearTimeout(batchSearchDebounce);
  batchSearchDebounce = setTimeout(() => {
    batchSearchDebounce = null;
    batchPage.value = 1;
    void loadBatches();
  }, 350);
});

let investSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(investQ, () => {
  if (investSearchDebounce) clearTimeout(investSearchDebounce);
  investSearchDebounce = setTimeout(() => {
    investSearchDebounce = null;
    investPage.value = 1;
    void loadInvestments();
  }, 350);
});

onMounted(async () => {
  await loadBatches();
});

onUnmounted(() => {
  if (batchSearchDebounce) clearTimeout(batchSearchDebounce);
  if (investSearchDebounce) clearTimeout(investSearchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input
        ref="batchTemplateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onBatchTemplateFileChange"
      />
      <input
        ref="investTemplateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onInvestTemplateFileChange"
      />

      <h1 class="page-title">Investment / Monitoring<template v-if="selectedBatch"> / {{ selectedBatch }}</template></h1>

      <!-- ===================== Level 1 : Batches ===================== -->
      <article
        v-if="!selectedBatch"
        class="rounded-lg border border-slate-200 bg-white shadow-sm"
      >
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Monitoring — Batches</h1>
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
                v-model.number="batchLimit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="
                  batchPage = 1;
                  loadBatches();
                "
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">
                  {{ n }}
                </option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  v-model="batchQ"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="
                    batchPage = 1;
                    void loadBatches();
                  "
                />
                <button
                  v-if="batchQ"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="batchQ = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <!--
                Legacy ATR_INVESTMENT_MONITORING exposes three PDF
                actions: investmentSummary_pdf is MIGRATED (see the
                per-row "Summary" icon below and the Level-2 header
                "Download Summary" button); billRegistrationInvestBatch_pdf
                and billRegistrationInvest_pdf remain DEFERRED — they
                require bills_master / bills_details / wf_task /
                wf_task_history / staff / bank_master / lookup_details
                joins and a per-bill workflow layout.
              -->
              <button
                type="button"
                disabled
                title="Bill registration PDFs (billRegistrationInvestBatch_pdf) are not migrated yet. The Summary PDF is available per-batch — use the download icon on each row."
                class="inline-flex cursor-not-allowed items-center gap-1 rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-sm font-medium text-slate-400"
              >
                <FileText class="h-4 w-4" />
                Bill Registration
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="batchRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[640px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleBatchSort('dt_batch')"
                    >
                      Batch No
                      <span v-if="batchSortBy === 'dt_batch'">{{
                        batchSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleBatchSort('dt_total')"
                    >
                      Total Amount (RM)
                      <span v-if="batchSortBy === 'dt_total'">{{
                        batchSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="batchLoading">
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="batchRows.length === 0">
                    <td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in batchRows"
                    :key="row.batchNo ?? row.index"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="whitespace-nowrap px-3 py-2 font-medium text-slate-900">
                      {{ row.batchNo ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.totalAmount) }}
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-100"
                          title="Open batch"
                          :disabled="!row.batchNo"
                          @click="openBatch(row)"
                        >
                          <CornerDownRight class="h-3.5 w-3.5" />
                          Open
                        </button>
                        <button
                          type="button"
                          :disabled="!row.batchNo || downloadingSummaryBatch !== null"
                          :title="
                            row.batchNo
                              ? 'Download Investment Summary PDF for this batch'
                              : 'Batch number unavailable'
                          "
                          :class="[
                            'inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-medium',
                            row.batchNo && downloadingSummaryBatch === null
                              ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                              : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400',
                          ]"
                          @click="onDownloadSummary(row.batchNo)"
                        >
                          <FileText class="h-3.5 w-3.5" />
                          <span v-if="downloadingSummaryBatch === row.batchNo">
                            …
                          </span>
                          <span v-else>Summary</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ batchStartIdx }}-{{ batchEndIdx }} of {{ batchTotal }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="batchPage <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevBatchPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600"
                >Page {{ batchPage }} / {{ batchTotalPages }}</span
              >
              <button
                type="button"
                :disabled="batchPage >= batchTotalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextBatchPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleBatchPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleBatchCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportBatchExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>

      <!-- ===================== Level 2 : Investments ===================== -->
      <article v-else class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"
        >
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
              @click="backToBatches"
            >
              <ArrowLeft class="h-3.5 w-3.5" />
              Back to batches
            </button>
            <div>
              <h1 class="text-base font-semibold text-slate-900">
                Monitoring — Batch {{ selectedBatch }}
              </h1>
              <p class="text-xs text-slate-500">
                Batch total: RM {{ formatMoney(selectedBatchTotal) }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="!selectedBatch || downloadingSummaryBatch !== null"
              :title="
                selectedBatch
                  ? 'Download Investment Summary PDF for this batch'
                  : 'Batch unavailable'
              "
              :class="[
                'inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium',
                selectedBatch && downloadingSummaryBatch === null
                  ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                  : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400',
              ]"
              @click="onDownloadSummary(selectedBatch)"
            >
              <FileText class="h-3.5 w-3.5" />
              <span v-if="downloadingSummaryBatch === selectedBatch">
                Downloading…
              </span>
              <span v-else>Download Summary</span>
            </button>
            <button
              type="button"
              class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"
              aria-label="More"
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
                v-model.number="investLimit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="
                  investPage = 1;
                  loadInvestments();
                "
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">
                  {{ n }}
                </option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  v-model="investQ"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-72 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
                  @keyup.enter="
                    investPage = 1;
                    void loadInvestments();
                  "
                />
                <button
                  v-if="investQ"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="investQ = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="investRows.length > 10 ? 'max-h-[520px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1100px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleInvestSort('dt_institution')"
                    >
                      Institution
                      <span v-if="investSortBy === 'dt_institution'">{{
                        investSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">
                      Investment No. /<br />Certification No.
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleInvestSort('dt_journal_no')"
                    >
                      Journal No. /<br />Status
                      <span v-if="investSortBy === 'dt_journal_no'">{{
                        investSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">
                      Tenure /<br />Period Duration
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleInvestSort('dt_principal')"
                    >
                      Principal (RM)
                      <span v-if="investSortBy === 'dt_principal'">{{
                        investSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleInvestSort('dt_rate')"
                    >
                      Rate (%)
                      <span v-if="investSortBy === 'dt_rate'">{{
                        investSortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="investLoading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="investRows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in investRows"
                    :key="row.investmentId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ institutionLabel(row) }}</td>
                    <td class="px-3 py-2">
                      <div>{{ row.investmentNo ?? "-" }}</div>
                      <div v-if="row.certificateNo" class="text-xs text-slate-500">
                        {{ row.certificateNo }}
                      </div>
                    </td>
                    <td class="px-3 py-2">
                      <div>{{ row.journalNo ?? "-" }}</div>
                      <div v-if="row.journalStatus" class="text-xs text-slate-500">
                        ({{ row.journalStatus }})
                      </div>
                    </td>
                    <td class="px-3 py-2">
                      <div v-if="row.period !== null || row.tenureDesc">
                        {{
                          [
                            row.period !== null ? String(row.period) : "",
                            row.tenureDesc ?? "",
                          ]
                            .filter(Boolean)
                            .join(" ")
                        }}
                      </div>
                      <div class="text-xs text-slate-500">
                        {{ row.startDate ?? "" }}
                        <span v-if="row.endDate"> - {{ row.endDate }}</span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatMoney(row.principalAmount) }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right tabular-nums">
                      {{ formatRate(row.rate) }}
                    </td>
                    <td class="px-3 py-2">
                      <span
                        :class="[
                          'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                          statusBadge(row.status),
                        ]"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          :disabled="!row.journalId || downloadingJournalId !== null"
                          :title="
                            row.journalId
                              ? `Download Journal ${row.journalNo ?? ''}`
                              : 'No journal attached'
                          "
                          :class="[
                            'inline-flex items-center rounded-lg border px-2 py-1 text-xs',
                            row.journalId && downloadingJournalId === null
                              ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                              : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400',
                          ]"
                          @click="onDownloadJournal(row)"
                        >
                          <Download class="h-3.5 w-3.5" />
                          <span
                            v-if="downloadingJournalId === row.journalId"
                            class="ml-1"
                          >
                            ...
                          </span>
                        </button>
                        <button
                          type="button"
                          disabled
                          title="Legacy view (menuID 3226) not yet migrated"
                          class="inline-flex cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-100 px-2 py-1 text-xs text-slate-400"
                        >
                          View
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="investRows.length > 0" class="bg-slate-50">
                  <tr class="border-t border-slate-200">
                    <td colspan="5" class="px-3 py-2 text-right text-xs font-semibold text-slate-600">
                      Grand Total
                    </td>
                    <td class="whitespace-nowrap px-3 py-2 text-right text-sm font-semibold tabular-nums">
                      {{ formatMoney(investGrandTotal) }}
                    </td>
                    <td />
                    <td />
                    <td />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ investStartIdx }}-{{ investEndIdx }} of {{ investTotal }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="investPage <= 1"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevInvestPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600"
                >Page {{ investPage }} / {{ investTotalPages }}</span
              >
              <button
                type="button"
                :disabled="investPage >= investTotalPages"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextInvestPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleInvestPDF"
              >
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="handleInvestCSV"
              >
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"
                @click="exportInvestExcel"
              >
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
