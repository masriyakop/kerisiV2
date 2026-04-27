<script setup lang="ts">
/**
 * Account Receivable / Cashbook PTJ (PAGEID 2048, MENUID 1049)
 *
 * Source: FIMS BL `MZ_BL_AR_CASHBOOK_LISTING`. Read-only UNION of offline
 * (offline_receipt_master) + preprinted (preprinted_receipt_stock_master/
 * details) receipts, grouped by staff + counter.
 *
 * The legacy BL scopes by the authenticated staff or their FIMS user-group
 * (UUM_UNIT_TERIMAAN) — not yet modelled here. The optional `staff_id` input
 * lets admins narrow by staff until user-groups are ported.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { listCashbookPtj } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { CashbookPtjRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);

const rows = ref<CashbookPtjRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const staffId = ref("");

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function currencyMyr(amount: number): string {
  return new Intl.NumberFormat("en-MY", {
    style: "currency",
    currency: "MYR",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number.isFinite(amount) ? amount : 0);
}

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    ...(q.value.trim() ? { q: q.value.trim() } : {}),
    ...(staffId.value.trim() ? { staff_id: staffId.value.trim() } : {}),
  });
  try {
    const res = await listCashbookPtj(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load collections.");
  } finally {
    loading.value = false;
  }
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
  "Staff ID",
  "Name",
  "PTJ",
  "Application No",
  "Purpose",
  "Collection Amount",
  "Receipt Type",
];

const {
  templateFileInputRef,
  onTemplateFileChange,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Cashbook PTJ",
  apiDataPath: "/account-receivable/cashbook-ptj",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Staff ID": r.staffId,
      Name: r.staffName ?? "",
      PTJ: r.staffPtj ?? "",
      "Application No": r.applicationNo ?? "",
      Purpose: r.purpose ?? "",
      "Collection Amount": currencyMyr(r.collectionAmount),
      "Receipt Type": r.receiptType,
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter: ref({}),
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
    const ws = wb.addWorksheet("Cashbook PTJ");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.staffId,
        r.staffName ?? "",
        r.staffPtj ?? "",
        r.applicationNo ?? "",
        r.purpose ?? "",
        r.collectionAmount,
        r.receiptType,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Cashbook_PTJ_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

let staffDebounce: ReturnType<typeof setTimeout> | null = null;
watch(staffId, () => {
  if (staffDebounce) clearTimeout(staffDebounce);
  staffDebounce = setTimeout(() => {
    staffDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadRows();
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (staffDebounce) clearTimeout(staffDebounce);
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
      <h1 class="page-title">Account Receivable / Cashbook PTJ</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Collection</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More">
            <MoreVertical class="h-4 w-4" />
          </button>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                @change="page = 1; loadRows()"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>

              <label class="ml-2 text-xs font-medium text-slate-600">Staff ID</label>
              <div class="relative">
                <input
                  v-model="staffId"
                  type="search"
                  placeholder="Filter by staff ID"
                  class="w-40 rounded-lg border border-slate-300 py-1.5 pl-3 pr-8 text-sm"
                />
                <button
                  v-if="staffId"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear staff filter"
                  @click="staffId = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-60 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm"
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
              <table class="w-full min-w-[960px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Staff ID</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Application No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Purpose</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Collection Amount</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Receipt Type</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.staffId}-${row.applicationNo ?? ''}-${row.receiptType}-${row.index}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.staffId }}</td>
                    <td class="px-3 py-2">{{ row.staffName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.staffPtj ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.applicationNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.purpose ?? "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums">{{ currencyMyr(row.collectionAmount) }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                          row.receiptType === 'Preprinted'
                            ? 'bg-indigo-100 text-indigo-700'
                            : 'bg-sky-100 text-sky-700'
                        "
                      >
                        {{ row.receiptType }}
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
  </AdminLayout>
</template>
