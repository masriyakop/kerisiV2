<script setup lang="ts">
/**
 * Cashbook / Bank Master (PAGEID 1682, MENUID 2036)
 *
 * Legacy FIMS BL `ZR_MODUL_SETUP_BANKMASTER_API`. Columns follow the
 * `dt_bi` order. Bank ID is kept d-none in the legacy UI and we mirror
 * that by omitting it from the visible columns. Popup form items come
 * from `items.json` of component 5121.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Pencil, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { createBankMaster, getBankMaster, getBankMasterOptions, listBankMaster, updateBankMaster } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { BankMasterInput, BankMasterOptions, BankMasterRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<BankMasterRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("bnm_bank_code");
const sortDir = ref<"asc" | "desc">("asc");
const showSmartFilter = ref(false);
const loading = ref(false);

const smartFilter = ref({
  bnmBankCodeSm: "",
  bnmBankCodeMainSm: "",
});

const options = ref<BankMasterOptions>({
  smartFilter: { bankCode: [], mainBank: [] },
  popupModal: { mainBank: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editId = ref<number | null>(null);
const form = ref<BankMasterInput>({
  bnmBankCode: "",
  bnmBankCodeMain: "",
  bnmBankDesc: "",
  bnmShortname: "",
  bnmBankAddress: "",
  bnmAddressCity: "",
  bnmContactPerson: "",
  bnmBranchName: "",
  bnmOfficeTelno: "",
  bnmOfficeFaxno: "",
  bnmUrlAddress: "",
  bnmSwiftCode: "",
  bnmBusinessNature: "",
});

function resetForm() {
  form.value = {
    bnmBankCode: "",
    bnmBankCodeMain: "",
    bnmBankDesc: "",
    bnmShortname: "",
    bnmBankAddress: "",
    bnmAddressCity: "",
    bnmContactPerson: "",
    bnmBranchName: "",
    bnmOfficeTelno: "",
    bnmOfficeFaxno: "",
    bnmUrlAddress: "",
    bnmSwiftCode: "",
    bnmBusinessNature: "",
  };
}

async function loadOptions() {
  try {
    const res = await getBankMasterOptions();
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
    ...(smartFilter.value.bnmBankCodeSm ? { bnm_bank_code_sm: smartFilter.value.bnmBankCodeSm } : {}),
    ...(smartFilter.value.bnmBankCodeMainSm ? { bnm_bank_code_main_sm: smartFilter.value.bnmBankCodeMainSm } : {}),
  });
  try {
    const res = await listBankMaster(`?${params.toString()}`);
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
  smartFilter.value = { bnmBankCodeSm: "", bnmBankCodeMainSm: "" };
}

function prevPage() {
  if (page.value > 1) { page.value -= 1; void loadRows(); }
}
function nextPage() {
  if (page.value < totalPages.value) { page.value += 1; void loadRows(); }
}

function openCreate() {
  editId.value = null;
  resetForm();
  showModal.value = true;
}

async function openEdit(id: number) {
  try {
    const res = await getBankMaster(id);
    editId.value = id;
    form.value = {
      bnmBankCode: res.data.bnmBankCode ?? "",
      bnmBankCodeMain: res.data.bnmBankCodeMain ?? "",
      bnmBankDesc: res.data.bnmBankDesc ?? "",
      bnmShortname: res.data.bnmShortname ?? "",
      bnmBankAddress: res.data.bnmBankAddress ?? "",
      bnmAddressCity: res.data.bnmAddressCity ?? "",
      bnmContactPerson: res.data.bnmContactPerson ?? "",
      bnmBranchName: res.data.bnmBranchName ?? "",
      bnmOfficeTelno: res.data.bnmOfficeTelno ?? "",
      bnmOfficeFaxno: res.data.bnmOfficeFaxno ?? "",
      bnmUrlAddress: res.data.bnmUrlAddress ?? "",
      bnmSwiftCode: res.data.bnmSwiftCode ?? "",
      bnmBusinessNature: res.data.bnmBusinessNature ?? "",
    };
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load detail.");
  }
}

async function saveItem() {
  const required: [keyof BankMasterInput, string][] = [
    ["bnmBankCode", "Bank Code"],
    ["bnmBankDesc", "Description"],
    ["bnmBankAddress", "Address"],
    ["bnmContactPerson", "Contact Person"],
    ["bnmUrlAddress", "URL Address"],
    ["bnmSwiftCode", "Swift Code"],
  ];
  for (const [key, label] of required) {
    if (!String(form.value[key] ?? "").trim()) {
      toast.error("Validation failed", `${label} is required.`);
      return;
    }
  }

  try {
    const payload: BankMasterInput = {
      ...form.value,
      bnmBankCode: form.value.bnmBankCode.trim(),
      bnmBankDesc: form.value.bnmBankDesc.trim(),
      bnmBankAddress: form.value.bnmBankAddress.trim(),
      bnmContactPerson: form.value.bnmContactPerson.trim(),
      bnmUrlAddress: form.value.bnmUrlAddress.trim(),
      bnmSwiftCode: form.value.bnmSwiftCode.trim(),
    };
    if (editId.value == null) {
      await createBankMaster(payload);
      toast.success("Insert successful");
    } else {
      await updateBankMaster(editId.value, payload);
      toast.success("Update successful");
    }
    showModal.value = false;
    await Promise.all([loadRows(), loadOptions()]);
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  }
}

const exportColumns = [
  "Main Code",
  "Bank Code",
  "Bank Desc",
  "Shortname",
  "Address",
  "Address City",
  "Contact Person",
  "Branch Name",
  "Office Tel No",
  "Office Fax No",
  "URL Address",
  "Swift Code",
  "Business Nature",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Bank Master",
  apiDataPath: "/cashbook/bank-master",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Main Code": r.bnmBankCodeMain ?? "",
      "Bank Code": r.bnmBankCode,
      "Bank Desc": r.bnmBankDesc,
      Shortname: r.bnmShortname ?? "",
      Address: r.bnmBankAddress ?? "",
      "Address City": r.bnmAddressCity ?? "",
      "Contact Person": r.bnmContactPerson ?? "",
      "Branch Name": r.bnmBranchName ?? "",
      "Office Tel No": r.bnmOfficeTelno ?? "",
      "Office Fax No": r.bnmOfficeFaxno ?? "",
      "URL Address": r.bnmUrlAddress ?? "",
      "Swift Code": r.bnmSwiftCode ?? "",
      "Business Nature": r.bnmBusinessNature ?? "",
    })),
  datatableRef,
  searchKeyword: q,
  smartFilter,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  if (rows.value.length === 0) {
    toast.info("No data", "There is nothing to export.");
    return;
  }
  try {
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet("Bank Master");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.bnmBankCodeMain ?? "",
        r.bnmBankCode,
        r.bnmBankDesc,
        r.bnmShortname ?? "",
        r.bnmBankAddress ?? "",
        r.bnmAddressCity ?? "",
        r.bnmContactPerson ?? "",
        r.bnmBranchName ?? "",
        r.bnmOfficeTelno ?? "",
        r.bnmOfficeFaxno ?? "",
        r.bnmUrlAddress ?? "",
        r.bnmSwiftCode ?? "",
        r.bnmBusinessNature ?? "",
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Bank_Master_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

onMounted(async () => {
  await loadOptions();
  await loadRows();
});
onUnmounted(() => { if (searchDebounce) clearTimeout(searchDebounce); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Cashbook / Bank Master</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Bank Master</h1>
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
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''"><X class="h-3.5 w-3.5" /></button>
              </div>
              <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm" @click="showSmartFilter = true">
                <Filter class="h-4 w-4" />Filter
              </button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[460px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_bank_code_main')">Main Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_bank_code')">Bank Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_bank_desc')">Bank Desc</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_shortname')">Shortname</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_address_city')">Address City</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('bnm_contact_person')">Contact Person</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Branch Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Office Tel No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Office Fax No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">URL Address</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Swift Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Business Nature</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="15" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="15" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="row in rows" :key="row.bnmBankId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ row.bnmBankCodeMain ?? "-" }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.bnmBankCode }}</td>
                    <td class="px-3 py-2">{{ row.bnmBankDesc }}</td>
                    <td class="px-3 py-2">{{ row.bnmShortname ?? "-" }}</td>
                    <td class="whitespace-pre-line px-3 py-2">{{ row.bnmBankAddress ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmAddressCity ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">{{ row.bnmContactPerson ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmBranchName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmOfficeTelno ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmOfficeFaxno ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmUrlAddress ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmSwiftCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmBusinessNature ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" title="Edit" @click="openEdit(row.bnmBankId)">
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
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadPDF"><Download class="h-3.5 w-3.5" />PDF</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="handleDownloadCSV"><FileDown class="h-3.5 w-3.5" />CSV</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium" @click="exportExcel"><FileSpreadsheet class="h-3.5 w-3.5" />Excel</button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800" @click="openCreate"><Plus class="h-3.5 w-3.5" />Add</button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showSmartFilter" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showSmartFilter = false">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3"><h3 class="text-base font-semibold text-slate-900">Smart filter</h3></div>
          <div class="space-y-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Code</label>
              <select v-model="smartFilter.bnmBankCodeSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.bankCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Main Bank</label>
              <select v-model="smartFilter.bnmBankCodeMainSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.mainBank" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
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
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3 sticky top-0 bg-white">
            <h3 class="text-base font-semibold text-slate-900">{{ editId == null ? "Bank Master — Add" : "Bank Master — Edit" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Main Code</label>
              <select v-model="form.bnmBankCodeMain" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">-- Select --</option>
                <option v-for="opt in options.popupModal.mainBank" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Code <span class="text-rose-600">*</span></label>
              <input v-model="form.bnmBankCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Description <span class="text-rose-600">*</span></label>
              <input v-model="form.bnmBankDesc" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Shortname</label>
              <input v-model="form.bnmShortname" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Address City</label>
              <input v-model="form.bnmAddressCity" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Address <span class="text-rose-600">*</span></label>
              <textarea v-model="form.bnmBankAddress" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Contact Person <span class="text-rose-600">*</span></label>
              <input v-model="form.bnmContactPerson" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Branch Name</label>
              <input v-model="form.bnmBranchName" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Office Tel No</label>
              <input v-model="form.bnmOfficeTelno" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Office Fax No</label>
              <input v-model="form.bnmOfficeFaxno" type="text" inputmode="numeric" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">URL Address <span class="text-rose-600">*</span></label>
              <input v-model="form.bnmUrlAddress" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Swift Code <span class="text-rose-600">*</span></label>
              <input v-model="form.bnmSwiftCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Business Nature</label>
              <input v-model="form.bnmBusinessNature" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-100 bg-white px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white" @click="saveItem">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
