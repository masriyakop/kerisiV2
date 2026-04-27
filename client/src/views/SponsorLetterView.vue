<script setup lang="ts">
/**
 * Portal / List of Letter (PAGEID 2330 / MENUID 2823)
 *
 * Source: FIMS BL `IKA_LETTER_LIST_API`. Two read-only datatables shown
 * as tabs:
 *
 *   - Catalog : sponsor-letter types from lookup_details
 *   - History : student's previously generated letters
 *
 * The legacy "Download" action depends on a sponsor-letter PDF
 * generation pipeline that has not been ported to this codebase
 * (see SponsorLetterController doc). The download button surfaces a
 * graceful 501 NOT_IMPLEMENTED response from the API and shows a toast
 * so the SPA contract stays honest.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, FileText, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { downloadSponsorLetter, listSponsorLetterCatalog, listSponsorLetterHistory } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { SponsorLetterCatalogRow, SponsorLetterHistoryRow } from "@/types";

type TabId = "catalog" | "history";
const activeTab = ref<TabId>("catalog");
const toast = useToast();

// Catalog
const catalogRows = ref<SponsorLetterCatalogRow[]>([]);
const catalogTotal = ref(0);
const catalogPage = ref(1);
const catalogLimit = ref(10);
const catalogQ = ref("");
const catalogSortBy = ref<"lde_description" | "lde_value">("lde_description");
const catalogSortDir = ref<"asc" | "desc">("asc");
const catalogLoading = ref(false);
const downloading = ref<string | null>(null);

// History
const historyRows = ref<SponsorLetterHistoryRow[]>([]);
const historyTotal = ref(0);
const historyPage = ref(1);
const historyLimit = ref(10);
const historyQ = ref("");
const historySortBy = ref<"surat" | "ref_no" | "download_date" | "createddate">("createddate");
const historySortDir = ref<"asc" | "desc">("desc");
const historyLoading = ref(false);

const catalogTotalPages = computed(() => (catalogTotal.value ? Math.max(1, Math.ceil(catalogTotal.value / catalogLimit.value)) : 1));
const historyTotalPages = computed(() => (historyTotal.value ? Math.max(1, Math.ceil(historyTotal.value / historyLimit.value)) : 1));

async function loadCatalog() {
  catalogLoading.value = true;
  const params = new URLSearchParams({
    page: String(catalogPage.value), limit: String(catalogLimit.value),
    sort_by: catalogSortBy.value, sort_dir: catalogSortDir.value,
    ...(catalogQ.value ? { q: catalogQ.value } : {}),
  });
  try {
    const res = await listSponsorLetterCatalog(`?${params.toString()}`);
    catalogRows.value = res.data;
    catalogTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load letter catalog.");
  } finally {
    catalogLoading.value = false;
  }
}

async function loadHistory() {
  historyLoading.value = true;
  const params = new URLSearchParams({
    page: String(historyPage.value), limit: String(historyLimit.value),
    sort_by: historySortBy.value, sort_dir: historySortDir.value,
    ...(historyQ.value ? { q: historyQ.value } : {}),
  });
  try {
    const res = await listSponsorLetterHistory(`?${params.toString()}`);
    historyRows.value = res.data;
    historyTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load letter history.");
  } finally {
    historyLoading.value = false;
  }
}

async function onDownload(row: SponsorLetterCatalogRow) {
  downloading.value = row.letterId;
  try {
    const res = await downloadSponsorLetter(row.letterId);
    if (res.data?.reportUrl) {
      window.open(res.data.reportUrl, "_blank", "noopener");
      toast.success("Letter ready", `Opened ${row.letter} in a new tab.`);
      historyPage.value = 1;
      void loadHistory();
    } else {
      toast.info("Letter generation not available", "Sponsor letter PDF service has not been migrated yet.");
    }
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Download failed.";
    toast.info("Letter generation not available", msg);
  } finally {
    downloading.value = null;
  }
}

function toggleSortCatalog(col: typeof catalogSortBy.value) {
  if (catalogSortBy.value === col) catalogSortDir.value = catalogSortDir.value === "asc" ? "desc" : "asc";
  else { catalogSortBy.value = col; catalogSortDir.value = "asc"; }
  void loadCatalog();
}
function toggleSortHistory(col: typeof historySortBy.value) {
  if (historySortBy.value === col) historySortDir.value = historySortDir.value === "asc" ? "desc" : "asc";
  else { historySortBy.value = col; historySortDir.value = "asc"; }
  void loadHistory();
}

function catalogPrev() { if (catalogPage.value > 1) { catalogPage.value -= 1; void loadCatalog(); } }
function catalogNext() { if (catalogPage.value < catalogTotalPages.value) { catalogPage.value += 1; void loadCatalog(); } }
function historyPrev() { if (historyPage.value > 1) { historyPage.value -= 1; void loadHistory(); } }
function historyNext() { if (historyPage.value < historyTotalPages.value) { historyPage.value += 1; void loadHistory(); } }

let catalogDebounce: ReturnType<typeof setTimeout> | null = null;
let historyDebounce: ReturnType<typeof setTimeout> | null = null;

watch(catalogQ, () => {
  if (catalogDebounce) clearTimeout(catalogDebounce);
  catalogDebounce = setTimeout(() => { catalogDebounce = null; catalogPage.value = 1; void loadCatalog(); }, 350);
});
watch(historyQ, () => {
  if (historyDebounce) clearTimeout(historyDebounce);
  historyDebounce = setTimeout(() => { historyDebounce = null; historyPage.value = 1; void loadHistory(); }, 350);
});

async function exportTab(tab: TabId, format: "pdf" | "csv" | "excel") {
  let header: string[] = [];
  let body: (string | number)[][] = [];
  let filename = "";
  if (tab === "catalog") {
    header = ["Letter ID", "Letter Name"];
    body = catalogRows.value.map((r) => [r.letterId, r.letter]);
    filename = `Sponsor_Letter_Catalog_${new Date().toISOString().slice(0, 10)}`;
  } else {
    header = ["Letter ID", "Letter Name", "Reference No.", "Download Date"];
    body = historyRows.value.map((r) => [r.letterId, r.letterName, r.referenceNo ?? "", r.downloadDate ?? ""]);
    filename = `Sponsor_Letter_History_${new Date().toISOString().slice(0, 10)}`;
  }
  if (body.length === 0) { toast.info("No data", "There is nothing to export."); return; }

  if (format === "csv") {
    const escape = (v: string | number) => { const s = String(v); return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s; };
    const csv = [["No", ...header], ...body.map((r, i) => [i + 1, ...r])].map((r) => r.map(escape).join(",")).join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a"); a.href = url; a.download = `${filename}.csv`; a.click(); URL.revokeObjectURL(url);
    toast.success("CSV downloaded");
  } else if (format === "excel") {
    try {
      const ExcelJS = await import("exceljs");
      const wb = new ExcelJS.Workbook();
      const ws = wb.addWorksheet("Sheet1");
      ws.addRow(["No", ...header]);
      body.forEach((r, i) => ws.addRow([i + 1, ...r]));
      const buf = await wb.xlsx.writeBuffer();
      const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a"); a.href = url; a.download = `${filename}.xlsx`; a.click(); URL.revokeObjectURL(url);
      toast.success("Excel downloaded");
    } catch (e) {
      toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
    }
  } else {
    try {
      const { default: jsPDF } = await import("jspdf");
      const autoTable = (await import("jspdf-autotable")).default;
      const doc = new jsPDF({ orientation: "landscape" });
      doc.text(filename, 14, 14);
      autoTable(doc, { head: [["No", ...header]], body: body.map((r, i) => [i + 1, ...r]), startY: 20, styles: { fontSize: 9 } });
      doc.save(`${filename}.pdf`);
      toast.success("PDF downloaded");
    } catch (e) {
      toast.error("Export failed", e instanceof Error ? e.message : "PDF export failed.");
    }
  }
}

onMounted(() => {
  void loadCatalog();
  void loadHistory();
});
onUnmounted(() => {
  if (catalogDebounce) clearTimeout(catalogDebounce);
  if (historyDebounce) clearTimeout(historyDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Portal / List of Letter</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div class="flex items-center gap-2">
            <button type="button" :class="['rounded-lg px-3 py-1.5 text-sm font-medium', activeTab === 'catalog' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50']" @click="activeTab = 'catalog'">Letter Catalog</button>
            <button type="button" :class="['rounded-lg px-3 py-1.5 text-sm font-medium', activeTab === 'history' ? 'bg-slate-900 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50']" @click="activeTab = 'history'">Download History</button>
          </div>
        </div>

        <!-- Catalog -->
        <div v-show="activeTab === 'catalog'" class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="catalogLimit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="catalogPage = 1; loadCatalog()">
                <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="catalogQ" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="catalogPage = 1; void loadCatalog()" />
              <button v-if="catalogQ" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="catalogQ = ''"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="catalogRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortCatalog('lde_value')">Letter ID</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortCatalog('lde_description')">Letter Name</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="catalogLoading"><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="catalogRows.length === 0"><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">No letters available.</td></tr>
                  <tr v-for="row in catalogRows" :key="`c-${row.ldeId}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.letterId }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.letter }}</td>
                    <td class="px-3 py-2 text-right">
                      <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-50" :disabled="downloading === row.letterId" @click="onDownload(row)">
                        <FileText class="h-3.5 w-3.5" />
                        {{ downloading === row.letterId ? "Generating..." : "Generate" }}
                      </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">{{ catalogTotal === 0 ? "No rows" : `Page ${catalogPage} / ${catalogTotalPages} (${catalogTotal})` }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="catalogPage <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="catalogPrev">Prev</button>
              <button type="button" :disabled="catalogPage >= catalogTotalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="catalogNext">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('catalog', 'pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('catalog', 'csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('catalog', 'excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>

        <!-- History -->
        <div v-show="activeTab === 'history'" class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="historyLimit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="historyPage = 1; loadHistory()">
                <option v-for="n in [5, 10, 25, 50]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="relative">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
              <input v-model="historyQ" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="historyPage = 1; void loadHistory()" />
              <button v-if="historyQ" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="historyQ = ''"><X class="h-3.5 w-3.5" /></button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="historyRows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortHistory('surat')">Letter Name</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortHistory('ref_no')">Reference No.</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSortHistory('download_date')">Download Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="historyLoading"><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="historyRows.length === 0"><td colspan="4" class="px-3 py-6 text-center text-sm text-slate-500">No letters downloaded yet.</td></tr>
                  <tr v-for="row in historyRows" :key="`h-${row.lvsId}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 text-slate-700">{{ row.letterName }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.referenceNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.downloadDate ?? "-" }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">{{ historyTotal === 0 ? "No rows" : `Page ${historyPage} / ${historyTotalPages} (${historyTotal})` }}</div>
            <div class="flex items-center gap-2">
              <button type="button" :disabled="historyPage <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="historyPrev">Prev</button>
              <button type="button" :disabled="historyPage >= historyTotalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="historyNext">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('history', 'pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('history', 'csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportTab('history', 'excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
