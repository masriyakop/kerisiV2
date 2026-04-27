<script setup lang="ts">
/**
 * Cashbook / Bank Setup (PAGEID 2680, MENUID 3246)
 *
 * Mirrors the legacy FIMS component `SNA_API_CASHBOOK_SETUPBANKMAIN` —
 * datatable + smart filter + popup form. Columns follow the legacy
 * `dt_bi` order (No, Bank Code, Bank Name, Main bank, Status, Updated Date,
 * Action). Popup form items follow `items.json` of component 8449.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Pencil, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { createBankSetup, getBankSetup, getBankSetupOptions, listBankSetup, updateBankSetup } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { BankSetupInput, BankSetupOptions, BankSetupRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<BankSetupRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("lbm_bank_code");
const sortDir = ref<"asc" | "desc">("asc");
const showSmartFilter = ref(false);
const loading = ref(false);

const smartFilter = ref({
  lbmBankCodeSm: "",
  isBankMainSm: "",
  lbmStatusSm: "",
});

const options = ref<BankSetupOptions>({
  smartFilter: { bankCode: [], isBankMain: [], status: [] },
  popupModal: { isBankMain: [], status: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editCode = ref<string | null>(null);
const form = ref<BankSetupInput>({
  lbmBankCode: "",
  lbmBankName: "",
  isBankMain: "N",
  lbmStatus: 1,
});

async function loadOptions() {
  try {
    const res = await getBankSetupOptions();
    options.value = res.data;
  } catch (e) {
    toast.error("Failed to load options", e instanceof Error ? e.message : "Unable to fetch options.");
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
    ...(smartFilter.value.lbmBankCodeSm ? { lbm_bank_code_sm: smartFilter.value.lbmBankCodeSm } : {}),
    ...(smartFilter.value.isBankMainSm ? { is_bank_main_sm: smartFilter.value.isBankMainSm } : {}),
    ...(smartFilter.value.lbmStatusSm ? { lbm_status_sm: smartFilter.value.lbmStatusSm } : {}),
  });
  try {
    const res = await listBankSetup(`?${params.toString()}`);
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

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = { lbmBankCodeSm: "", isBankMainSm: "", lbmStatusSm: "" };
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

function openCreate() {
  editCode.value = null;
  form.value = { lbmBankCode: "", lbmBankName: "", isBankMain: "N", lbmStatus: 1 };
  showModal.value = true;
}

async function openEdit(code: string) {
  try {
    const res = await getBankSetup(code);
    editCode.value = code;
    form.value = {
      lbmBankCode: res.data.lbmBankCode,
      lbmBankName: res.data.lbmBankName,
      isBankMain: (res.data.isBankMain ?? "N") as "Y" | "N",
      lbmStatus: (Number(res.data.lbmStatus ?? 1) as 0 | 1),
    };
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load detail.");
  }
}

async function saveItem() {
  if (editCode.value == null) {
    if (!form.value.lbmBankCode?.trim() || !form.value.lbmBankName?.trim()) {
      toast.error("Validation failed", "Bank Code and Bank Name are required.");
      return;
    }
  } else if (!form.value.lbmBankName?.trim()) {
    toast.error("Validation failed", "Bank Name is required.");
    return;
  }

  try {
    if (editCode.value == null) {
      await createBankSetup({
        ...form.value,
        lbmBankCode: form.value.lbmBankCode!.trim().toUpperCase(),
        lbmBankName: form.value.lbmBankName.trim(),
      });
      toast.success("Insert successful");
    } else {
      await updateBankSetup(editCode.value, {
        ...form.value,
        lbmBankName: form.value.lbmBankName.trim(),
      });
      toast.success("Update successful");
    }
    showModal.value = false;
    await Promise.all([loadRows(), loadOptions()]);
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  }
}

const exportColumns = ["Bank Code", "Bank Name", "Main bank", "Status", "Updated Date"];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Bank Setup",
  apiDataPath: "/cashbook/bank-setup",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Bank Code": r.lbmBankCode,
      "Bank Name": r.lbmBankName,
      "Main bank": r.mainBankLabel,
      Status: r.lbmStatus,
      "Updated Date": r.updatedDate ?? "",
    })),
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
    const ws = wb.addWorksheet("Bank Setup");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([idx + 1, r.lbmBankCode, r.lbmBankName, r.mainBankLabel, r.lbmStatus, r.updatedDate ?? ""]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Bank_Setup_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
  await loadOptions();
  await loadRows();
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Cashbook / Bank Setup</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Setup Bank Main</h1>
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
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" @click="showSmartFilter = true">
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[800px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('lbm_bank_code')">Bank Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('lbm_bank_name')">Bank Name</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('isBankMain')">Main bank</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('lbm_status')">Status</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('updateddate')">Updated Date</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="7" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.lbmBankCode" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.lbmBankCode }}</td>
                    <td class="px-3 py-2">{{ row.lbmBankName }}</td>
                    <td class="px-3 py-2">{{ row.mainBankLabel }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.lbmStatus === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                      >
                        {{ row.lbmStatus }}
                      </span>
                    </td>
                    <td class="px-3 py-2">{{ row.updatedDate ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" title="Edit Setup" @click="openEdit(row.lbmBankCode)">
                        <Pencil class="h-3.5 w-3.5" />
                      </button>
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
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800" @click="openCreate">
                <Plus class="h-3.5 w-3.5" />Add
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Code</label>
              <select v-model="smartFilter.lbmBankCodeSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.bankCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Main bank</label>
              <select v-model="smartFilter.isBankMainSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.isBankMain" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.lbmStatusSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
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
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ editCode == null ? "Setup Detail — Add" : "Setup Detail — Edit" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Code <span class="text-rose-600">*</span><br /><span class="text-xs text-slate-500">(Capital Letter)</span></label>
              <input v-model="form.lbmBankCode" type="text" :disabled="editCode != null" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase disabled:bg-slate-100" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Main Bank</label>
              <select v-model="form.isBankMain" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in options.popupModal.isBankMain" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Name <span class="text-rose-600">*</span></label>
              <input v-model="form.lbmBankName" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status <span class="text-rose-600">*</span></label>
              <select v-model.number="form.lbmStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in options.popupModal.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="saveItem">Save Setup</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
