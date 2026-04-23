<script setup lang="ts">
/**
 * Student Finance / Manual Invoice Listing (PAGEID 2389, MENUID 2897)
 *
 * Source: FIMS BL `DT_SF_MANUAL_INV_LISTING`. Datatable scoped to
 * cim_system_id='STUD_INV' AND cim_invoice_type='12' with smart filter
 * (Invoice Date dd/mm/yyyy substring / Debtor Type / Status) and a
 * grand-total footer for the Amount column.
 *
 * The Manual Invoice Form (MENUID 2898) is NOT migrated yet; View/Edit
 * buttons render disabled. Delete is only allowed for DRAFT invoices
 * (same gate server-side and client-side as the legacy dt_js).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  Eye,
  FileDown,
  FileSpreadsheet,
  Filter,
  MoreVertical,
  Pencil,
  Search,
  Trash2,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  deleteManualInvoice,
  getManualInvoiceOptions,
  listManualInvoices,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  ManualInvoiceOptions,
  ManualInvoiceRow,
  ManualInvoiceSmartFilter,
} from "@/types";

const toast = useToast();
const { confirm } = useConfirmDialog();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<ManualInvoiceRow[]>([]);
const loading = ref(false);
const total = ref(0);
const grandTotal = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "cim_invoice_no"
  | "cim_invoice_date"
  | "cim_cust_id"
  | "cim_cust_name"
  | "cim_status"
  | "cim_total_amt";

const sortBy = ref<SortKey>("cim_invoice_date");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref<ManualInvoiceSmartFilter>({
  invoiceDate: "",
  debtorType: "",
  status: "",
});
const options = ref<ManualInvoiceOptions>({ debtorType: [], status: [] });

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function currencyMyr(amount: number): string {
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

async function loadOptions() {
  try {
    const res = await getManualInvoiceOptions();
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
  if (smartFilter.value.invoiceDate)
    params.set("cim_invoice_date", smartFilter.value.invoiceDate);
  if (smartFilter.value.debtorType)
    params.set("cim_cust_type", smartFilter.value.debtorType);
  if (smartFilter.value.status) params.set("cim_status", smartFilter.value.status);

  try {
    const res = await listManualInvoices(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    grandTotal.value = Number(res.meta?.footer?.totalAmt ?? 0);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load manual invoices.",
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
  smartFilter.value = { invoiceDate: "", debtorType: "", status: "" };
}

async function handleDelete(row: ManualInvoiceRow) {
  if (row.status !== "DRAFT") {
    toast.info("Cannot delete", "Only DRAFT invoices can be deleted.");
    return;
  }
  const ok = await confirm({
    title: "Delete manual invoice?",
    message: `Delete invoice ${row.invoiceNo ?? "(no number)"} for ${row.debtorName ?? row.debtorId ?? ""}? This cannot be undone.`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deleteManualInvoice(row.id);
    toast.success("Deleted", `Invoice ${row.invoiceNo ?? ""} removed.`);
    if (rows.value.length === 1 && page.value > 1) page.value -= 1;
    await loadRows();
  } catch (e) {
    toast.error(
      "Delete failed",
      e instanceof Error ? e.message : "Unable to delete manual invoice.",
    );
  }
}

const exportColumns = [
  "Invoice No",
  "Date",
  "Debtor ID",
  "Debtor Name",
  "Debtor Type",
  "Status",
  "Amount",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Manual Invoice Listing",
  apiDataPath: "/student-finance/manual-invoice",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Invoice No": r.invoiceNo ?? "",
      Date: r.invoiceDate ?? "",
      "Debtor ID": r.debtorId ?? "",
      "Debtor Name": r.debtorName ?? "",
      "Debtor Type": r.debtorTypeLabel,
      Status: r.status ?? "",
      Amount: currencyMyr(r.totalAmt),
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
    const ws = wb.addWorksheet("Manual Invoices");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.invoiceNo ?? "",
        r.invoiceDate ?? "",
        r.debtorId ?? "",
        r.debtorName ?? "",
        r.debtorTypeLabel,
        r.status ?? "",
        r.totalAmt,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `ManualInvoiceListing_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
      return "bg-emerald-100 text-emerald-700";
    case "VERIFIED":
    case "ENDORSE":
      return "bg-sky-100 text-sky-700";
    case "ENTRY":
    case "DRAFT":
      return "bg-amber-100 text-amber-700";
    case "REJECT":
    case "CANCEL":
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
    <div class="mx-auto max-w-7xl space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />

      <nav class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-1">
          <li>Student Finance</li>
          <li class="text-slate-300">/</li>
          <li class="font-semibold text-slate-700">Manual Invoice Listing</li>
        </ol>
      </nav>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List Of Manual Invoices</h1>
          <div class="flex items-center gap-2">
            <button
              type="button"
              disabled
              title="Add (Manual Invoice Form not yet migrated)"
              class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400"
            >
              + Add
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
              <table class="w-full min-w-[980px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_invoice_no')"
                    >
                      Invoice No
                      <span v-if="sortBy === 'cim_invoice_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_invoice_date')"
                    >
                      Date
                      <span v-if="sortBy === 'cim_invoice_date'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_cust_id')"
                    >
                      Debtor ID
                      <span v-if="sortBy === 'cim_cust_id'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_cust_name')"
                    >
                      Debtor Name
                      <span v-if="sortBy === 'cim_cust_name'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Debtor Type</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_status')"
                    >
                      Status
                      <span v-if="sortBy === 'cim_status'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('cim_total_amt')"
                    >
                      Amount
                      <span v-if="sortBy === 'cim_total_amt'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="row.id"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      {{ row.invoiceNo ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.invoiceDate ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.debtorId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.debtorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.debtorTypeLabel }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusBadge(row.status)"
                      >
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(row.totalAmt) }}
                    </td>
                    <td class="px-3 py-2">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          disabled
                          title="View (Manual Invoice Form not yet migrated)"
                          class="cursor-not-allowed rounded p-1 text-slate-300"
                        >
                          <Eye class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          disabled
                          title="Edit (Manual Invoice Form not yet migrated)"
                          class="cursor-not-allowed rounded p-1 text-slate-300"
                        >
                          <Pencil class="h-3.5 w-3.5" />
                        </button>
                        <button
                          type="button"
                          :disabled="row.status !== 'DRAFT'"
                          :title="
                            row.status === 'DRAFT'
                              ? 'Delete'
                              : 'Delete disabled: status is ' + (row.status ?? '')
                          "
                          :class="
                            row.status === 'DRAFT'
                              ? 'rounded p-1 text-rose-500 hover:bg-rose-50'
                              : 'cursor-not-allowed rounded p-1 text-slate-300'
                          "
                          @click="handleDelete(row)"
                        >
                          <Trash2 class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0">
                  <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                    <td colspan="7" class="px-3 py-2 text-right text-xs uppercase text-slate-600">
                      Grand Total
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(grandTotal) }}
                    </td>
                    <td></td>
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
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Invoice Date</label>
              <input
                v-model="smartFilter.invoiceDate"
                type="text"
                placeholder="dd/mm/yyyy (partial ok)"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Debtor Type</label>
              <select
                v-model="smartFilter.debtorType"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              >
                <option value="">Any</option>
                <option v-for="opt in options.debtorType" :key="opt.id" :value="opt.id">
                  {{ opt.label }}
                </option>
              </select>
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
