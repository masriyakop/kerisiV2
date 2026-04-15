<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { createCostCentre, getCostCentre, getCostCentreOptions, listCostCentres, updateCostCentre } from "@/api/cms";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { useToast } from "@/composables/useToast";
import type { CostCentreInput, CostCentreRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<CostCentreRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const showSmartFilter = ref(false);
const showModal = ref(false);
const editId = ref<number | null>(null);
const smartFilter = ref({ ccrCostcentre: "", ptjCodeSm: "", statusSm: "" });
const options = ref<{ costCentre: { id: string; label: string }[]; ptjCode: { id: string; label: string }[]; status: { id: string; label: string }[] }>({ costCentre: [], ptjCode: [], status: [] });
const popup = ref<{ ptjCode: { id: string; label: string }[]; status: { id: string; label: string }[]; flagSalary: { id: string; label: string }[] }>({ ptjCode: [], status: [], flagSalary: [] });
const form = ref<CostCentreInput>({ ccrCostcentre: "", ccrCostcentreDesc: "", ccrCostcentreDescEng: "", ounCode: "", ccrAddress: "", ccrHostelCode: "", ccrStatus: "ACTIVE", ccrFlagSalary: "N" });

async function loadOptions() {
  const res = await getCostCentreOptions();
  options.value = res.data.smartFilter;
  popup.value = res.data.popupModal;
}
async function loadRows() {
  const params = new URLSearchParams({ page: String(page.value), limit: String(limit.value), ...(q.value ? { q: q.value } : {}), ...(smartFilter.value.ccrCostcentre ? { ccrCostcentre: smartFilter.value.ccrCostcentre } : {}), ...(smartFilter.value.ptjCodeSm ? { ptjCodeSm: smartFilter.value.ptjCodeSm } : {}), ...(smartFilter.value.statusSm ? { statusSm: smartFilter.value.statusSm } : {}) });
  const res = await listCostCentres(`?${params.toString()}`);
  rows.value = res.data;
  total.value = Number(res.meta?.total ?? 0);
}
async function openEdit(id: number) {
  const res = await getCostCentre(id);
  editId.value = id;
  form.value = { ccrCostcentre: res.data.ccrCostcentre, ccrCostcentreDesc: res.data.ccrCostcentreDesc, ccrCostcentreDescEng: res.data.ccrCostcentreDescEng, ounCode: res.data.ounCode, ccrAddress: res.data.ccrAddress, ccrHostelCode: res.data.ccrHostelCode, ccrStatus: res.data.ccrStatus, ccrFlagSalary: (res.data.ccrFlagSalary ?? "N") as "Y" | "N" };
  showModal.value = true;
}
function openCreate() {
  editId.value = null;
  form.value = { ccrCostcentre: "", ccrCostcentreDesc: "", ccrCostcentreDescEng: "", ounCode: "", ccrAddress: "", ccrHostelCode: "", ccrStatus: "ACTIVE", ccrFlagSalary: "N" };
  showModal.value = true;
}
async function saveItem() {
  if (editId.value == null) await createCostCentre(form.value); else await updateCostCentre(editId.value, form.value);
  showModal.value = false;
  await loadRows();
  toast.success("Saved");
}
const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Cost Centre",
  apiDataPath: "/setup/cost-centre",
  defaultExportColumns: ["Code", "Description (Malay)", "PTJ", "Status"],
  getFilteredList: () => rows.value.map((r) => ({ Code: r.ccrCostcentre, "Description (Malay)": r.ccrCostcentreDesc, PTJ: r.ounCode, Status: r.ccrStatus })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { ccrCostcentre: "", ptjCodeSm: "", statusSm: "" };
}

onMounted(async () => { await loadOptions(); await loadRows(); });
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <p class="text-base font-semibold text-slate-500">Setup and Maintenance / General Ledger Structure / Cost Centre</p>
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Cost Centre</h1>
          <div class="relative">
            <button class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><MoreVertical class="h-4 w-4" /></button>
          </div>
        </div>
        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2"><label class="text-xs font-medium text-slate-600">Display</label><select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm" @change="page = 1; loadRows()"><option v-for="n in [5,10,25,50,100]" :key="n" :value="n">{{ n }}</option></select></div>
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
                  aria-label="Clear search"
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
              <table class="w-full min-w-[1200px] text-sm">
                <thead class="sticky top-0 bg-slate-50"><tr class="border-b border-slate-200 text-left"><th class="px-3 py-2 text-xs font-semibold uppercase">No</th><th class="px-3 py-2 text-xs font-semibold uppercase">Code</th><th class="px-3 py-2 text-xs font-semibold uppercase">Description (Malay)</th><th class="px-3 py-2 text-xs font-semibold uppercase">Description (English)</th><th class="px-3 py-2 text-xs font-semibold uppercase">PTJ</th><th class="px-3 py-2 text-xs font-semibold uppercase">PTJ Description</th><th class="px-3 py-2 text-xs font-semibold uppercase">Address</th><th class="px-3 py-2 text-xs font-semibold uppercase">Hostel Code</th><th class="px-3 py-2 text-xs font-semibold uppercase">Status</th><th class="px-3 py-2 text-xs font-semibold uppercase">Action</th></tr></thead>
                <tbody>
                  <tr v-for="row in rows" :key="row.ccrCostcentreId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td><td class="px-3 py-2">{{ row.ccrCostcentre }}</td><td class="px-3 py-2">{{ row.ccrCostcentreDesc }}</td><td class="px-3 py-2">{{ row.ccrCostcentreDescEng }}</td><td class="px-3 py-2">{{ row.ounCode }}</td><td class="px-3 py-2">{{ row.ounCodeDesc }}</td><td class="px-3 py-2">{{ row.ccrAddress }}</td><td class="px-3 py-2">{{ row.ccrHostelCode }}</td><td class="px-3 py-2">{{ row.ccrStatus }}</td>
                    <td class="px-3 py-2"><button class="rounded p-1 text-slate-500 hover:bg-slate-100" @click="openEdit(row.ccrCostcentreId)">✎</button></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <button class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
            <button class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
            <button class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium"><FileSpreadsheet class="h-3.5 w-3.5" />Excell</button>
            <button class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white" @click="openCreate"><Plus class="h-3.5 w-3.5" />Add</button>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Cost Centre</label>
              <select v-model="smartFilter.ccrCostcentre" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.costCentre" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PTJ</label>
              <select v-model="smartFilter.ptjCodeSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.ptjCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.statusSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
