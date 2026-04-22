<script setup lang="ts">
/**
 * Vendor Portal / Tender/Quotation List
 * (PAGEID 2278 / MENUID 2767)
 *
 * Source: FIMS BL `NF_BL_PURCHASING_VENDOR_PORTAL_TENDER`. Read-only
 * listing of APPROVE tenders whose briefing-close date is still in the
 * future, scoped globally (not per-vendor). Each row carries an
 * `editable` flag (NOW between tender_open_start and tender_open_close)
 * which legacy used to gate the Buy Document action.
 *
 * A vendor status pre-check banner is shown when the logged-in vendor is
 * inactive ('0') or blacklisted — legacy `?check=1` semantics. The Buy
 * Document action itself deep-linked to MENUID 2769, which is not part
 * of the current Portal migration wave, so only the editable indicator
 * is surfaced here.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { AlertTriangle, Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { checkPortalVendorStatus, listPortalTenders } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { TenderQuotationRow, VendorStatusCheck } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<TenderQuotationRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("tdm_briefing_close_peti");
const sortDir = ref<"asc" | "desc">("asc");
const loading = ref(false);

const vendorCheck = ref<VendorStatusCheck>({
  vendorCode: null,
  restrictedStatus: null,
  canBuyDocument: true,
});

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

function fmtDateTime(d: string | null): string {
  if (!d) return "-";
  try {
    return new Date(d).toLocaleString("en-GB");
  } catch {
    return String(d);
  }
}

function fmtMoney(n: number | null): string {
  if (n === null || Number.isNaN(n)) return "-";
  return new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

async function loadVendorCheck() {
  try {
    const res = await checkPortalVendorStatus();
    vendorCheck.value = res.data;
  } catch {
    // Non-fatal — the banner stays hidden if the check fails.
    vendorCheck.value = { vendorCode: null, restrictedStatus: null, canBuyDocument: true };
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
  });
  try {
    const res = await listPortalTenders(`?${params.toString()}`);
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

const exportColumns = [
  "Tender No",
  "Briefing Ref No",
  "Type",
  "Title",
  "Start Date",
  "End Date",
  "Estimated Amount",
  "Amount Doc",
  "Status",
  "Briefing Close",
  "Tender Open Start",
  "Tender Open Close",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Tender List",
  apiDataPath: "/portal/vendor/tenders",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Tender No": r.tenderNo ?? "",
      "Briefing Ref No": r.briefingRefNo ?? "",
      Type: r.tenderType ?? "",
      Title: r.title ?? "",
      "Start Date": fmtDate(r.startDate),
      "End Date": fmtDate(r.endDate),
      "Estimated Amount": fmtMoney(r.estimatedAmount),
      "Amount Doc": fmtMoney(r.amountDoc),
      Status: r.status ?? "",
      "Briefing Close": fmtDate(r.briefingCloseDate),
      "Tender Open Start": fmtDateTime(r.tenderOpenStart),
      "Tender Open Close": fmtDateTime(r.tenderOpenClose),
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
    const ws = wb.addWorksheet("Tenders");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.tenderNo ?? "",
        r.briefingRefNo ?? "",
        r.tenderType ?? "",
        r.title ?? "",
        fmtDate(r.startDate),
        fmtDate(r.endDate),
        fmtMoney(r.estimatedAmount),
        fmtMoney(r.amountDoc),
        r.status ?? "",
        fmtDate(r.briefingCloseDate),
        fmtDateTime(r.tenderOpenStart),
        fmtDateTime(r.tenderOpenClose),
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Tender_List_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  await loadVendorCheck();
  await loadRows();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <p class="text-base font-semibold text-slate-500">Vendor Portal / Tender/Quotation List</p>

      <div
        v-if="!vendorCheck.canBuyDocument && vendorCheck.restrictedStatus"
        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
        <div>
          <p class="font-medium">Account restriction</p>
          <p class="text-xs">
            Your vendor account ({{ vendorCheck.vendorCode }}) is currently
            <strong>{{ vendorCheck.restrictedStatus === "BLACKLIST" ? "BLACKLISTED" : "INACTIVE" }}</strong>.
            You may view the tender list, but the Buy Document action is disabled.
          </p>
        </div>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List Of Tender / Quotation</h1>
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
              <table class="w-full min-w-[1600px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_tender_no')">Tender No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Briefing Ref No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_tender_type')">Type</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_title')">Title</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_start_date')">Start Date</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_end_date')">End Date</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('tdm_estimated_amount')">Estimated Amount (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-right text-xs font-semibold uppercase" @click="toggleSort('tdm_amount_doc')">Doc Price (RM)</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_status')">Status</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_briefing_close_peti')">Briefing Close</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_tender_open_start')">Open Start</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('tdm_tender_open_close')">Open Close</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Buy Doc</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="14" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="14" class="px-3 py-6 text-center text-sm text-slate-500">No open tenders found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.tenderId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.tenderNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.briefingRefNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.tenderType ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.title ?? "-" }}</td>
                    <td class="px-3 py-2">{{ fmtDate(row.startDate) }}</td>
                    <td class="px-3 py-2">{{ fmtDate(row.endDate) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.estimatedAmount) }}</td>
                    <td class="px-3 py-2 text-right">{{ fmtMoney(row.amountDoc) }}</td>
                    <td class="px-3 py-2">
                      <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                        {{ row.status ?? "-" }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ fmtDate(row.briefingCloseDate) }}</td>
                    <td class="px-3 py-2">{{ fmtDateTime(row.tenderOpenStart) }}</td>
                    <td class="px-3 py-2">{{ fmtDateTime(row.tenderOpenClose) }}</td>
                    <td class="px-3 py-2">
                      <span
                        v-if="row.editable && vendorCheck.canBuyDocument"
                        class="inline-flex items-center rounded-full bg-sky-100 px-2 py-0.5 text-xs font-medium text-sky-700"
                      >
                        Open
                      </span>
                      <span
                        v-else
                        class="inline-flex items-center rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600"
                      >
                        Closed
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
