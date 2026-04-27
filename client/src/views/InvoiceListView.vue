<script setup lang="ts">
/**
 * Student Finance / Invoice (PAGEID 828, MENUID 1023)
 *
 * Source: FIMS BLs `DT_SF_INVOICE` (main listing) + `DT_DEBIT_LIST`
 * (per-invoice debit detail drilldown).
 *
 * Listing: cust_invoice_master scoped to cim_cust_type IN ('A','E')
 * AND (cim_system_id IS NULL OR IN ('STUD_INV','SF_SPON_INV')) AND
 * the invoice has at least one DT cust_invoice_details line.
 *
 * Smart filter mirrors the legacy 14-field form. Two notes vs the
 * legacy contract:
 *   - "Entered By" was a no-op in the legacy BL (the SQL never
 *     filtered on it). We omit the field rather than ship a control
 *     that does nothing.
 *   - Legacy "Customer Name" + "Sponsor Code" both filtered the same
 *     `cim_cust_id` column (one came from a name->id autosuggest, the
 *     other from a sponsor-code autosuggest). The autosuggest BLs are
 *     not migrated, so we collapse the two fields into a single
 *     "Customer / Sponsor ID" text input that filters cim_cust_id
 *     exactly. Server still accepts the legacy keys.
 *
 * Read-only migration:
 *   - View / Edit / Delete buttons render disabled — the legacy
 *     Invoice Form (menuID 1062) and the per-invoice cancel SP
 *     (`invoice_cancel`) are NOT migrated yet.
 *   - The cog ("Detail List") opens an inline drilldown that fetches
 *     `/api/student-finance/invoice/{id}/details` (read-only).
 *   - Bulk select-all PDF download (legacy `MY_SF_DOWNLOAD_INVOICE`)
 *     is not surfaced on this page; CSV / PDF / Excel exports cover
 *     the rendered page set instead.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  ChevronDown,
  ChevronRight,
  Cog,
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
  getInvoiceDetails,
  getInvoiceOptions,
  listInvoices,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  InvoiceDebitFooter,
  InvoiceDebitRow,
  InvoiceOptions,
  InvoiceRow,
  InvoiceSmartFilter,
} from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<InvoiceRow[]>([]);
const loading = ref(false);
const total = ref(0);
const grandTotal = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "InvoiceNo"
  | "InvoiceDate"
  | "cim_status"
  | "CustomerId"
  | "CustomerName"
  | "CustomerType"
  | "semester"
  | "cim_batch_no"
  | "feeCode"
  | "studStatus"
  | "Amt"
  | "Balance";

const sortBy = ref<SortKey>("InvoiceDate");
const sortDir = ref<"asc" | "desc">("desc");

const showSmartFilter = ref(false);
const smartFilter = ref<InvoiceSmartFilter>({
  status: "",
  invoiceNo: "",
  feeCategory: "",
  semester: "",
  ptj: "",
  programLevel: "",
  studentStatus: "",
  studyCategory: "",
  citizenship: "",
  nationality: "",
  customerType: "",
  customerId: "",
});
const options = ref<InvoiceOptions>({
  status: [],
  feeCategory: [],
  programLevel: [],
  studentStatus: [],
  studyCategory: [],
  citizenship: [],
  nationality: [],
  customerType: [],
});

// Inline drilldown state — one expanded invoice at a time keeps the
// UI predictable on dense pages.
const expandedId = ref<number | null>(null);
const detailsLoading = ref(false);
const detailsRows = ref<InvoiceDebitRow[]>([]);
const detailsFooter = ref<InvoiceDebitFooter>({
  amt: 0,
  cnAmt: 0,
  dbAmt: 0,
  dcAmt: 0,
  totalAmt: 0,
  balAmt: 0,
});

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function currencyMyr(amount: number | null): string {
  if (amount === null || !Number.isFinite(amount)) return "-";
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

async function loadOptions() {
  try {
    const res = await getInvoiceOptions();
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
  if (smartFilter.value.status)
    params.set("sddInvoiceStatus", smartFilter.value.status);
  if (smartFilter.value.invoiceNo)
    params.set("cim_invoice_no", smartFilter.value.invoiceNo);
  if (smartFilter.value.feeCategory)
    params.set("sddFeeCategory", smartFilter.value.feeCategory);
  if (smartFilter.value.semester)
    params.set("sddSemester", smartFilter.value.semester);
  if (smartFilter.value.ptj) params.set("sddPtj", smartFilter.value.ptj);
  if (smartFilter.value.programLevel)
    params.set("sddProgramLevel", smartFilter.value.programLevel);
  if (smartFilter.value.studentStatus)
    params.set("sddStudentStatus", smartFilter.value.studentStatus);
  if (smartFilter.value.studyCategory)
    params.set("sddStudyCategory", smartFilter.value.studyCategory);
  if (smartFilter.value.citizenship)
    params.set("sddCitizenship", smartFilter.value.citizenship);
  if (smartFilter.value.nationality)
    params.set("sddNationality", smartFilter.value.nationality);
  if (smartFilter.value.customerType)
    params.set("sddCustomerType", smartFilter.value.customerType);
  if (smartFilter.value.customerId)
    params.set("cim_cust_id", smartFilter.value.customerId);

  try {
    const res = await listInvoices(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    grandTotal.value = Number(res.meta?.footer?.totalAmt ?? 0);
    expandedId.value = null;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load invoices.",
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
    status: "",
    invoiceNo: "",
    feeCategory: "",
    semester: "",
    ptj: "",
    programLevel: "",
    studentStatus: "",
    studyCategory: "",
    citizenship: "",
    nationality: "",
    customerType: "",
    customerId: "",
  };
}

async function toggleDetails(row: InvoiceRow) {
  if (expandedId.value === row.id) {
    expandedId.value = null;
    return;
  }
  expandedId.value = row.id;
  detailsRows.value = [];
  detailsFooter.value = {
    amt: 0,
    cnAmt: 0,
    dbAmt: 0,
    dcAmt: 0,
    totalAmt: 0,
    balAmt: 0,
  };
  detailsLoading.value = true;
  try {
    const res = await getInvoiceDetails(row.id);
    detailsRows.value = res.data.rows;
    detailsFooter.value = res.data.footer;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load invoice details.",
    );
    expandedId.value = null;
  } finally {
    detailsLoading.value = false;
  }
}

const exportColumns = [
  "Invoice No",
  "Invoice Date",
  "Status",
  "Customer ID",
  "Customer Name",
  "Customer Type",
  "Semester",
  "Batch No",
  "Fee Structure",
  "Student Status",
  "Amount",
  "Balance",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Invoice Listing",
  apiDataPath: "/student-finance/invoice",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Invoice No": r.invoiceNo ?? "",
      "Invoice Date": r.invoiceDate ?? "",
      Status: r.status ?? "",
      "Customer ID": r.customerId ?? "",
      "Customer Name": r.customerName ?? "",
      "Customer Type": r.customerTypeLabel,
      Semester: r.semester ?? "",
      "Batch No": r.batchNo ?? "",
      "Fee Structure": r.feeCode ?? "",
      "Student Status": r.studentStatus ?? "",
      Amount: currencyMyr(r.amount),
      Balance: currencyMyr(r.balance),
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
    const ws = wb.addWorksheet("Invoices");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.invoiceNo ?? "",
        r.invoiceDate ?? "",
        r.status ?? "",
        r.customerId ?? "",
        r.customerName ?? "",
        r.customerTypeLabel,
        r.semester ?? "",
        r.batchNo ?? "",
        r.feeCode ?? "",
        r.studentStatus ?? "",
        r.amount,
        r.balance,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `InvoiceListing_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    case "ENTRY":
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
    <div class="space-y-4">
      <input
        ref="templateFileInputRef"
        type="file"
        accept=".json,application/json"
        class="hidden"
        @change="onTemplateFileChange"
      />

      <h1 class="page-title">Student Finance / Invoice</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List Of Invoice</h1>
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
            <div :class="rows.length > 10 ? 'max-h-[480px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="w-10 px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('InvoiceNo')"
                    >
                      Invoice No
                      <span v-if="sortBy === 'InvoiceNo'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('InvoiceDate')"
                    >
                      Invoice Date
                      <span v-if="sortBy === 'InvoiceDate'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
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
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('CustomerId')"
                    >
                      Customer ID
                      <span v-if="sortBy === 'CustomerId'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('CustomerName')"
                    >
                      Customer Name
                      <span v-if="sortBy === 'CustomerName'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('CustomerType')"
                    >
                      Type
                      <span v-if="sortBy === 'CustomerType'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('semester')"
                    >
                      Semester
                      <span v-if="sortBy === 'semester'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('cim_batch_no')"
                    >
                      Batch No
                      <span v-if="sortBy === 'cim_batch_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('feeCode')"
                    >
                      Fee Structure
                      <span v-if="sortBy === 'feeCode'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('studStatus')"
                    >
                      Student Status
                      <span v-if="sortBy === 'studStatus'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('Amt')"
                    >
                      Amount
                      <span v-if="sortBy === 'Amt'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-right text-xs font-semibold uppercase"
                      @click="toggleSort('Balance')"
                    >
                      Balance
                      <span v-if="sortBy === 'Balance'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="14" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="14" class="px-3 py-6 text-center text-sm text-slate-500">
                      No records found.
                    </td>
                  </tr>
                  <template v-for="row in rows" :key="row.id">
                    <tr class="border-b border-slate-100 hover:bg-slate-50">
                      <td class="px-3 py-2">{{ row.index }}</td>
                      <td class="px-3 py-2 font-medium text-slate-900">
                        {{ row.invoiceNo ?? "-" }}
                      </td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.invoiceDate ?? "-" }}</td>
                      <td class="px-3 py-2">
                        <span
                          class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                          :class="statusBadge(row.status)"
                        >
                          {{ row.statusLabel ?? row.status ?? "-" }}
                        </span>
                      </td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.customerId ?? "-" }}</td>
                      <td class="px-3 py-2">{{ row.customerName ?? "-" }}</td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.customerTypeLabel }}</td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.semester ?? "-" }}</td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.batchNo ?? "-" }}</td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.feeCode ?? "-" }}</td>
                      <td class="whitespace-nowrap px-3 py-2">{{ row.studentStatus ?? "-" }}</td>
                      <td class="px-3 py-2 text-right tabular-nums">
                        {{ currencyMyr(row.amount) }}
                      </td>
                      <td class="px-3 py-2 text-right tabular-nums">
                        {{ currencyMyr(row.balance) }}
                      </td>
                      <td class="px-3 py-2">
                        <div class="flex items-center gap-1">
                          <button
                            type="button"
                            disabled
                            title="View (Invoice Form not yet migrated)"
                            class="cursor-not-allowed rounded p-1 text-slate-300"
                          >
                            <Eye class="h-3.5 w-3.5" />
                          </button>
                          <button
                            type="button"
                            disabled
                            title="Edit (Invoice Form not yet migrated)"
                            class="cursor-not-allowed rounded p-1 text-slate-300"
                          >
                            <Pencil class="h-3.5 w-3.5" />
                          </button>
                          <button
                            type="button"
                            :title="
                              expandedId === row.id ? 'Hide details' : 'Detail List'
                            "
                            class="rounded p-1 text-slate-500 hover:bg-slate-100"
                            @click="toggleDetails(row)"
                          >
                            <ChevronDown
                              v-if="expandedId === row.id"
                              class="h-3.5 w-3.5"
                            />
                            <Cog v-else class="h-3.5 w-3.5" />
                          </button>
                          <button
                            type="button"
                            disabled
                            title="Cancel invoice (cancel SP not yet migrated)"
                            class="cursor-not-allowed rounded p-1 text-slate-300"
                          >
                            <Trash2 class="h-3.5 w-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="expandedId === row.id" class="bg-slate-50/60">
                      <td colspan="14" class="px-3 pb-3 pt-1">
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                          <div class="mb-2 flex items-center gap-2 text-xs text-slate-600">
                            <ChevronRight class="h-3.5 w-3.5" />
                            <span class="font-semibold">
                              Debit details — Invoice {{ row.invoiceNo ?? "(no number)" }}
                            </span>
                          </div>
                          <div class="overflow-x-auto rounded-md border border-slate-200">
                            <table class="w-full min-w-[1100px] text-xs">
                              <thead class="bg-slate-100">
                                <tr class="border-b border-slate-200 text-left">
                                  <th class="px-2 py-1.5 font-semibold uppercase">No</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Item</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Sub Item</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Fund</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Activity</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">PTJ</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">
                                    Costcentre
                                  </th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Code SO</th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">
                                    Account Code
                                  </th>
                                  <th class="px-2 py-1.5 font-semibold uppercase">Tax Code</th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    Tax Amt
                                  </th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    Amount
                                  </th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    CN
                                  </th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    DN
                                  </th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    DC
                                  </th>
                                  <th class="px-2 py-1.5 text-right font-semibold uppercase">
                                    Paid
                                  </th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr v-if="detailsLoading">
                                  <td
                                    colspan="16"
                                    class="px-2 py-3 text-center text-slate-500"
                                  >
                                    Loading details...
                                  </td>
                                </tr>
                                <tr v-else-if="detailsRows.length === 0">
                                  <td
                                    colspan="16"
                                    class="px-2 py-3 text-center text-slate-500"
                                  >
                                    No detail rows.
                                  </td>
                                </tr>
                                <tr
                                  v-for="dr in detailsRows"
                                  :key="dr.id"
                                  class="border-b border-slate-100"
                                >
                                  <td class="px-2 py-1.5">{{ dr.index }}</td>
                                  <td class="px-2 py-1.5">{{ dr.item ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.subItem ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.fundType ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.activityCode ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.ptjCode ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.costcentre ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.codeSO ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.acctCode ?? "-" }}</td>
                                  <td class="px-2 py-1.5">{{ dr.taxCode ?? "-" }}</td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.taxAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.amt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.cnAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.dbAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.dcAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(dr.totalAmt) }}
                                  </td>
                                </tr>
                              </tbody>
                              <tfoot v-if="!detailsLoading && detailsRows.length > 0">
                                <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                                  <td
                                    colspan="11"
                                    class="px-2 py-1.5 text-right uppercase text-slate-600"
                                  >
                                    Total
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(detailsFooter.amt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(detailsFooter.cnAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(detailsFooter.dbAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(detailsFooter.dcAmt) }}
                                  </td>
                                  <td class="px-2 py-1.5 text-right tabular-nums">
                                    {{ currencyMyr(detailsFooter.totalAmt) }}
                                  </td>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
                <tfoot v-if="rows.length > 0">
                  <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                    <td colspan="11" class="px-3 py-2 text-right text-xs uppercase text-slate-600">
                      Grand Total
                    </td>
                    <td class="px-3 py-2 text-right tabular-nums">
                      {{ currencyMyr(grandTotal) }}
                    </td>
                    <td colspan="2"></td>
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
        <div class="w-full max-w-3xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Invoice Status
                </label>
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
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Invoice No
                </label>
                <input
                  v-model="smartFilter.invoiceNo"
                  type="text"
                  placeholder="partial ok"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Fee Category
                </label>
                <select
                  v-model="smartFilter.feeCategory"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.feeCategory"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Semester</label>
                <input
                  v-model="smartFilter.semester"
                  type="text"
                  placeholder="e.g. A211"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">PTJ Code</label>
                <input
                  v-model="smartFilter.ptj"
                  type="text"
                  placeholder="exact match"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Program Level
                </label>
                <select
                  v-model="smartFilter.programLevel"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.programLevel"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Student Status
                </label>
                <select
                  v-model="smartFilter.studentStatus"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.studentStatus"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Study Category
                </label>
                <select
                  v-model="smartFilter.studyCategory"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.studyCategory"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Citizenship
                </label>
                <select
                  v-model="smartFilter.citizenship"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.citizenship"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Nationality
                </label>
                <select
                  v-model="smartFilter.nationality"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.nationality"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Customer Type
                </label>
                <select
                  v-model="smartFilter.customerType"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option
                    v-for="opt in options.customerType"
                    :key="opt.id"
                    :value="opt.id"
                  >
                    {{ opt.label }}
                  </option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                  Customer / Sponsor ID
                </label>
                <input
                  v-model="smartFilter.customerId"
                  type="text"
                  placeholder="exact cim_cust_id"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
              </div>
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
