<script setup lang="ts">
/**
 * Student Finance / PTPTN Data
 * (PAGEID 857 / MENUID 1031)
 *
 * Source: FIMS BL `API_PTPTN_DATA` (referenced by the legacy datatable
 * `dt_ajax`). The legacy COMPONENT_JS attached to this page was a reused
 * Fund Type boilerplate fragment, so column mapping is derived directly
 * from the `Datatable column details` block in PAGE_SECOND_LEVEL_MENU.json
 * + the live `kerisiv2` schema:
 *
 *   No              -> row index
 *   Reference No    -> pdm_reference_no
 *   Date            -> pdm_date
 *   File Name       -> pdm_file_name
 *   Source          -> ptptn_source
 *   Total Student   -> pdm_total_stud
 *   Total Warrant   -> pdm_warrant_amt
 *   Deduction Amount-> pdm_deduction_amt
 *   Balance Amount  -> pdm_balance_amt
 *   Action          -> View (modal) + Delete (gated on isProcessed === 'N')
 *
 * Legacy Action JS:
 *   - View  -> openPage('EDIT', row.mID) (deep-link to an edit page that is
 *              not in scope for this migration wave; replaced here with a
 *              read-only details modal powered by ptptn_data_detl).
 *   - Delete -> listing.delete(row.mID) — disabled when isProcessed != 'N'.
 *               Enforced both client-side (button disabled) and server-side
 *               (controller returns 409 PTPTN_ALREADY_PROCESSED).
 *
 * Component type is a pure datatable (no Form smart/top filter), so the
 * Kitchen Sink "Table" pattern is used for button placement and default
 * exports (PDF, CSV, Excel — rule #8).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  MoreVertical,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { deletePtptnData, getPtptnData, listPtptnData } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { PtptnDataDetail, PtptnDataHeader, PtptnDataRow } from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<PtptnDataRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("pdm_date");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const showDetailsModal = ref(false);
const detailsLoading = ref(false);
const detailsHeader = ref<PtptnDataHeader | null>(null);
const detailsRows = ref<PtptnDataDetail[]>([]);

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

function fmtInt(n: number | null): string {
  if (n === null || Number.isNaN(n)) return "-";
  return new Intl.NumberFormat("en-MY").format(n);
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
  });
  try {
    const res = await listPtptnData(`?${params.toString()}`);
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

async function openDetails(id: number) {
  showDetailsModal.value = true;
  detailsLoading.value = true;
  detailsHeader.value = null;
  detailsRows.value = [];
  try {
    const res = await getPtptnData(id);
    detailsHeader.value = res.data.header;
    detailsRows.value = res.data.details;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load details.");
    showDetailsModal.value = false;
  } finally {
    detailsLoading.value = false;
  }
}

function closeDetails() {
  showDetailsModal.value = false;
  detailsHeader.value = null;
  detailsRows.value = [];
}

async function onDelete(row: PtptnDataRow) {
  if (row.isProcessed !== "N") return;
  const ok = await confirm({
    title: "Delete PTPTN data?",
    message: `Delete PTPTN batch “${row.referenceNo ?? row.mID}” and all its line items? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deletePtptnData(row.mID);
    toast.success("PTPTN data deleted");
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete.");
  }
}

const exportColumns = [
  "Reference No",
  "Date",
  "File Name",
  "Source",
  "Total Student",
  "Total Warrant",
  "Deduction Amount",
  "Balance Amount",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } =
  useDatatableFeatures({
    pageName: "PTPTN Data",
    apiDataPath: "/student-finance/ptptn-data",
    defaultExportColumns: exportColumns,
    getFilteredList: () =>
      rows.value.map((r) => ({
        "Reference No": r.referenceNo ?? "",
        Date: r.date ?? "",
        "File Name": r.fileName ?? "",
        Source: r.source ?? "",
        "Total Student": r.totalStudent ?? 0,
        "Total Warrant": r.totalWarrant ?? 0,
        "Deduction Amount": r.deductAmt ?? 0,
        "Balance Amount": r.balanceAmt ?? 0,
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
    const ws = wb.addWorksheet("PTPTN Data");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.referenceNo ?? "",
        r.date ?? "",
        r.fileName ?? "",
        r.source ?? "",
        r.totalStudent ?? 0,
        r.totalWarrant ?? 0,
        r.deductAmt ?? 0,
        r.balanceAmt ?? 0,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `PTPTN_Data_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

onMounted(() => {
  void loadRows();
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
      <h1 class="page-title">Student Finance / PTPTN Data</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of PTPTN Data</h1>
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_reference_no')"
                    >
                      Reference No
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_date')"
                    >
                      Date
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_file_name')"
                    >
                      File Name
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('ptptn_source')"
                    >
                      Source
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_total_stud')"
                    >
                      Total Student
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_warrant_amt')"
                    >
                      Total Warrant
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_deduction_amt')"
                    >
                      Deduction Amount
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('pdm_balance_amt')"
                    >
                      Balance Amount
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
                    :key="row.mID"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      {{ row.referenceNo ?? "-" }}
                    </td>
                    <td class="px-3 py-2">{{ row.date || "-" }}</td>
                    <td class="px-3 py-2">{{ row.fileName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.source ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtInt(row.totalStudent) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.totalWarrant) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.deductAmt) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.balanceAmt) }}</td>
                    <td class="px-3 py-2">
                      <div class="flex items-center justify-center gap-1">
                        <button
                          type="button"
                          class="rounded p-1 text-sky-600 hover:bg-sky-50"
                          title="View"
                          @click="openDetails(row.mID)"
                        >
                          <Eye class="h-4 w-4" />
                        </button>
                        <button
                          type="button"
                          class="rounded p-1 text-rose-600 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-40"
                          :disabled="row.isProcessed !== 'N'"
                          :title="
                            row.isProcessed !== 'N'
                              ? 'Cannot delete — already processed'
                              : 'Delete'
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
        v-if="showDetailsModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
        @click.self="closeDetails"
      >
        <div
          class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg border border-slate-200 bg-white shadow-2xl"
        >
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">PTPTN Data Details</h3>
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
              Loading details...
            </div>
            <template v-else-if="detailsHeader">
              <dl
                class="grid grid-cols-1 gap-x-6 gap-y-2 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3"
              >
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Reference No</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.referenceNo ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Date</dt>
                  <dd class="font-medium text-slate-900">{{ detailsHeader.date ?? "-" }}</dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Source</dt>
                  <dd class="font-medium text-slate-900">{{ detailsHeader.source ?? "-" }}</dd>
                </div>
                <div class="flex flex-col sm:col-span-2 lg:col-span-3">
                  <dt class="text-xs font-medium uppercase text-slate-500">File Name</dt>
                  <dd class="break-all font-medium text-slate-900">
                    {{ detailsHeader.fileName ?? "-" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Total Student</dt>
                  <dd class="font-medium text-slate-900">
                    {{ fmtInt(detailsHeader.totalStudent) }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Total Warrant</dt>
                  <dd class="font-medium text-slate-900">
                    {{ fmtMoney(detailsHeader.totalWarrant) }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Deduction Amount</dt>
                  <dd class="font-medium text-slate-900">
                    {{ fmtMoney(detailsHeader.deductAmt) }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Balance Amount</dt>
                  <dd class="font-medium text-slate-900">
                    {{ fmtMoney(detailsHeader.balanceAmt) }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Processed</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.isProcessed === "Y" ? "Yes" : "No" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Invoices Generated</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.isInvGenComplete === "Y" ? "Yes" : "No" }}
                  </dd>
                </div>
                <div class="flex flex-col">
                  <dt class="text-xs font-medium uppercase text-slate-500">Exported</dt>
                  <dd class="font-medium text-slate-900">
                    {{ detailsHeader.isExportComplete === "Y" ? "Yes" : "No" }}
                  </dd>
                </div>
              </dl>

              <div class="mt-4">
                <h4 class="mb-2 text-sm font-semibold text-slate-800">
                  Line Items ({{ detailsRows.length }})
                </h4>
                <div class="overflow-x-auto rounded-lg border border-slate-200">
                  <div
                    :class="detailsRows.length > 10 ? 'max-h-[360px] overflow-y-auto' : ''"
                  >
                    <table class="w-full min-w-[1100px] text-xs">
                      <thead class="sticky top-0 bg-slate-50">
                        <tr class="border-b border-slate-200 text-left">
                          <th class="px-3 py-2 font-semibold uppercase">Student Id</th>
                          <th class="px-3 py-2 font-semibold uppercase">Student Name</th>
                          <th class="px-3 py-2 font-semibold uppercase">IC</th>
                          <th class="px-3 py-2 font-semibold uppercase">Warrant No</th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">
                            Warrant Amt
                          </th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">
                            Deduction Amt
                          </th>
                          <th class="px-3 py-2 text-right font-semibold uppercase">
                            Balance Amt
                          </th>
                          <th class="px-3 py-2 font-semibold uppercase">Status</th>
                          <th class="px-3 py-2 font-semibold uppercase">Invoice No</th>
                          <th class="px-3 py-2 font-semibold uppercase">Credit</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-if="detailsRows.length === 0"
                          class="border-b border-slate-100"
                        >
                          <td
                            colspan="10"
                            class="px-3 py-4 text-center text-sm text-slate-500"
                          >
                            No line items.
                          </td>
                        </tr>
                        <tr
                          v-for="line in detailsRows"
                          :key="line.id"
                          class="border-b border-slate-100 hover:bg-slate-50"
                        >
                          <td class="px-3 py-1.5">{{ line.studentId ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.studentName ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.studentIc ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.warrantNo ?? "-" }}</td>
                          <td class="px-3 py-1.5 text-right">
                            {{ fmtMoney(line.warrantAmt) }}
                          </td>
                          <td class="px-3 py-1.5 text-right">
                            {{ fmtMoney(line.deductionAmt) }}
                          </td>
                          <td class="px-3 py-1.5 text-right">
                            {{ fmtMoney(line.balanceAmt) }}
                          </td>
                          <td class="px-3 py-1.5">{{ line.statusPtptn ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.invoiceNo ?? "-" }}</td>
                          <td class="px-3 py-1.5">{{ line.creditStatus ?? "-" }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
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
