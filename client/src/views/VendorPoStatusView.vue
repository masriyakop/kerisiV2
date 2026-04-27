<script setup lang="ts">
/**
 * Vendor Portal / Purchase Order Status
 * (PAGEID 1664 / MENUID 2015)
 *
 * Source: FIMS BL `NF_BL_VENDOR_PO_STATUS`. Read-only datatable showing
 * the logged-in vendor's purchase orders with per-row GRN aggregation.
 * Mirrors the kitchen-sink "Datatable — smart filter pattern": single
 * search box + Filter modal, paginated results, sortable columns, and
 * default PDF / CSV / Excel exports.
 *
 * Scoping: pom.vcs_vendor_code = auth user's name (FIMS convention —
 * username == vendor code).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listVendorPoStatus } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { VendorPoStatusRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<VendorPoStatusRow[]>([]);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref<"createddate" | "pom_order_no" | "pom_description" | "pom_order_amt" | "pom_order_status" | "pom_available_date">("createddate");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);
const footer = ref({ pomOrderAmt: 0, grmTotalAmt: 0 });

const showSmartFilter = ref(false);
const smartFilter = ref({
  createdDateFrom: "",
  createdDateTo: "",
  pomOrderNo: "",
  pomOrderType: "",
  pomOrderStatus: "",
  pomOrderAmtFrom: "",
  pomOrderAmtTo: "",
  availableDateFrom: "",
  availableDateTo: "",
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const currency = new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function formatDate(s: string | null | undefined): string {
  if (!s) return "-";
  // backend returns ISO-ish; show DD/MM/YYYY for the visible columns.
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s;
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  return `${dd}/${mm}/${d.getFullYear()}`;
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(smartFilter.value.createdDateFrom ? { created_date_from: smartFilter.value.createdDateFrom } : {}),
    ...(smartFilter.value.createdDateTo ? { created_date_to: smartFilter.value.createdDateTo } : {}),
    ...(smartFilter.value.pomOrderNo ? { pom_order_no: smartFilter.value.pomOrderNo } : {}),
    ...(smartFilter.value.pomOrderType ? { pom_order_type: smartFilter.value.pomOrderType } : {}),
    ...(smartFilter.value.pomOrderStatus ? { pom_order_status: smartFilter.value.pomOrderStatus } : {}),
    ...(smartFilter.value.pomOrderAmtFrom ? { pom_order_amt_from: smartFilter.value.pomOrderAmtFrom } : {}),
    ...(smartFilter.value.pomOrderAmtTo ? { pom_order_amt_to: smartFilter.value.pomOrderAmtTo } : {}),
    ...(smartFilter.value.availableDateFrom ? { available_date_from: smartFilter.value.availableDateFrom } : {}),
    ...(smartFilter.value.availableDateTo ? { available_date_to: smartFilter.value.availableDateTo } : {}),
  });
  try {
    const res = await listVendorPoStatus(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const f = res.meta?.footer as { pomOrderAmt?: number; grmTotalAmt?: number } | undefined;
    footer.value = { pomOrderAmt: Number(f?.pomOrderAmt ?? 0), grmTotalAmt: Number(f?.grmTotalAmt ?? 0) };
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load purchase orders.");
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: typeof sortBy.value) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

function applySmartFilter() { page.value = 1; showSmartFilter.value = false; void loadRows(); }
function resetSmartFilter() {
  smartFilter.value = {
    createdDateFrom: "", createdDateTo: "", pomOrderNo: "", pomOrderType: "",
    pomOrderStatus: "", pomOrderAmtFrom: "", pomOrderAmtTo: "",
    availableDateFrom: "", availableDateTo: "",
  };
}

const exportColumns = ["Po Date", "Po Number", "Description", "Amount (RM)", "GRN", "Status", "Available Date"];

function asExportRow(r: VendorPoStatusRow) {
  return {
    "Po Date": formatDate(r.createdDate),
    "Po Number": r.orderNo ?? "",
    Description: r.description ?? "",
    "Amount (RM)": r.orderAmount !== null ? currency.format(r.orderAmount) : "",
    GRN: r.grns.map((g) => `${g.receiveNo} (RM ${g.totalAmount !== null ? currency.format(g.totalAmount) : "-"})`).join(", "),
    Status: r.orderStatus ?? "",
    "Available Date": formatDate(r.availableDate),
  };
}

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Vendor Portal - Purchase Order Status",
  apiDataPath: "/portal/vendor/po-status",
  defaultExportColumns: exportColumns,
  getFilteredList: () => rows.value.map(asExportRow),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    if (rows.value.length === 0) { toast.info("No data", "There is nothing to export."); return; }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Purchase Order");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      const e = asExportRow(r);
      ws.addRow([idx + 1, e["Po Date"], e["Po Number"], e.Description, e["Amount (RM)"], e.GRN, e.Status, e["Available Date"]]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Vendor_PO_Status_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  searchDebounce = setTimeout(() => { searchDebounce = null; page.value = 1; void loadRows(); }, 350);
});

onMounted(() => { void loadRows(); });
onUnmounted(() => { if (searchDebounce) clearTimeout(searchDebounce); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Vendor Portal / Purchase Order Status</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Purchase Order List</h1>
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
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" aria-label="Clear search" @click="q = ''">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50" @click="showSmartFilter = true">
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
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('createddate')">Po Date</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('pom_order_no')">Po Number</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('pom_description')">Description</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('pom_order_amt')">Amount (RM)</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">GRN</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('pom_order_status')">Status</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('pom_available_date')">Available Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No purchase orders found.</td></tr>
                  <tr v-for="row in rows" :key="row.orderId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ formatDate(row.createdDate) }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.orderNo ?? "-" }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.description ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ row.orderAmount !== null ? currency.format(row.orderAmount) : "-" }}</td>
                    <td class="px-3 py-2 text-right">
                      <div v-if="row.grns.length === 0" class="text-slate-400">-</div>
                      <ul v-else class="space-y-0.5">
                        <li v-for="(g, i) in row.grns" :key="i" class="flex justify-between gap-3 text-xs text-slate-600">
                          <span>{{ g.receiveNo }}</span>
                          <span class="tabular-nums">RM {{ g.totalAmount !== null ? currency.format(g.totalAmount) : "-" }}</span>
                        </li>
                      </ul>
                    </td>
                    <td class="px-3 py-2">{{ row.orderStatus ?? "-" }}</td>
                    <td class="px-3 py-2">{{ formatDate(row.availableDate) }}</td>
                  </tr>
                </tbody>
                <tfoot v-if="rows.length > 0" class="bg-slate-50">
                  <tr class="border-t-2 border-slate-300">
                    <td colspan="4" class="px-3 py-2 text-right text-xs font-semibold uppercase text-slate-600">Total (filtered)</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">{{ currency.format(footer.pomOrderAmt) }}</td>
                    <td class="px-3 py-2 text-right text-sm font-semibold tabular-nums">RM {{ currency.format(footer.grmTotalAmt) }}</td>
                    <td colspan="2"></td>
                  </tr>
                </tfoot>
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
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>

      <Teleport to="body">
        <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
          <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <h2 class="text-base font-semibold text-slate-900">Filter Purchase Orders</h2>
              <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" aria-label="Close" @click="showSmartFilter = false"><X class="h-4 w-4" /></button>
            </div>
            <div class="grid gap-3 px-4 py-4 sm:grid-cols-2">
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Po Date From</label><input v-model="smartFilter.createdDateFrom" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Po Date To</label><input v-model="smartFilter.createdDateTo" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">PO Number</label><input v-model="smartFilter.pomOrderNo" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">PO Type</label><input v-model="smartFilter.pomOrderType" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Status</label><input v-model="smartFilter.pomOrderStatus" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Amount From</label><input v-model="smartFilter.pomOrderAmtFrom" type="text" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Amount To</label><input v-model="smartFilter.pomOrderAmtTo" type="text" inputmode="decimal" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Available Date From</label><input v-model="smartFilter.availableDateFrom" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
              <div><label class="mb-1 block text-sm font-medium text-slate-700">Available Date To</label><input v-model="smartFilter.availableDateTo" type="text" placeholder="DD/MM/YYYY" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" /></div>
            </div>
            <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-4 py-3">
              <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
              <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
