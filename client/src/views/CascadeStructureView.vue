<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { createCascadeStructure, getCascadeStructure, getCascadeStructureOptions, listCascadeStructures, updateCascadeStructure } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type { CascadeStructureInput, CascadeStructureRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<CascadeStructureRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const showSmartFilter = ref(false);
const showModal = ref(false);
const editId = ref<number | null>(null);
const smartFilter = ref({ ftyFundTypeSm: "", activitySm: "", ounCodePtj: "", costcenterSm: "", oucStatus: "" });
const form = ref<CascadeStructureInput>({ ftyFundType: "", atActivityCode: "", ounCode: "", ccrCostcentre: "", oucStatus: "ACTIVE" });
const options = ref<{ fund: { id: string; label: string }[]; activity: { id: string; label: string }[]; ptj: { id: string; label: string }[]; costCenter: { id: string; label: string }[]; status: { id: string; label: string }[] }>({ fund: [], activity: [], ptj: [], costCenter: [], status: [] });

async function loadOptions() {
  const res = await getCascadeStructureOptions(form.value.ounCode);
  options.value = res.data.smartFilter;
}
async function loadRows() {
  const params = new URLSearchParams({ page: String(page.value), limit: String(limit.value), ...(q.value ? { q: q.value } : {}), ...(smartFilter.value.ftyFundTypeSm ? { ftyFundTypeSm: smartFilter.value.ftyFundTypeSm } : {}), ...(smartFilter.value.activitySm ? { activitySm: smartFilter.value.activitySm } : {}), ...(smartFilter.value.ounCodePtj ? { ounCodePtj: smartFilter.value.ounCodePtj } : {}), ...(smartFilter.value.costcenterSm ? { costcenterSm: smartFilter.value.costcenterSm } : {}), ...(smartFilter.value.oucStatus ? { oucStatus: smartFilter.value.oucStatus } : {}) });
  const res = await listCascadeStructures(`?${params.toString()}`);
  rows.value = res.data;
}
async function openEdit(id: number) {
  const res = await getCascadeStructure(id);
  editId.value = id;
  form.value = { ftyFundType: res.data.ftyFundType, atActivityCode: res.data.atActivityCode, ounCode: res.data.ounCode, ccrCostcentre: res.data.ccrCostcentre, oucStatus: res.data.oucStatus };
  showModal.value = true;
}
function openCreate() {
  editId.value = null;
  form.value = { ftyFundType: "", atActivityCode: "", ounCode: "", ccrCostcentre: "", oucStatus: "ACTIVE" };
  showModal.value = true;
}
async function saveItem() {
  if (editId.value == null) await createCascadeStructure(form.value); else await updateCascadeStructure(editId.value, form.value);
  showModal.value = false;
  await loadRows();
  toast.success("Saved");
}

watch(() => form.value.ounCode, () => { void loadOptions(); });

let cascadeSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (cascadeSearchDebounce) clearTimeout(cascadeSearchDebounce);
  cascadeSearchDebounce = setTimeout(() => {
    cascadeSearchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

const exportColumns = [
  "Fund",
  "Fund Desc",
  "Activity",
  "Activity Desc",
  "PTJ",
  "PTJ Desc",
  "Cost Center",
  "Cost Center Desc",
  "Status",
];

function toExportRow(r: CascadeStructureRow): Record<string, string | number> {
  return {
    Fund: r.ftyFundType ?? "",
    "Fund Desc": r.ftyFundDesc ?? "",
    Activity: r.atActivityCode ?? "",
    "Activity Desc": r.atActivityDescriptionBm ?? "",
    PTJ: r.ounCode ?? "",
    "PTJ Desc": r.ounDesc ?? "",
    "Cost Center": r.ccrCostcentre ?? "",
    "Cost Center Desc": r.ccrCostcentreDesc ?? "",
    Status: r.oucStatus ?? "",
  };
}

const { handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Cascade Structure",
  apiDataPath: "/setup/cascade-structure",
  defaultExportColumns: exportColumns,
  getFilteredList: () => rows.value.map(toExportRow),
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
    const ws = wb.addWorksheet("Cascade Structure");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      const row = toExportRow(r);
      ws.addRow([idx + 1, ...exportColumns.map((c) => row[c] ?? "")]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Cascade_Structure_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { ftyFundTypeSm: "", activitySm: "", ounCodePtj: "", costcenterSm: "", oucStatus: "" };
}

onMounted(async () => { await loadOptions(); await loadRows(); });
onUnmounted(() => {
  if (cascadeSearchDebounce) clearTimeout(cascadeSearchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <p class="text-base font-semibold text-slate-500">Setup and Maintenance / General Ledger Structure / Cascade Structure</p>
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3"><h1 class="text-base font-semibold text-slate-900">Cascade Structure</h1></div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2"><label class="text-xs font-medium text-slate-600">Display</label><select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="loadRows"><option v-for="n in [5,10,25,50,100]" :key="n" :value="n">{{ n }}</option></select></div>
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
                <button
                  v-if="q"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  @click="q = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" @click="showSmartFilter = true"><Filter class="h-4 w-4" />Filter</button>
            </div>
          </div>
          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1300px] text-sm">
                <thead class="sticky top-0 bg-slate-50"><tr class="border-b border-slate-200 text-left"><th class="px-3 py-2 text-xs font-semibold uppercase">Fund</th><th class="px-3 py-2 text-xs font-semibold uppercase">Fund Desc</th><th class="px-3 py-2 text-xs font-semibold uppercase">Activity</th><th class="px-3 py-2 text-xs font-semibold uppercase">Activity Desc</th><th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th><th class="px-3 py-2 text-xs font-semibold uppercase">PTJ Desc</th><th class="px-3 py-2 text-xs font-semibold uppercase">Cost Center</th><th class="px-3 py-2 text-xs font-semibold uppercase">Cost Center Desc</th><th class="px-3 py-2 text-xs font-semibold uppercase">Status</th><th class="px-3 py-2 text-xs font-semibold uppercase">Action</th></tr></thead>
                <tbody><tr v-for="row in rows" :key="row.oucOunitCostcentreId" class="border-b border-slate-100 hover:bg-slate-50"><td class="px-3 py-2">{{ row.ftyFundType }}</td><td class="px-3 py-2">{{ row.ftyFundDesc }}</td><td class="px-3 py-2">{{ row.atActivityCode }}</td><td class="px-3 py-2">{{ row.atActivityDescriptionBm }}</td><td class="px-3 py-2">{{ row.ounCode }}</td><td class="px-3 py-2">{{ row.ounDesc }}</td><td class="px-3 py-2">{{ row.ccrCostcentre }}</td><td class="px-3 py-2">{{ row.ccrCostcentreDesc }}</td><td class="px-3 py-2">{{ row.oucStatus }}</td><td class="px-3 py-2"><button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openEdit(row.oucOunitCostcentreId)">✎</button></td></tr></tbody>
              </table>
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium hover:bg-slate-50" @click="exportExcel"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800" @click="openCreate"><Plus class="h-3.5 w-3.5" />Add</button>
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
          <div class="max-h-[min(70vh,28rem)] space-y-4 overflow-y-auto p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fund</label>
              <select v-model="smartFilter.ftyFundTypeSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.fund" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Activity</label>
              <select v-model="smartFilter.activitySm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.activity" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PTJ</label>
              <select v-model="smartFilter.ounCodePtj" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.ptj" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Cost Centre</label>
              <select v-model="smartFilter.costcenterSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.costCenter" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.oucStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm" @click="resetSmartFilter">Reset</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="applySmartFilter">OK</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
