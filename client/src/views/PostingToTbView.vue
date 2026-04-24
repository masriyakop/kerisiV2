<script setup lang="ts">
/**
 * General Ledger / Posting to GL (TB)
 * (PAGEID 1139 / MENUID 1409)
 *
 * Source: FIMS BL `POSTING_TO_TB`. Legacy endpoints
 *   - posting=-1       -> GET  /api/general-ledger/posting-to-tb
 *   - displayMaster
 *     + dtDebitDetails
 *     + dtCreditDetails -> GET /{id}  (one payload)
 *
 * Filter-first behaviour: legacy UI exposes a top-form (System ID + date
 * range + amount) and the user must click Search before any rows appear.
 * The full join over `posting_master` x `posting_details` is very heavy
 * without a filter (hundreds of thousands of grouped rows), so we mirror
 * legacy here: the table stays empty until the smart filter is applied
 * once. After the first apply the text search + pagination + sort behave
 * normally and refetch against the same filters.
 *
 * Per user request (2026-04), the smart filter exposes exactly the three
 * legacy top-form inputs: System ID (dropdown), Date Range (native date
 * pickers, converted to dd/mm/yyyy for the backend which still parses
 * with STR_TO_DATE), and Amount (RM). The extra inputs (Posting No,
 * Document No, References, Status) that an earlier revision merged in
 * from the old Smart Filter pop-up are removed to match legacy.
 *
 * The legacy Action column View icon deep-linked to MENUID 1413
 * ("Posting Details"), which is NOT in the current PAGE_SECOND_LEVEL_MENU
 * migration scope. Following the Journal Listing / PTPTN Data precedent,
 * the View action opens an in-page details modal with the master header
 * plus DR and CR sub-tables. No router-link is emitted until 1413 lands.
 *
 * The page is read-only (legacy BL exposes no delete endpoint).
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
  getPostingToTb,
  getPostingToTbOptions,
  listPostingToTb,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  PostingToTbFooter,
  PostingToTbHeader,
  PostingToTbLine,
  PostingToTbOptions,
  PostingToTbRow,
  PostingToTbSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<PostingToTbRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("pde_trans_date");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);
const footer = ref<PostingToTbFooter>({ amountDt: 0, amountCr: 0 });

const showSmartFilter = ref(false);
const hasSearched = ref(false);
const smartFilter = ref<PostingToTbSmartFilter>({
  systemId: "",
  dateFrom: "",
  dateTo: "",
  totalAmt: "",
});
const options = ref<PostingToTbOptions>({
  systemIds: [],
  statuses: [],
});

/** Convert HTML5 <input type="date"> value (yyyy-mm-dd) to dd/mm/yyyy
 *  which is what the legacy backend expects (STR_TO_DATE(..., '%d/%m/%Y')). */
function toLegacyDate(iso: string): string {
  if (!iso) return "";
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
  return m ? `${m[3]}/${m[2]}/${m[1]}` : iso;
}

const showDetailsModal = ref(false);
const detailsLoading = ref(false);
const detailsHeader = ref<PostingToTbHeader | null>(null);
const detailsDebit = ref<PostingToTbLine[]>([]);
const detailsCredit = ref<PostingToTbLine[]>([]);

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
    const res = await getPostingToTbOptions();
    options.value = res.data;
  } catch {
    options.value = { systemIds: [], statuses: [] };
  }
}

async function loadRows() {
  loading.value = true;
  const dateFrom = toLegacyDate(smartFilter.value.dateFrom);
  const dateTo = toLegacyDate(smartFilter.value.dateTo);
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.systemId
      ? { pmt_system_id: smartFilter.value.systemId }
      : {}),
    ...(dateFrom ? { date_from: dateFrom } : {}),
    ...(dateTo ? { date_to: dateTo } : {}),
    ...(smartFilter.value.totalAmt
      ? { pmt_total_amt: smartFilter.value.totalAmt }
      : {}),
  });
  try {
    const res = await listPostingToTb(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const meta = res.meta as { footer?: PostingToTbFooter } | undefined;
    footer.value = meta?.footer ?? { amountDt: 0, amountCr: 0 };
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
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
  if (!hasSearched.value) return;
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
  hasSearched.value = true;
  void loadRows();
}
function resetSmartFilter() {
  smartFilter.value = {
    systemId: "",
    dateFrom: "",
    dateTo: "",
    totalAmt: "",
  };
}

async function openDetails(id: number) {
  showDetailsModal.value = true;
  detailsLoading.value = true;
  detailsHeader.value = null;
  detailsDebit.value = [];
  detailsCredit.value = [];
  try {
    const res = await getPostingToTb(id);
    detailsHeader.value = res.data.header;
    detailsDebit.value = res.data.debit;
    detailsCredit.value = res.data.credit;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load posting details.",
    );
    showDetailsModal.value = false;
  } finally {
    detailsLoading.value = false;
  }
}

function closeDetails() {
  showDetailsModal.value = false;
  detailsHeader.value = null;
  detailsDebit.value = [];
  detailsCredit.value = [];
}

const exportColumns = [
  "Posting No",
  "Document No",
  "System ID",
  "Transaction Amount (CR)",
  "Transaction Amount (DT)",
  "Status",
  "Reference 1",
  "Reference 2",
  "Posted Date",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "Posting to GL (TB)",
    apiDataPath: "/general-ledger/posting-to-tb",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        "Posting No": r.postingNo ?? "",
        "Document No": r.documentNo ?? "",
        "System ID": r.systemId ?? "",
        "Transaction Amount (CR)": r.amountCr,
        "Transaction Amount (DT)": r.amountDt,
        Status: r.status ?? "",
        "Reference 1": r.reference ?? "",
        "Reference 2": r.reference1 ?? "",
        "Posted Date": r.transDate ?? "",
      })),
    datatableRef,
    searchKeyword: q,
  });

async function exportExcel() {
  try {
    if (rows.value.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Posting to GL (TB)");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.postingNo ?? "",
        r.documentNo ?? "",
        r.systemId ?? "",
        r.amountCr,
        r.amountDt,
        r.status ?? "",
        r.reference ?? "",
        r.reference1 ?? "",
        r.transDate ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Posting_to_GL_TB_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  // The table is filter-gated (legacy parity). Don't kick off a full-join
  // scan just because the user typed in the search box before applying
  // the smart filter — that's the exact perf regression we're fixing.
  if (!hasSearched.value) return;
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadOptions();
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
      <h1 class="page-title">
        General Ledger / Posting to GL (TB)
      </h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Posting</h1>
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
                @change="page = 1; hasSearched && void loadRows()"
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
                  @keyup.enter="page = 1; hasSearched && void loadRows()"
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
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_posting_no')"
                    >
                      Posting No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pde_document_no')"
                    >
                      Document No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_system_id')"
                    >
                      System ID
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">
                      Transaction Amount (CR)
                    </th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">
                      Transaction Amount (DT)
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pmt_status')"
                    >
                      Status
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pde_reference')"
                    >
                      Reference 1
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pde_reference1')"
                    >
                      Reference 2
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pde_trans_date')"
                    >
                      Posted Date
                    </th>
                    <th class="px-3 py-2 text-center text-xs font-semibold uppercase">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="11" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="!hasSearched">
                    <td colspan="11" class="px-3 py-10 text-center text-sm text-slate-500">
                      Use <span class="font-medium text-slate-700">Filter</span> to search
                      postings by System ID, date range or amount.
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="11" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.pmtPostingId}-${row.documentNo}-${row.reference}-${row.reference1}-${row.transDate}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      <!--
                        Legacy Posting No / eye icon deep-linked to MENUID
                        1413 (Posting Details), not in the current migration
                        scope. Clicking opens the in-page details modal.
                        Restore router-link once 1413 lands.
                      -->
                      <button
                        type="button"
                        class="text-sky-600 hover:underline"
                        @click="openDetails(row.pmtPostingId)"
                      >
                        {{ row.postingNo ?? "-" }}
                      </button>
                    </td>
                    <td class="px-3 py-2">{{ row.documentNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.systemId ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.amountCr) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.amountDt) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ row.reference ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.reference1 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.transDate || "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-sky-600 hover:bg-sky-50"
                          title="View"
                          @click="openDetails(row.pmtPostingId)"
                        >
                          <Eye class="h-4 w-4" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot
                  v-if="rows.length > 0"
                  class="sticky bottom-0 border-t border-slate-300 bg-slate-50"
                >
                  <tr>
                    <td
                      colspan="4"
                      class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-700"
                    >
                      Grand Total (filtered)
                    </td>
                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                      {{ fmtMoney(footer.amountCr) }}
                    </td>
                    <td class="px-3 py-2 text-right text-sm font-semibold text-slate-900">
                      {{ fmtMoney(footer.amountDt) }}
                    </td>
                    <td colspan="5"></td>
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
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">System ID</label>
              <select
                v-model="smartFilter.systemId"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.systemIds" :key="opt" :value="opt">
                  {{ opt }}
                </option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date from</label>
              <input
                v-model="smartFilter.dateFrom"
                type="date"
                :max="smartFilter.dateTo || undefined"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date to</label>
              <input
                v-model="smartFilter.dateTo"
                type="date"
                :min="smartFilter.dateFrom || undefined"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Amount (RM)</label>
              <input
                v-model="smartFilter.totalAmt"
                type="number"
                step="0.01"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="Exact total match"
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

      <div
        v-if="showDetailsModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="closeDetails"
      >
        <div
          class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
        >
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Posting Details</h3>
            <button
              type="button"
              class="rounded-lg p-1 text-slate-500 hover:bg-slate-100"
              aria-label="Close"
              @click="closeDetails"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-4 py-4">
            <div v-if="detailsLoading" class="py-12 text-center text-sm text-slate-500">
              Loading posting details...
            </div>
            <template v-else-if="detailsHeader">
              <dl
                class="grid grid-cols-1 gap-x-6 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
              >
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Posting No</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.postingNo ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">System ID</dt>
                  <dd class="font-medium text-slate-900">{{ detailsHeader.systemId ?? "-" }}</dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Status</dt>
                  <dd class="font-medium text-slate-900">{{ detailsHeader.status ?? "-" }}</dd>
                </div>
                <div class="flex flex-col sm:col-span-2 lg:col-span-3">
                  <dt class="text-xs font-medium uppercase text-slate-500">Description</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.description ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Posted Date</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.postedDate ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Posted By</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.postedBy ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Total Amount (RM)</dt>
                  <dd class="font-medium text-slate-900">
                    {{ fmtMoney(detailsHeader.totalAmount) }}
                  </dd>
                </div>
              </dl>

              <section class="mt-4">
                <h4
                  class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-800"
                >
                  <span>Debit ({{ detailsDebit.length }})</span>
                  <span class="text-slate-600">
                    Total DR: {{ fmtMoney(detailsHeader.sumDebit) }}
                  </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                  <div :class="detailsDebit.length > 10 ? 'max-h-[260px] overflow-y-auto' : ''">
                    <table class="w-full min-w-[1100px] text-xs">
                      <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-left">
                          <th class="px-3 py-2 font-semibold uppercase">Fund</th>
                          <th class="px-3 py-2 font-semibold uppercase">Activity</th>
                          <th class="px-3 py-2 font-semibold uppercase">PTJ</th>
                          <th class="px-3 py-2 font-semibold uppercase">Account</th>
                          <th class="px-3 py-2 font-semibold uppercase">Document No</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference 1</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference 2</th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">Amount</th>
                          <th class="px-3 py-2 font-semibold uppercase">Pay To</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="detailsDebit.length === 0" class="border-b border-slate-100">
                          <td colspan="9" class="px-3 py-4 text-center text-sm text-slate-500">
                            No debit lines.
                          </td>
                        </tr>
                        <tr
                          v-for="line in detailsDebit"
                          :key="'dr-' + line.id"
                          class="border-b border-slate-100 hover:bg-slate-50"
                        >
                          <td class="px-3 py-1.5">{{ line.fund ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.activity ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.ptj ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.account ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.documentNo ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference1 ?? "-" }}</td>
                          <td class="px-3 py-1.5 text-right">{{ fmtMoney(line.amount) }}</td>
                          <td class="px-3 py-1.5">{{ line.payTo ?? "-" }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </section>

              <section class="mt-4">
                <h4
                  class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-800"
                >
                  <span>Credit ({{ detailsCredit.length }})</span>
                  <span class="text-slate-600">
                    Total CR: {{ fmtMoney(detailsHeader.sumCredit) }}
                  </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                  <div :class="detailsCredit.length > 10 ? 'max-h-[260px] overflow-y-auto' : ''">
                    <table class="w-full min-w-[1100px] text-xs">
                      <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-left">
                          <th class="px-3 py-2 font-semibold uppercase">Fund</th>
                          <th class="px-3 py-2 font-semibold uppercase">Activity</th>
                          <th class="px-3 py-2 font-semibold uppercase">PTJ</th>
                          <th class="px-3 py-2 font-semibold uppercase">Account</th>
                          <th class="px-3 py-2 font-semibold uppercase">Document No</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference 1</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference 2</th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">Amount</th>
                          <th class="px-3 py-2 font-semibold uppercase">Pay To</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="detailsCredit.length === 0" class="border-b border-slate-100">
                          <td colspan="9" class="px-3 py-4 text-center text-sm text-slate-500">
                            No credit lines.
                          </td>
                        </tr>
                        <tr
                          v-for="line in detailsCredit"
                          :key="'cr-' + line.id"
                          class="border-b border-slate-100 hover:bg-slate-50"
                        >
                          <td class="px-3 py-1.5">{{ line.fund ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.activity ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.ptj ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.account ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.documentNo ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference1 ?? "-" }}</td>
                          <td class="px-3 py-1.5 text-right">{{ fmtMoney(line.amount) }}</td>
                          <td class="px-3 py-1.5">{{ line.payTo ?? "-" }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </section>
            </template>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
              @click="closeDetails"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
