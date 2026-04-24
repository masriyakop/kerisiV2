<script setup lang="ts">
/**
 * Cashbook / Bank Account (PAGEID 1736, MENUID 2097)
 *
 * Legacy FIMS BL `SNA_API_CASHBOOK_BANKACCOUNT`. Columns follow the
 * legacy `dt_bi` (No, Bank Name, Bank Account No, Account Code,
 * Account Description, Status, Create By, Action). Popup form items
 * come from component 5120 `items.json`.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Pencil, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { createBankAccount, getBankAccount, getBankAccountOptions, listBankAccount, updateBankAccount } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { BankAccountInput, BankAccountOptions, BankAccountRow, BankAccountUpdateInput } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<BankAccountRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("acm_acct_code");
const sortDir = ref<"asc" | "desc">("asc");
const showSmartFilter = ref(false);
const loading = ref(false);

const smartFilter = ref({
  bnmBankIdSm: "",
  bndStatusSm: "",
});

const options = ref<BankAccountOptions>({
  smartFilter: { bankName: [], status: [] },
  popupModal: { bankName: [], accountCode: [], ptj: [], isBankMain: [], status: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editId = ref<number | null>(null);
const form = ref<BankAccountInput>({
  bnmBankId: "",
  bndBankAcctno: "",
  acmAcctCode: "",
  ounCode: "",
  bndStatus: 1,
  bndIsBankMain: "N",
});
const editAccountDesc = ref<string>("");

function resetForm() {
  form.value = {
    bnmBankId: "",
    bndBankAcctno: "",
    acmAcctCode: "",
    ounCode: "",
    bndStatus: 1,
    bndIsBankMain: "N",
  };
  editAccountDesc.value = "";
}

async function loadOptions() {
  try {
    const res = await getBankAccountOptions();
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
    ...(smartFilter.value.bnmBankIdSm ? { bnm_bank_id_sm: smartFilter.value.bnmBankIdSm } : {}),
    ...(smartFilter.value.bndStatusSm ? { bnd_status_sm: smartFilter.value.bndStatusSm } : {}),
  });
  try {
    const res = await listBankAccount(`?${params.toString()}`);
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

function applySmartFilter() { page.value = 1; showSmartFilter.value = false; void loadRows(); }
function resetSmartFilter() { smartFilter.value = { bnmBankIdSm: "", bndStatusSm: "" }; }
function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

function openCreate() {
  editId.value = null;
  resetForm();
  showModal.value = true;
}

async function openEdit(id: number) {
  try {
    const res = await getBankAccount(id);
    editId.value = id;
    form.value = {
      bnmBankId: res.data.bnmBankId,
      bndBankAcctno: res.data.bndBankAcctno ?? "",
      acmAcctCode: res.data.acmAcctCode ?? "",
      ounCode: res.data.ounCode ?? "",
      bndStatus: (Number(res.data.bndStatus ?? 1) as 0 | 1),
      bndIsBankMain: (res.data.bndIsBankMain ?? "N") as "Y" | "N",
    };
    editAccountDesc.value = res.data.acmAcctDesc ?? "";
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load detail.");
  }
}

async function saveItem() {
  if (editId.value == null) {
    if (!form.value.bnmBankId || !form.value.bndBankAcctno?.trim() || !form.value.acmAcctCode) {
      toast.error("Validation failed", "Bank Name, Account No, and Account Code are required.");
      return;
    }
  } else if (!form.value.bndBankAcctno?.trim()) {
    toast.error("Validation failed", "Account No is required.");
    return;
  }
  if (!/^\d+$/.test(String(form.value.bndBankAcctno ?? "").trim())) {
    toast.error("Validation failed", "Please insert number only at Account number field to save.");
    return;
  }

  try {
    if (editId.value == null) {
      await createBankAccount({
        ...form.value,
        bndBankAcctno: form.value.bndBankAcctno.trim(),
      });
      toast.success("Insert successful");
    } else {
      const payload: BankAccountUpdateInput = {
        bndBankAcctno: form.value.bndBankAcctno.trim(),
        ounCode: form.value.ounCode || null,
        bndStatus: form.value.bndStatus,
        bndIsBankMain: form.value.bndIsBankMain,
      };
      await updateBankAccount(editId.value, payload);
      toast.success("Update successful");
    }
    showModal.value = false;
    await Promise.all([loadRows(), loadOptions()]);
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  }
}

const selectedAccountDesc = computed(() => {
  if (editId.value != null) return editAccountDesc.value;
  const opt = options.value.popupModal.accountCode.find((o) => String(o.id) === String(form.value.acmAcctCode));
  return opt?.desc ?? "";
});

const exportColumns = ["Bank Name", "Bank Account No", "Account Code", "Account Description", "Status", "Create By"];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Bank Account",
  apiDataPath: "/cashbook/bank-account",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Bank Name": r.bnmBankDesc ?? "",
      "Bank Account No": r.bndBankAcctno ?? "",
      "Account Code": r.acmAcctCode ?? "",
      "Account Description": r.acmAcctDesc ?? "",
      Status: r.bndStatus,
      "Create By": r.createdby ?? "",
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
    const ws = wb.addWorksheet("Bank Account");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([idx + 1, r.bnmBankDesc ?? "", r.bndBankAcctno ?? "", r.acmAcctCode ?? "", r.acmAcctDesc ?? "", r.bndStatus, r.createdby ?? ""]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Bank_Account_${new Date().toISOString().slice(0, 10)}.xlsx`;
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

onMounted(async () => { await loadOptions(); await loadRows(); });
onUnmounted(() => { if (searchDebounce) clearTimeout(searchDebounce); });
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Cashbook / Bank Account</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Bank Account</h1>
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
              <table class="w-full min-w-[1000px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Account No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('acm_acct_code')">Account Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Description</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Create By</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="8" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="row in rows" :key="row.bndBankDetlId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2">{{ row.bnmBankDesc ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bndBankAcctno ?? "-" }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.acmAcctCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acmAcctDesc ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.bndStatus === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                      >{{ row.bndStatus }}</span>
                    </td>
                    <td class="px-3 py-2">{{ row.createdby ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" title="Edit" @click="openEdit(row.bndBankDetlId)">
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Name</label>
              <select v-model="smartFilter.bnmBankIdSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.bankName" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="smartFilter.bndStatusSm" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
        <div class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ editId == null ? "Bank Account — Add" : "Bank Account — Edit" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Bank Name <span class="text-rose-600">*</span></label>
              <select v-model="form.bnmBankId" :disabled="editId != null" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100">
                <option value="">-- Select --</option>
                <option v-for="opt in options.popupModal.bankName" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account No <span class="text-rose-600">*</span></label>
              <input v-model="form.bndBankAcctno" type="text" inputmode="numeric" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Code <span class="text-rose-600">*</span></label>
              <select v-if="editId == null" v-model="form.acmAcctCode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">-- Select --</option>
                <option v-for="opt in options.popupModal.accountCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
              <input v-else :value="form.acmAcctCode" disabled class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Account Description</label>
              <input :value="selectedAccountDesc" disabled class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">PTJ</label>
              <select v-model="form.ounCode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">-- Select --</option>
                <option v-for="opt in options.popupModal.ptj" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Main Bank</label>
              <select v-model="form.bndIsBankMain" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in options.popupModal.isBankMain" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status <span class="text-rose-600">*</span></label>
              <select v-model.number="form.bndStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option v-for="opt in options.popupModal.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
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
