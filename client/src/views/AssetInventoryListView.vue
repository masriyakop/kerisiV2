<script setup lang="ts">
/**
 * Asset / List of Asset (PAGEID 1271 / MENUID 1548).
 * Source: FIMS BL `API_ASSET_INVENTORY_LISTOFASSET`. Read-only listing
 * of asset_inventory_main with smart filter + exports.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listAssetInventory } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { AssetInventoryRow } from "@/types";

const toast = useToast();
const currency = new Intl.NumberFormat("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const rows = ref<AssetInventoryRow[]>([]);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref<
  "aim_asset_code" | "aim_asset_type" | "aim_gasset_code" | "aim_category" | "aim_asset_desc" | "aim_serial_no" | "aim_brand_name"
  | "aim_initial_cost" | "aim_install_cost" | "aim_status" | "aim_registered_date" | "aim_acq_date"
>("aim_asset_code");
const sortDir = ref<"asc" | "desc">("asc");
const loading = ref(false);
const footer = ref({ totalRecord: 0, totalInitialCost: 0, totalInstallCost: 0 });
const showSmartFilter = ref(false);
const smartFilter = ref({
  assetCode: "",
  serialNo: "",
  brand: "",
  assetType: "",
  category: "",
  status: "",
  regSource: "",
  acqDateStart: "",
  acqDateTo: "",
  registeredYear: "",
  initialCostMin: "",
  initialCostMax: "",
  ouCode: "",
  costCentre: "",
  fundType: "",
  activityCode: "",
  accountCode: "",
  projectNo: "",
});
const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

function formatDate(s: string | null | undefined): string {
  if (!s) return "-";
  const d = new Date(s);
  if (Number.isNaN(d.getTime())) return s;
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  return `${dd}/${mm}/${d.getFullYear()}`;
}
function toggleSort(col: typeof sortBy.value) {
  if (sortBy.value === col) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = col; sortDir.value = "asc"; }
  void loadRows();
}
async function loadRows() {
  loading.value = true;
  const sf = smartFilter.value;
  const params = new URLSearchParams({
    page: String(page.value), limit: String(limit.value), sort_by: sortBy.value, sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
    ...(sf.assetCode ? { aim_asset_code: sf.assetCode } : {}),
    ...(sf.serialNo ? { aim_serial_no: sf.serialNo } : {}),
    ...(sf.brand ? { aim_brand_name: sf.brand } : {}),
    ...(sf.assetType ? { aim_asset_type: sf.assetType } : {}),
    ...(sf.category ? { aim_category: sf.category } : {}),
    ...(sf.status ? { aim_status: sf.status } : {}),
    ...(sf.regSource ? { aim_reg_source: sf.regSource } : {}),
    ...(sf.acqDateStart ? { aim_acq_date_start: sf.acqDateStart } : {}),
    ...(sf.acqDateTo ? { aim_acq_date_end: sf.acqDateTo } : {}),
    ...(sf.registeredYear ? { aim_registered_date: sf.registeredYear } : {}),
    ...(sf.initialCostMin ? { aim_initial_cost_min: sf.initialCostMin } : {}),
    ...(sf.initialCostMax ? { aim_initial_cost_max: sf.initialCostMax } : {}),
    ...(sf.ouCode ? { oun_code: sf.ouCode } : {}),
    ...(sf.costCentre ? { ccr_costcentre: sf.costCentre } : {}),
    ...(sf.fundType ? { fty_fund_type: sf.fundType } : {}),
    ...(sf.activityCode ? { at_activity_code: sf.activityCode } : {}),
    ...(sf.accountCode ? { acm_acct_code: sf.accountCode } : {}),
    ...(sf.projectNo ? { cpa_project_no: sf.projectNo } : {}),
  });
  try {
    const res = await listAssetInventory(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
    const f = res.meta?.footer as { totalRecord?: number; totalInitialCost?: number; totalInstallCost?: number } | undefined;
    footer.value = { totalRecord: Number(f?.totalRecord ?? 0), totalInitialCost: Number(f?.totalInitialCost ?? 0), totalInstallCost: Number(f?.totalInstallCost ?? 0) };
  } catch (e) { toast.error("Load failed", e instanceof Error ? e.message : "Unable to load assets."); } finally { loading.value = false; }
}
function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }
function applySmartFilter() { page.value = 1; showSmartFilter.value = false; void loadRows(); }
function resetSmartFilter() {
  smartFilter.value = { assetCode: "", serialNo: "", brand: "", assetType: "", category: "", status: "", regSource: "", acqDateStart: "", acqDateTo: "", registeredYear: "", initialCostMin: "", initialCostMax: "", ouCode: "", costCentre: "", fundType: "", activityCode: "", accountCode: "", projectNo: "" };
}
const exportColumns = [
  "Asset Code", "Asset Type", "G-Asset No", "Category", "Item", "Serial No.", "Brand", "PTJ", "CC", "Fund", "Activity", "Acct", "Init.", "Inst.", "GRN", "POR", "Bill", "Vchr", "St.", "Acq.",
];
function asExport(r: AssetInventoryRow): (string | number)[] {
  return [r.assetCode ?? "", r.assetType ?? "", r.gAssetNo ?? "", r.category ?? "", r.item ?? "", r.serialNo ?? "", r.brand ?? "", r.currentPtj ?? "", r.currentCostCentre ?? "", r.fund ?? "", r.activity ?? "", r.accountCode ?? "", r.initialCost != null ? currency.format(r.initialCost) : "", r.installCost != null ? currency.format(r.installCost) : "", r.grnNo ?? "", r.porNo ?? "", r.billNo ?? "", r.voucherNo ?? "", r.status ?? "", formatDate(r.acqDate)];
}
async function exportRows(kind: "pdf" | "csv" | "excel") {
  if (!rows.value.length) { toast.info("No data", "Nothing to export."); return; }
  const name = `Asset_List_${new Date().toISOString().slice(0, 10)}`;
  const body = rows.value.map(asExport);
  if (kind === "csv") {
    const e = (v: string | number) => (/,|\n|"/.test(String(v)) ? `"${String(v).replace(/"/g, '""')}"` : v);
    const c = [["No", ...exportColumns], ...body.map((r, i) => [i + 1, ...r])].map((a) => a.map(e).join(",")).join("\n");
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([c], { type: "text/csv;charset=utf-8" })); a.download = `${name}.csv`; a.click();
    toast.success("CSV downloaded");
  } else if (kind === "excel") {
    const ExcelJS = await import("exceljs"); const wb = new ExcelJS.Workbook(); const w = wb.addWorksheet("Assets");
    w.addRow(["No", ...exportColumns]); body.forEach((r, i) => w.addRow([i + 1, ...r])); const buf = await wb.xlsx.writeBuffer();
    const a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" })); a.download = `${name}.xlsx`; a.click(); URL.revokeObjectURL(a.href);
    toast.success("Excel downloaded");
  } else {
    const { default: jsPDF } = await import("jspdf"); const t = (await import("jspdf-autotable")).default; const d = new jsPDF({ orientation: "landscape" });
    d.text(name, 14, 14); t(d, { head: [["No", ...exportColumns]], body: body.map((r, i) => [i + 1, ...r]), startY: 20, styles: { fontSize: 6 } });
    d.save(`${name}.pdf`); toast.success("PDF downloaded");
  }
}
let t: ReturnType<typeof setTimeout> | null = null;
watch(q, () => { if (t) clearTimeout(t); t = setTimeout(() => { t = null; page.value = 1; void loadRows(); }, 350); });
onMounted(() => { void loadRows(); });
onUnmounted(() => { if (t) clearTimeout(t); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Asset / List of Asset</h1>
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Asset inventory</h1>
          <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="More"><MoreVertical class="h-4 w-4" /></button>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()">
                <option v-for="n in [10, 25, 50, 100, 200]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 p-0.5 text-slate-400" @click="q = ''"><X class="h-3.5 w-3.5" /></button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm hover:bg-slate-50" @click="showSmartFilter = true"><Filter class="h-4 w-4" />Filter</button>
            </div>
          </div>
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[480px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1800px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b text-left text-xs font-semibold uppercase">
                    <th class="px-2 py-2">No</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_asset_code')">Code</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_asset_type')">Type</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_gasset_code')">G-Asset</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_category')">Cat</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_asset_desc')">Item</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_serial_no')">Serial</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_brand_name')">Brand</th>
                    <th class="px-2 py-2">PTJ</th>
                    <th class="px-2 py-2">CC</th>
                    <th class="px-2 py-2">Fund</th>
                    <th class="px-2 py-2">Act</th>
                    <th class="px-2 py-2">Acct</th>
                    <th class="cursor-pointer px-2 py-2 text-right" @click="toggleSort('aim_initial_cost')">Init</th>
                    <th class="cursor-pointer px-2 py-2 text-right" @click="toggleSort('aim_install_cost')">Inst</th>
                    <th class="px-2 py-2">GRN</th>
                    <th class="px-2 py-2">POR</th>
                    <th class="px-2 py-2">Bill</th>
                    <th class="px-2 py-2">Vchr</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_status')">St</th>
                    <th class="cursor-pointer px-2 py-2" @click="toggleSort('aim_acq_date')">Acq</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="21" class="p-6 text-center text-slate-500">Loading…</td></tr>
                  <tr v-else-if="!rows.length"><td colspan="21" class="p-6 text-center text-slate-500">No assets</td></tr>
                  <template v-else>
                    <tr v-for="row in rows" :key="row.assetId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-2 py-1.5">{{ row.index }}</td>
                    <td class="px-2 py-1.5 font-medium">{{ row.assetCode ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.assetType ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.gAssetNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.category ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.item ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.serialNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.brand ?? "—" }}</td>
                    <td class="px-2 py-1.5 max-w-[140px] truncate" :title="row.currentPtj ?? ''">{{ row.currentPtj ?? "—" }}</td>
                    <td class="px-2 py-1.5 max-w-[120px] truncate" :title="row.currentCostCentre ?? ''">{{ row.currentCostCentre ?? "—" }}</td>
                    <td class="px-2 py-1.5 max-w-[100px] truncate">{{ row.fund ?? "—" }}</td>
                    <td class="px-2 py-1.5 max-w-[100px] truncate">{{ row.activity ?? "—" }}</td>
                    <td class="px-2 py-1.5 max-w-[120px] truncate">{{ row.accountCode ?? "—" }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ row.initialCost != null ? currency.format(row.initialCost) : "—" }}</td>
                    <td class="px-2 py-1.5 text-right tabular-nums">{{ row.installCost != null ? currency.format(row.installCost) : "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.grnNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.porNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.billNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.voucherNo ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ row.status ?? "—" }}</td>
                    <td class="px-2 py-1.5">{{ formatDate(row.acqDate) }}</td>
                  </tr>
                  </template>
                </tbody>
                <tfoot v-if="rows.length" class="bg-slate-50 text-xs">
                  <tr class="border-t-2">
                    <td colspan="13" class="p-2 text-right font-semibold">Total ({{ footer.totalRecord }} in filter)</td>
                    <td class="p-2 text-right font-semibold tabular-nums">{{ currency.format(footer.totalInitialCost) }}</td>
                    <td class="p-2 text-right font-semibold tabular-nums">{{ currency.format(footer.totalInstallCost) }}</td>
                    <td colspan="6" />
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
            <p>Showing {{ startIdx }}–{{ endIdx }} of {{ total }}</p>
            <div class="flex items-center gap-2">
              <button type="button" class="rounded border bg-white px-2 py-1" :disabled="page <= 1" @click="prevPage">Prev</button>
              <span class="text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" class="rounded border bg-white px-2 py-1" :disabled="page >= totalPages" @click="nextPage">Next</button>
              <div class="mx-2 h-4 w-px bg-slate-200" />
              <button class="inline-flex items-center gap-1 rounded border bg-white px-2 py-1" @click="exportRows('pdf')"><Download class="h-3.5 w-3.5" />PDF</button>
              <button class="inline-flex items-center gap-1 rounded border bg-white px-2 py-1" @click="exportRows('csv')"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button class="inline-flex items-center gap-1 rounded border bg-white px-2 py-1" @click="exportRows('excel')"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            </div>
          </div>
        </div>
      </article>
      <Teleport to="body">
        <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
          <div class="max-h-[80vh] w-full max-w-3xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b px-4 py-3">
              <h2 class="text-base font-semibold">Filter</h2>
              <button class="p-1 text-slate-500" @click="showSmartFilter = false"><X class="h-4 w-4" /></button>
            </div>
            <div class="grid gap-2 px-4 py-3 sm:grid-cols-2 md:grid-cols-3">
              <div><label class="text-sm">Asset code</label><input v-model="smartFilter.assetCode" class="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm" /></div>
              <div><label class="text-sm">Serial</label><input v-model="smartFilter.serialNo" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Brand</label><input v-model="smartFilter.brand" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Type</label><input v-model="smartFilter.assetType" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Category</label><input v-model="smartFilter.category" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Status</label><input v-model="smartFilter.status" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Reg. source</label><input v-model="smartFilter.regSource" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Acq from (DD/MM/YYYY)</label><input v-model="smartFilter.acqDateStart" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Acq to</label><input v-model="smartFilter.acqDateTo" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Reg. year</label><input v-model="smartFilter.registeredYear" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Init cost min</label><input v-model="smartFilter.initialCostMin" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Init cost max</label><input v-model="smartFilter.initialCostMax" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">PTJ (code)</label><input v-model="smartFilter.ouCode" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Cost centre</label><input v-model="smartFilter.costCentre" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Fund</label><input v-model="smartFilter.fundType" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Activity</label><input v-model="smartFilter.activityCode" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Account</label><input v-model="smartFilter.accountCode" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
              <div><label class="text-sm">Project no</label><input v-model="smartFilter.projectNo" class="mt-1 w-full rounded border px-2 py-1.5" /></div>
            </div>
            <div class="flex justify-end gap-2 border-t px-4 py-3">
              <button type="button" class="rounded border px-3 py-1.5" @click="resetSmartFilter">Reset</button>
              <button type="button" class="rounded bg-slate-900 px-3 py-1.5 text-sm text-white" @click="applySmartFilter">OK</button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
