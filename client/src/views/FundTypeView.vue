<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Plus, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FundTypeSmartFilterTable from "@/components/fims/FundTypeSmartFilterTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import { createFundType, getFundType, getFundTypeOptions, listFundTypes, updateFundType } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { FundTypeInput, FundTypeRow } from "@/types";

const toast = useToast();

const rows = ref<FundTypeRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

const showSmartFilter = ref(false);
const smartFilter = ref({
  ftyFundTypeSm: "",
  ftyBasisSm: "",
  ftyStatusSm: "",
});

const smartFundTypeOptions = ref<{ id: string; label: string }[]>([]);
const smartBasisOptions = ref<{ id: string; label: string }[]>([]);
const smartStatusOptions = ref<{ id: string; label: string }[]>([]);
const modalBasisOptions = ref<{ id: string; label: string }[]>([]);
const modalStatusOptions = ref<{ id: number; label: string }[]>([]);

const datatableRef = ref<InstanceType<typeof FundTypeSmartFilterTable> | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const showModal = ref(false);
const editId = ref<number | null>(null);
const form = ref<FundTypeInput>({
  ftyFundType: "",
  ftyFundDesc: "",
  ftyFundDescEng: "",
  ftyBasis: "",
  ftyStatus: 1,
  ftyRemark: "",
});


async function loadOptions() {
  const res = await getFundTypeOptions();
  smartFundTypeOptions.value = res.data.smartFilter.fundType;
  smartBasisOptions.value = res.data.smartFilter.basis;
  smartStatusOptions.value = res.data.smartFilter.status;
  modalBasisOptions.value = res.data.popupModal.basis;
  modalStatusOptions.value = res.data.popupModal.status;
  if (!form.value.ftyBasis && modalBasisOptions.value.length > 0) form.value.ftyBasis = modalBasisOptions.value[0].id;
}

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sortBy: "fty_fund_type",
      sortDir: "asc",
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
      ...(smartFilter.value.ftyFundTypeSm ? { ftyFundTypeSm: smartFilter.value.ftyFundTypeSm } : {}),
      ...(smartFilter.value.ftyBasisSm ? { ftyBasisSm: smartFilter.value.ftyBasisSm } : {}),
      ...(smartFilter.value.ftyStatusSm ? { ftyStatusSm: smartFilter.value.ftyStatusSm } : {}),
    });
    const res = await listFundTypes(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load Fund Type.");
  } finally {
    loading.value = false;
  }
}

function openCreate() {
  editId.value = null;
  form.value = {
    ftyFundType: "",
    ftyFundDesc: "",
    ftyFundDescEng: "",
    ftyBasis: modalBasisOptions.value[0]?.id ?? "",
    ftyStatus: 1,
    ftyRemark: "",
  };
  showModal.value = true;
}

async function openEdit(id: number) {
  const res = await getFundType(id);
  editId.value = id;
  form.value = {
    ftyFundType: res.data.ftyFundType ?? "",
    ftyFundDesc: res.data.ftyFundDesc ?? "",
    ftyFundDescEng: res.data.ftyFundDescEng ?? "",
    ftyBasis: res.data.ftyBasis ?? "",
    ftyStatus: Number(res.data.ftyStatus ?? 1),
    ftyRemark: res.data.ftyRemark ?? "",
  };
  showModal.value = true;
}

async function saveItem() {
  if (!form.value.ftyFundType || !form.value.ftyFundDesc || !form.value.ftyBasis) {
    toast.error("Validation failed", "Please fill all required fields.");
    return;
  }

  try {
    if (editId.value == null) {
      await createFundType(form.value);
      toast.success("Insert successful");
    } else {
      await updateFundType(editId.value, form.value);
      toast.success("Update successful");
    }
    showModal.value = false;
    await loadRows();
    await loadOptions();
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  }
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { ftyFundTypeSm: "", ftyBasisSm: "", ftyStatusSm: "" };
}

const {
  templateFileInputRef,
  isGrouped,
  handleSaveTemplate,
  handleLoadTemplate,
  onTemplateFileChange,
  handleUngroupList,
  handleGroupList,
  handleDownloadPDF,
  handleDownloadCSV,
} = useDatatableFeatures({
  pageName: "Fund Type",
  apiDataPath: "/fund-types",
  defaultExportColumns: ["Fund Type", "Description (Malay)", "Description (English)", "Type Basis", "Status"],
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Fund Type": r.ftyFundType,
      "Description (Malay)": r.ftyFundDesc,
      "Description (English)": r.ftyFundDescEng ?? "",
      "Type Basis": r.ftyBasis,
      Status: r.ftyStatus,
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => {
    void loadRows();
  },
});


async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columns = cfg?.columns ?? ["Fund Type", "Description (Malay)", "Description (English)", "Type Basis", "Status"];
    const data = cfg?.data ?? [];

    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Fund Type");
    ws.addRow(["No", ...columns]);

    (data as Record<string, unknown>[]).forEach((row, idx) => {
      const values = columns.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });

    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Fund_Type_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (!overflowOpen.value) return;
  if (overflowRoot.value?.contains(event.target as Node)) return;
  overflowOpen.value = false;
}

let qSearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  qSearchDebounce = setTimeout(() => {
    qSearchDebounce = null;
    page.value = 1;
    void loadRows();
  }, 350);
});

onMounted(async () => {
  await loadOptions();
  await loadRows();
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  document.removeEventListener("click", onClickOutside);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <h1 class="page-title">Setup and Maintenance / General Ledger Structure / Fund Type</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">Fund Type</h1>
          </div>
          <div ref="overflowRoot" class="relative">
            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" @click.stop="overflowOpen = !overflowOpen">
              <MoreVertical class="h-4 w-4" />
            </button>
            <div v-if="overflowOpen" class="absolute right-0 z-30 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-lg" @click.stop>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleSaveTemplate()">Save template</button>
              <button type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleLoadTemplate()">Load template</button>
              <button v-if="isGrouped" type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleUngroupList()">Ungroup list</button>
              <button v-else type="button" class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50" @click="overflowOpen = false; handleGroupList()">Group list</button>
            </div>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select v-model.number="limit" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm shadow-sm" @change="page = 1; loadRows()">
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm"
                  @keyup.enter="page = 1; void loadRows()"
                />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; loadRows()">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm hover:bg-slate-50" @click="showSmartFilter = true">
                <Filter class="h-4 w-4" />
                Filter
              </button>
            </div>
          </div>

          <FundTypeSmartFilterTable ref="datatableRef" :rows="rows" :page-size="limit" :grouped="isGrouped" @edit="openEdit" />

          <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
            <div class="flex flex-wrap gap-2">
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" />
                PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" />
                CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" />
                Excell
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800" @click="openCreate">
                <Plus class="h-3.5 w-3.5" />
                Add
              </button>
            </div>
          </div>

        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Smart filter</h3>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fund Type</label>
              <select v-model="smartFilter.ftyFundTypeSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in smartFundTypeOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Type Basis</label>
              <select v-model="smartFilter.ftyBasisSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in smartBasisOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.ftyStatusSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in smartStatusOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
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

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ editId == null ? "Fund Type Add" : "Fund Type Edit" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Fund Type *</label>
              <input v-model="form.ftyFundType" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Type Basis *</label>
              <select v-model="form.ftyBasis" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in modalBasisOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description (Malay) *</label>
              <textarea v-model="form.ftyFundDesc" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description (English)</label>
              <textarea v-model="form.ftyFundDescEng" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
              <select v-model.number="form.ftyStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in modalStatusOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Remark</label>
              <input v-model="form.ftyRemark" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="saveItem">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
