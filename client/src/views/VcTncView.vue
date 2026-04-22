<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Search, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import VcTncTable from "@/components/fims/VcTncTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getVcTnc, getVcTncOptions, listVcTnc, updateVcTnc } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { VcTncRow, VcTncSuperiorOption } from "@/types";

// Breadcrumb / title mirror legacy PAGEBREADCRUMBS / PAGETITLE for PAGEID 1715.
const PAGE_NAME = "HOD, VC & TNC";
const PAGE_BREADCRUMB = "Setup and Maintenance / HOD, VC & TNC";

const toast = useToast();

const rows = ref<VcTncRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const superiorOptions = ref<VcTncSuperiorOption[]>([]);
const showModal = ref(false);
const editId = ref<number | null>(null);
const form = ref({
  ounId: 0,
  ounCode: "",
  ounDesc: "",
  stStaffIdHead: "",
  stStaffIdHeadLabel: "",
  stStaffIdSuperior: "",
});
const saving = ref(false);

async function loadOptions() {
  try {
    const res = await getVcTncOptions();
    superiorOptions.value = res.data.popupModal.superior;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load VC/TNC options.");
  }
}

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sortBy: "oun_code",
      sortDir: "asc",
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await listVcTnc(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load list.");
  } finally {
    loading.value = false;
  }
}

async function openEdit(id: number) {
  try {
    const res = await getVcTnc(id);
    editId.value = res.data.id;
    form.value = {
      ounId: res.data.id,
      ounCode: res.data.ounCode ?? "",
      ounDesc: res.data.ounDesc ?? "",
      stStaffIdHead: res.data.stStaffIdHead ?? "",
      stStaffIdHeadLabel: res.data.stStaffIdHeadLabel ?? (res.data.stStaffIdHead ?? ""),
      stStaffIdSuperior: res.data.stStaffIdSuperior ?? "",
    };
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to fetch record.");
  }
}

async function saveItem() {
  if (!form.value.stStaffIdSuperior) {
    toast.error("Validation failed", "VC/TNC selection is required.");
    return;
  }
  if (editId.value == null) return;

  saving.value = true;
  try {
    await updateVcTnc(editId.value, {
      stStaffIdSuperior: form.value.stStaffIdSuperior,
    });
    toast.success("Update successful");
    showModal.value = false;
    await loadRows();
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    saving.value = false;
  }
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
  pageName: PAGE_NAME,
  apiDataPath: "/setup/vc-tnc",
  defaultExportColumns: ["Code", "Description", "Head of Department", "VC/TNC"],
  getFilteredList: () =>
    rows.value.map((r) => ({
      Code: r.ounCode,
      Description: r.ounDesc ?? "",
      "Head of Department": r.stStaffIdHeadLabel ?? r.stStaffIdHead ?? "",
      "VC/TNC": r.stStaffIdSuperiorLabel ?? r.stStaffIdSuperior ?? "",
    })),
  datatableRef,
  searchKeyword: q,
  applyFilters: () => {
    void loadRows();
  },
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columns = cfg?.columns ?? ["Code", "Description", "Head of Department", "VC/TNC"];
    const data = cfg?.data ?? [];

    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("VC TNC");
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
    a.download = `HOD_VC_TNC_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
    <div class="mx-auto max-w-7xl space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <p class="text-base font-semibold text-slate-500">{{ PAGE_BREADCRUMB }}</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <div>
            <h1 class="text-base font-semibold text-slate-900">List Of Organization Unit ({{ PAGE_NAME }} Setup)</h1>
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
            </div>
          </div>

          <VcTncTable ref="datatableRef" :rows="rows" :page-size="limit" :grouped="isGrouped" @edit="openEdit" />

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
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }} Information</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Code</label>
              <input v-model="form.ounCode" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" disabled />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
              <input v-model="form.ounDesc" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" disabled />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Head of Department</label>
              <input v-model="form.stStaffIdHeadLabel" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" disabled />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">VC/TNC *</label>
              <select v-model="form.stStaffIdSuperior" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="saving">
                <option value="">-- Select --</option>
                <option v-for="opt in superiorOptions" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="saving" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving" @click="saveItem">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
