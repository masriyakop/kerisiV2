<script setup lang="ts">
/**
 * General Ledger / Manual Journal Listing
 * (PAGEID 1729 / MENUID 2089)
 *
 * Source: FIMS BL `V2_GL_JOURNAL_API` (endpoints ?listing=1 and
 * ?listing_delete=1). Reads `manual_journal_master` filtered to
 * mjm_system_id IN ('MNL', 'MNL_UNIDENTIFIED', 'MNL_INVEST') and scoped
 * by mjm_typeofjournal. Delete is gated to DRAFT only (mirrors legacy).
 *
 * Filter layout follows the legacy spec exactly:
 *   - COMPONENTID 5104 (Form / Top Filter): single "Type of Journal"
 *     dropdown rendered INSIDE the card header, next to Search. This is
 *     a required filter — until the user picks a value the datatable
 *     stays empty, same as legacy BL.
 *   - COMPONENTID 5106 (Form / Smart Filter): Date range, Status,
 *     Amount range — rendered in a modal opened from the Filter button.
 *
 * Exports:
 *   - Toolbar "PDF" button reproduces the legacy landscape A4 report at
 *     `custom/report/Manual Journal/downloadListPDF.php` — see
 *     `useManualJournalPdf.downloadManualJournalListingPdf`. It refetches
 *     ALL matching rows (no pagination) via the `listing-pdf` endpoint.
 *   - Per-row "PDF" action renders the full Journal document from
 *     `custom/report/Manual Journal/downloadPDFmj.php` — see
 *     `useManualJournalPdf.downloadManualJournalFormPdf`.
 *   - CSV / Excel remain the kitchen-sink defaults (visible-page export)
 *     per rule #8; extend to full-dataset export alongside MENUID 2090.
 *
 * Edit / View / Duplicate row actions are deferred: they deep-link to
 * MENUID 2090 (Manual Journal Form), which is the next migration.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  FileText,
  Filter,
  Info,
  MoreVertical,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  deleteManualJournal,
  getManualJournalDetail,
  getManualJournalListingPdf,
  getManualJournalOptions,
  listManualJournal,
} from "@/api/cms";
import {
  downloadManualJournalFormPdf,
  downloadManualJournalListingPdf,
} from "@/composables/useManualJournalPdf";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  ManualJournalOptions,
  ManualJournalRow,
  ManualJournalSmartFilter,
} from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<ManualJournalRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("createddate");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const typeOfJournal = ref("");
const options = ref<ManualJournalOptions>({ types: [], statuses: [] });

const showSmartFilter = ref(false);
const smartFilter = ref<ManualJournalSmartFilter>({
  enterDateFrom: "",
  enterDateTo: "",
  status: "",
  totalAmtFrom: "",
  totalAmtTo: "",
});

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function fmtMoney(n: number | null): string {
  if (n === null || Number.isNaN(n)) return "-";
  return new Intl.NumberFormat("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}

async function loadOptions() {
  try {
    const res = await getManualJournalOptions();
    options.value = res.data;
    // Default the Type-of-Journal to the first entry so the listing loads
    // something on first visit (legacy page has the same behaviour — the
    // `<select>` auto-selects the first option).
    if (!typeOfJournal.value && res.data.types.length > 0) {
      typeOfJournal.value = res.data.types[0].code;
    }
  } catch {
    options.value = { types: [], statuses: [] };
  }
}

async function loadRows() {
  if (!typeOfJournal.value) {
    rows.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    mjm_typeofjournal: typeOfJournal.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.enterDateFrom
      ? { mjm_enterdate_from: smartFilter.value.enterDateFrom }
      : {}),
    ...(smartFilter.value.enterDateTo
      ? { mjm_enterdate_to: smartFilter.value.enterDateTo }
      : {}),
    ...(smartFilter.value.status ? { mjm_status: smartFilter.value.status } : {}),
    ...(smartFilter.value.totalAmtFrom
      ? { mjm_total_amt_from: smartFilter.value.totalAmtFrom }
      : {}),
    ...(smartFilter.value.totalAmtTo
      ? { mjm_total_amt_to: smartFilter.value.totalAmtTo }
      : {}),
  });
  try {
    const res = await listManualJournal(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load manual journals.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: string) {
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

function onTypeChange() {
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
    enterDateFrom: "",
    enterDateTo: "",
    status: "",
    totalAmtFrom: "",
    totalAmtTo: "",
  };
}

function isDeletable(row: ManualJournalRow): boolean {
  return (row.status ?? "").toUpperCase() === "DRAFT";
}

async function onDelete(row: ManualJournalRow) {
  if (!isDeletable(row)) return;
  const ok = await confirm({
    title: "Delete manual journal?",
    message: `Delete journal “${row.journalNo ?? row.mjmJournalId}”? Only DRAFT journals can be deleted; this cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deleteManualJournal(row.mjmJournalId);
    toast.success("Journal deleted");
    await loadRows();
  } catch (e) {
    toast.error(
      "Delete failed",
      e instanceof Error ? e.message : "Unable to delete journal.",
    );
  }
}

const exportColumns = [
  "Date",
  "Journal No",
  "Description",
  "Amount (RM)",
  "Status",
  "Created By",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "Manual Journal Listing",
    apiDataPath: "/general-ledger/manual-journal",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        Date: r.dateJournal ?? "",
        "Journal No": r.journalNo ?? "",
        Description: r.description ?? "",
        "Amount (RM)": r.amount ?? 0,
        Status: r.status ?? "",
        "Created By": r.createdBy ?? "",
      })),
    datatableRef,
    searchKeyword: q,
  });

/**
 * Listing PDF — matches `custom/report/Manual Journal/downloadListPDF.php`.
 * Fetches ALL matching rows (no pagination) so the printed report reflects
 * the full filter result, not just the visible page.
 */
async function onDownloadListingPdf() {
  if (!typeOfJournal.value) {
    toast.info("Select a type", "Pick a Type of Journal before exporting.");
    return;
  }
  const params = new URLSearchParams({
    mjm_typeofjournal: typeOfJournal.value,
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.enterDateFrom
      ? { mjm_enterdate_from: smartFilter.value.enterDateFrom }
      : {}),
    ...(smartFilter.value.enterDateTo
      ? { mjm_enterdate_to: smartFilter.value.enterDateTo }
      : {}),
    ...(smartFilter.value.status ? { mjm_status: smartFilter.value.status } : {}),
    ...(smartFilter.value.totalAmtFrom
      ? { mjm_total_amt_from: smartFilter.value.totalAmtFrom }
      : {}),
    ...(smartFilter.value.totalAmtTo
      ? { mjm_total_amt_to: smartFilter.value.totalAmtTo }
      : {}),
  });
  try {
    const res = await getManualJournalListingPdf(`?${params.toString()}`);
    if (res.data.rows.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    await downloadManualJournalListingPdf(res.data);
    if (res.data.truncated) {
      toast.info(
        "PDF truncated",
        `Output capped at ${res.data.limit ?? res.data.rows.length} rows. Narrow the filters to see more.`,
      );
    } else {
      toast.success("PDF downloaded");
    }
  } catch (e) {
    toast.error(
      "Export failed",
      e instanceof Error ? e.message : "Unable to generate listing PDF.",
    );
  }
}

/**
 * Per-row Journal PDF — matches
 * `custom/report/Manual Journal/downloadPDFmj.php`.
 */
async function onDownloadRowPdf(row: ManualJournalRow) {
  try {
    const res = await getManualJournalDetail(row.mjmJournalId);
    await downloadManualJournalFormPdf(res.data);
    toast.success("PDF downloaded");
  } catch (e) {
    toast.error(
      "Export failed",
      e instanceof Error ? e.message : "Unable to generate journal PDF.",
    );
  }
}

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Manual Journal Listing");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.dateJournal ?? "",
        r.journalNo ?? "",
        r.description ?? "",
        r.amount ?? 0,
        r.status ?? "",
        r.createdBy ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Manual_Journal_Listing_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="mx-auto max-w-7xl space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />
      <p class="text-base font-semibold text-slate-500">
        General Ledger / Manual Journal Listing
      </p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Manual Journal Listing</h1>
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
            <div class="flex flex-wrap items-center gap-4">
              <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-slate-600">Display</label>
                <select
                  v-model.number="limit"
                  class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                  @change="page = 1; void loadRows()"
                >
                  <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
                </select>
              </div>
              <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-slate-600">Type of Journal</label>
                <select
                  v-model="typeOfJournal"
                  class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                  @change="onTypeChange"
                >
                  <option
                    v-for="t in options.types"
                    :key="t.code"
                    :value="t.code"
                  >
                    {{ t.label }}
                  </option>
                </select>
              </div>
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
                  @keyup.enter="page = 1; void loadRows()"
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

          <div
            v-if="!typeOfJournal"
            class="flex items-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800"
          >
            <Info class="h-4 w-4" />
            <span>Select a Type of Journal to display the listing.</span>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1100px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No.</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_enterdate')"
                    >
                      Date
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_journal_no')"
                    >
                      Journal No.
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_journal_desc')"
                    >
                      Description
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_total_amt')"
                    >
                      Amount (RM)
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_status')"
                    >
                      Status
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('createdby')"
                    >
                      Created By
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.mjmJournalId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ row.dateJournal || "-" }}</td>
                    <td class="px-3 py-2 whitespace-nowrap font-medium text-slate-900">
                      {{ row.journalNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.amount) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                        :title="row.status === 'REJECT' && row.wasNotes ? row.wasNotes : undefined"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 whitespace-nowrap">{{ row.createdBy ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-slate-600 hover:bg-slate-100"
                          title="Download journal PDF"
                          @click="onDownloadRowPdf(row)"
                        >
                          <FileText class="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!isDeletable(row)"
                          :title="
                            isDeletable(row)
                              ? 'Delete (DRAFT only)'
                              : 'Only DRAFT journals can be deleted'
                          "
                          @click="onDelete(row)"
                        >
                          <Trash2 class="h-4 w-4" />
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
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50"
                :disabled="!typeOfJournal"
                @click="onDownloadListingPdf"
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
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div
        v-if="showSmartFilter"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="showSmartFilter = false"
      >
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date from</label>
              <input
                v-model="smartFilter.enterDateFrom"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="dd/mm/yyyy"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date to</label>
              <input
                v-model="smartFilter.enterDateTo"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="dd/mm/yyyy"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="s in options.statuses" :key="s" :value="s">{{ s }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount from (MYR)</label>
              <input
                v-model="smartFilter.totalAmtFrom"
                type="text"
                inputmode="decimal"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm"
                placeholder="0.00"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount to (MYR)</label>
              <input
                v-model="smartFilter.totalAmtTo"
                type="text"
                inputmode="decimal"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm"
                placeholder="0.00"
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
