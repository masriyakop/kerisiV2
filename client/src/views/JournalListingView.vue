<script setup lang="ts">
/**
 * General Ledger / Journal Listing
 * (PAGEID 1700 / MENUID 2056)
 *
 * Source: FIMS BL `SNA_API_GLREPORT_JOURNAL_LISTING`. Legacy endpoints
 *   - dt_listJL    -> GET /api/general-ledger/journal-listing
 *   - masterData + dt_debitJL + dt_creditJL -> GET  /{id}  (in one payload)
 *   - deleteMaster -> DELETE /{id}
 *
 * Kitchen Sink "Datatable — smart filter pattern" is applied (rule #3)
 * because the spec lists 6 `form (Smart Filter)` components alongside the
 * datatable. Default PDF/CSV/Excel export buttons are added since
 * COMPONENT_JS on this spec row is boilerplate (rule #8).
 *
 * The legacy Journal No column deep-linked to MENUID 2057 ("Journal
 * Listing Detail"), which is outside the current PAGE_SECOND_LEVEL_MENU
 * migration scope. Following the PTPTN Data precedent, the View action is
 * served by an in-page details modal that shows the master header plus DR
 * and CR sub-tables sourced from `manual_journal_details`.
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
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  deleteJournalListing,
  getJournalListing,
  getJournalListingOptions,
  listJournalListing,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  JournalListingHeader,
  JournalListingLine,
  JournalListingOptions,
  JournalListingRow,
  JournalListingSmartFilter,
} from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<JournalListingRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("mjm_journal_no");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const showSmartFilter = ref(false);
const smartFilter = ref<JournalListingSmartFilter>({
  year: "",
  typeOfJournal: "",
  description: "",
  dateJournal: "",
  status: "",
  systemId: "",
});
const options = ref<JournalListingOptions>({
  types: [],
  statuses: [],
  systemIds: [],
});

const showDetailsModal = ref(false);
const detailsLoading = ref(false);
const detailsHeader = ref<JournalListingHeader | null>(null);
const detailsDebit = ref<JournalListingLine[]>([]);
const detailsCredit = ref<JournalListingLine[]>([]);

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
    const res = await getJournalListingOptions();
    options.value = res.data;
  } catch {
    options.value = { types: [], statuses: [], systemIds: [] };
  }
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.year ? { years: smartFilter.value.year } : {}),
    ...(smartFilter.value.typeOfJournal
      ? { type_of_journal: smartFilter.value.typeOfJournal }
      : {}),
    ...(smartFilter.value.description
      ? { description: smartFilter.value.description }
      : {}),
    ...(smartFilter.value.dateJournal
      ? { date_journal: smartFilter.value.dateJournal }
      : {}),
    ...(smartFilter.value.status ? { status: smartFilter.value.status } : {}),
    ...(smartFilter.value.systemId ? { system_id: smartFilter.value.systemId } : {}),
  });
  try {
    const res = await listJournalListing(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
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
    typeOfJournal: "",
    description: "",
    dateJournal: "",
    status: "",
    systemId: "",
  };
}

async function openDetails(id: number) {
  showDetailsModal.value = true;
  detailsLoading.value = true;
  detailsHeader.value = null;
  detailsDebit.value = [];
  detailsCredit.value = [];
  try {
    const res = await getJournalListing(id);
    detailsHeader.value = res.data.header;
    detailsDebit.value = res.data.debit;
    detailsCredit.value = res.data.credit;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load journal details.");
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

function isDeletable(row: JournalListingRow): boolean {
  const s = (row.status ?? "").toUpperCase();
  return s !== "POSTED" && s !== "CANCEL" && s !== "CANCELLED";
}

async function onDelete(row: JournalListingRow) {
  if (!isDeletable(row)) return;
  const ok = await confirm({
    title: "Delete journal?",
    message: `Delete journal “${row.journalNo ?? row.mjmJournalId}” and all its line items? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deleteJournalListing(row.mjmJournalId);
    toast.success("Journal deleted");
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete journal.");
  }
}

const exportColumns = [
  "Journal No",
  "Description",
  "Type of Journal",
  "Amount (RM)",
  "Status",
  "System ID",
  "Date Journal",
  "Created By",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "Journal Listing",
    apiDataPath: "/general-ledger/journal-listing",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        "Journal No": r.journalNo ?? "",
        Description: r.description ?? "",
        "Type of Journal": r.typeOfJournal ?? "",
        "Amount (RM)": r.amount ?? 0,
        Status: r.status ?? "",
        "System ID": r.systemId ?? "",
        "Date Journal": r.dateJournal ?? "",
        "Created By": r.createdBy ?? "",
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
    const ws = wb.addWorksheet("Journal Listing");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.journalNo ?? "",
        r.description ?? "",
        r.typeOfJournal ?? "",
        r.amount ?? 0,
        r.status ?? "",
        r.systemId ?? "",
        r.dateJournal ?? "",
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
    a.download = `Journal_Listing_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
      <p class="text-base font-semibold text-slate-500">General Ledger / Journal Listing</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Journal Listing</h1>
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
                @change="page = 1; void loadRows()"
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

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_journal_no')"
                    >
                      Journal No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_journal_desc')"
                    >
                      Description
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_typeofjournal')"
                    >
                      Type of Journal
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
                      @click="toggleSort('mjm_system_id')"
                    >
                      System ID
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('mjm_enterdate')"
                    >
                      Date Journal
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
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="10" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.mjmJournalId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      <!--
                        Legacy Journal No deep-linked to MENUID 2057 (Journal
                        Listing Detail), which is not in the current
                        PAGE_SECOND_LEVEL_MENU migration scope. The View
                        action (eye icon) opens an in-page details modal
                        instead; restore router-link once 2057 lands.
                      -->
                      <button
                        type="button"
                        class="text-sky-600 hover:underline"
                        @click="openDetails(row.mjmJournalId)"
                      >
                        {{ row.journalNo ?? "-" }}
                      </button>
                    </td>
                    <td class="px-3 py-2">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.typeOfJournal ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.amount) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ row.systemId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.dateJournal || "-" }}</td>
                    <td class="px-3 py-2">{{ row.createdBy ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-sky-600 hover:bg-sky-50"
                          title="View"
                          @click="openDetails(row.mjmJournalId)"
                        >
                          <Eye class="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="!isDeletable(row)"
                          :title="
                            isDeletable(row)
                              ? 'Delete'
                              : 'Cannot delete — journal already ' + (row.status ?? '').toLowerCase()
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
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Year</label>
              <input
                v-model="smartFilter.year"
                type="text"
                inputmode="numeric"
                maxlength="4"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="e.g. 2024"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Type of Journal</label>
              <select
                v-model="smartFilter.typeOfJournal"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.types" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
              <input
                v-model="smartFilter.description"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="Description contains..."
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Date Journal</label>
              <input
                v-model="smartFilter.dateJournal"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                placeholder="dd/mm/yyyy"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select
                v-model="smartFilter.status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.statuses" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
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
            <h3 class="text-base font-semibold text-slate-900">Journal Details</h3>
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
              Loading journal details...
            </div>
            <template v-else-if="detailsHeader">
              <dl
                class="grid grid-cols-1 gap-x-6 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
              >
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Journal No</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.journalNo ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Type of Journal</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.typeOfJournal ?? "-" }}
                  </dd>
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
                  <dt class="text-xs font-medium uppercase text-slate-500">System ID</dt>
                  <dd class="font-medium text-slate-900">{{ detailsHeader.systemId ?? "-" }}</dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Date Journal</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.dateJournal ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Total Amount (RM)</dt>
                  <dd class="font-medium text-slate-900">{{ fmtMoney(detailsHeader.amount) }}</dd>
                </div>
              </dl>

              <section class="mt-4">
                <h4 class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-800">
                  <span>Debit ({{ detailsDebit.length }})</span>
                  <span class="text-slate-600">Total DR: {{ fmtMoney(detailsHeader.sumDebit) }}</span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                  <div
                    :class="detailsDebit.length > 10 ? 'max-h-[260px] overflow-y-auto' : ''"
                  >
                    <table class="w-full min-w-[1100px] text-xs">
                      <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-left">
                          <th class="px-3 py-2 font-semibold uppercase">PTJ</th>
                          <th class="px-3 py-2 font-semibold uppercase">Fund</th>
                          <th class="px-3 py-2 font-semibold uppercase">Activity</th>
                          <th class="px-3 py-2 font-semibold uppercase">Cost Centre</th>
                          <th class="px-3 py-2 font-semibold uppercase">Account</th>
                          <th class="px-3 py-2 font-semibold uppercase">Document No</th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">Amount</th>
                          <th class="px-3 py-2 font-semibold uppercase">Tax</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference</th>
                          <th class="px-3 py-2 font-semibold uppercase">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-if="detailsDebit.length === 0"
                          class="border-b border-slate-100"
                        >
                          <td
                            colspan="10"
                            class="px-3 py-4 text-center text-sm text-slate-500"
                          >
                            No debit lines.
                          </td>
                        </tr>
                        <tr
                          v-for="line in detailsDebit"
                          :key="'dr-' + line.id"
                          class="border-b border-slate-100 hover:bg-slate-50"
                        >
                          <td class="px-3 py-1.5">{{ line.ounCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.fundType ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.activityCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.costCentre ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.acctCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.documentNo ?? "-" }}</td>
                          <td class="px-3 py-1.5 text-right">{{ fmtMoney(line.amount) }}</td>
                          <td class="px-3 py-1.5">{{ line.taxcode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.status ?? "-" }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </section>

              <section class="mt-4">
                <h4 class="mb-2 flex items-center justify-between text-sm font-semibold text-slate-800">
                  <span>Credit ({{ detailsCredit.length }})</span>
                  <span class="text-slate-600">
                    Total CR: {{ fmtMoney(detailsHeader.sumCredit) }}
                  </span>
                </h4>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                  <div
                    :class="detailsCredit.length > 10 ? 'max-h-[260px] overflow-y-auto' : ''"
                  >
                    <table class="w-full min-w-[1100px] text-xs">
                      <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-left">
                          <th class="px-3 py-2 font-semibold uppercase">PTJ</th>
                          <th class="px-3 py-2 font-semibold uppercase">Fund</th>
                          <th class="px-3 py-2 font-semibold uppercase">Activity</th>
                          <th class="px-3 py-2 font-semibold uppercase">Cost Centre</th>
                          <th class="px-3 py-2 font-semibold uppercase">Account</th>
                          <th class="px-3 py-2 font-semibold uppercase">Document No</th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">Amount</th>
                          <th class="px-3 py-2 font-semibold uppercase">Tax</th>
                          <th class="px-3 py-2 font-semibold uppercase">Reference</th>
                          <th class="px-3 py-2 font-semibold uppercase">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-if="detailsCredit.length === 0"
                          class="border-b border-slate-100"
                        >
                          <td
                            colspan="10"
                            class="px-3 py-4 text-center text-sm text-slate-500"
                          >
                            No credit lines.
                          </td>
                        </tr>
                        <tr
                          v-for="line in detailsCredit"
                          :key="'cr-' + line.id"
                          class="border-b border-slate-100 hover:bg-slate-50"
                        >
                          <td class="px-3 py-1.5">{{ line.ounCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.fundType ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.activityCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.costCentre ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.acctCode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.documentNo ?? "-" }}</td>
                          <td class="px-3 py-1.5 text-right">{{ fmtMoney(line.amount) }}</td>
                          <td class="px-3 py-1.5">{{ line.taxcode ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.reference ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.status ?? "-" }}</td>
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
