<script setup lang="ts">
/**
 * Vendor Portal / Online Registration Fee History
 * (PAGEID 1654 / MENUID 2003)
 *
 * Read-only listing of `online_payment` rows for the logged-in vendor
 * (opa_payee_id = auth user's name, matching FIMS' "username === vendor
 * code" convention). An optional `creditor_id` route query parameter
 * overrides the scoping, mirroring legacy `creditorId` URL passing.
 *
 * ## Scope limitations
 * The authoritative BL `NF_BL_VENDOR_ONLINE_PAYMENT` is not present in
 * the available source JSON. The controller and this view were built
 * from the frontend datatable spec + a commented SQL block inside the
 * Tender BL. Two legacy row-level actions are intentionally NOT ported
 * in this wave:
 *
 *   - "Print Receipt" (shown for Successful rows): requires AES-encrypted
 *     `token1`/`token2` column values and a custom PHP receipt renderer.
 *   - "Confirm Not Pending" (shown for Pending rows): marks a payment
 *     failed via encrypted `token2`; depends on external payment-gateway
 *     re-query logic that has no migrated equivalent.
 *
 * Both are flagged in the dev guide as follow-up items pending the BL
 * source and a payment-gateway integration decision.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listPortalRegistrationFees } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { VendorRegistrationFeeRow } from "@/types";

const toast = useToast();
const route = useRoute();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<VendorRegistrationFeeRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("opa_checkout_time");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);
const creditorIdDisplay = ref<string | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function fmtDate(d: string | null): string {
  if (!d) return "-";
  try {
    return new Date(d).toLocaleDateString("en-GB");
  } catch {
    return String(d);
  }
}

function fmtMoney(n: number | null): string {
  if (n === null || Number.isNaN(n)) return "-";
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function statusChipClasses(status: string): string {
  switch (status) {
    case "Successful":
      return "bg-emerald-100 text-emerald-700";
    case "Unsuccessful":
      return "bg-rose-100 text-rose-700";
    case "Pending Authorization":
      return "bg-amber-100 text-amber-700";
    default:
      return "bg-slate-200 text-slate-700";
  }
}

async function loadRows() {
  loading.value = true;
  const overrideCreditor = route.query.creditor_id ?? route.query.creditorId;
  const creditorOverride = typeof overrideCreditor === "string" ? overrideCreditor : "";
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(creditorOverride ? { creditor_id: creditorOverride } : {}),
  });
  try {
    const res = await listPortalRegistrationFees(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const scopedCreditor = res.meta?.creditorId;
    creditorIdDisplay.value = typeof scopedCreditor === "string" ? scopedCreditor : null;
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

const exportColumns = [
  "Checkout Date",
  "Reference No",
  "Receipt No",
  "Creditor No",
  "Vendor Name",
  "Description",
  "Transaction Amount",
  "Status",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Online Registration Fee History",
  apiDataPath: "/portal/vendor/registration-fees",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Checkout Date": fmtDate(r.checkoutDate),
      "Reference No": r.referenceNo ?? "",
      "Receipt No": r.receiptNo ?? "",
      "Creditor No": r.creditorId ?? "",
      "Vendor Name": r.vendorName ?? "",
      Description: r.description ?? "",
      "Transaction Amount": fmtMoney(r.transactionAmount),
      Status: r.statusDesc,
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
    const ws = wb.addWorksheet("Registration Fees");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        fmtDate(r.checkoutDate),
        r.referenceNo ?? "",
        r.receiptNo ?? "",
        r.creditorId ?? "",
        r.vendorName ?? "",
        r.description ?? "",
        fmtMoney(r.transactionAmount),
        r.statusDesc,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Online_Registration_Fee_History_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Vendor Portal / Online Registration Fee History</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">Online Registration Fee History</h1>
            <p v-if="creditorIdDisplay" class="mt-0.5 text-xs text-slate-500">
              Creditor ID: <span class="font-medium text-slate-700">{{ creditorIdDisplay }}</span>
            </p>
          </div>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()">
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
                  @keyup.enter="page = 1; void loadRows()"
                />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" aria-label="Clear search" @click="q = ''">
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
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('opa_checkout_time')">Checkout Date</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('opa_reference_no')">Reference No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Receipt No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('opa_payee_id')">Creditor No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('opa_payee_name')">Vendor Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('opa_transaction_amount')">Amount (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('opa_status')">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="`${row.referenceNo}_${row.index}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ fmtDate(row.checkoutDate) }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.referenceNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.receiptNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.creditorId ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vendorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.transactionAmount) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="statusChipClasses(row.statusDesc)"
                      >
                        {{ row.statusDesc }}
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" />PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" />CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" />Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
