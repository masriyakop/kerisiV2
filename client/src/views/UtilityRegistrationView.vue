<script setup lang="ts">
/**
 * Account Payable / Utility Registration (PAGEID 2881, MENUID 3466)
 *
 * Source: FIMS component `SNA_API_AP_UTILITYREGISTRATION` — datatable +
 * inline add/edit via popup modal. Legacy detail deep-link was menuID 3467,
 * which is not in the migrated menu set; Add/Edit is handled by this view's
 * modal against /api/account-payable/utility-registration.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, MoreVertical, Pencil, Plus, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  createUtilityRegistration,
  getUtilityRegistration,
  listUtilityRegistration,
  updateUtilityRegistration,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { UtilityRegistrationInput, UtilityRegistrationRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<UtilityRegistrationRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("vcs_vendor_code");
const sortDir = ref<"asc" | "desc">("desc");
const loading = ref(false);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const editId = ref<string | null>(null);
const editVendorCode = ref<string | null>(null);
const form = ref<UtilityRegistrationInput>({
  vcsVendorName: "",
  vcsBillerCode: "",
  vcsVendorStatus: 1,
});

async function loadRows() {
  loading.value = true;
  const params = new URLSearchParams({
    page: String(page.value),
    limit: String(limit.value),
    sort_by: sortBy.value,
    sort_dir: sortDir.value,
    ...(q.value ? { q: q.value } : {}),
  });
  try {
    const res = await listUtilityRegistration(`?${params.toString()}`);
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
  editId.value = null;
  editVendorCode.value = null;
  form.value = { vcsVendorName: "", vcsBillerCode: "", vcsVendorStatus: 1 };
  showModal.value = true;
}

async function openEdit(id: string) {
  try {
    const res = await getUtilityRegistration(id);
    editId.value = id;
    editVendorCode.value = res.data.vcsVendorCode ?? null;
    form.value = {
      vcsVendorName: res.data.vcsVendorName ?? "",
      vcsBillerCode: res.data.vcsBillerCode ?? "",
      vcsVendorStatus: Number(res.data.vcsVendorStatus ?? 1) as 0 | 1,
    };
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load detail.");
  }
}

async function saveItem() {
  if (!form.value.vcsVendorName.trim() || !form.value.vcsBillerCode.trim()) {
    toast.error("Validation failed", "Payee Name and Biller Code are required.");
    return;
  }
  try {
    const payload: UtilityRegistrationInput = {
      vcsVendorName: form.value.vcsVendorName.trim(),
      vcsBillerCode: form.value.vcsBillerCode.trim(),
      vcsVendorStatus: form.value.vcsVendorStatus,
    };
    if (editId.value == null) {
      await createUtilityRegistration(payload);
      toast.success("Insert successful");
    } else {
      await updateUtilityRegistration(editId.value, payload);
      toast.success("Update successful");
    }
    showModal.value = false;
    await loadRows();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  }
}

const exportColumns = ["Payee Code", "Payee Name", "Biller Code", "Status"];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Utility Registration",
  apiDataPath: "/account-payable/utility-registration",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Payee Code": r.vcsVendorCode ?? "",
      "Payee Name": r.vcsVendorName ?? "",
      "Biller Code": r.vcsBillerCode ?? "",
      Status: r.vcsVendorStatus,
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
    const ws = wb.addWorksheet("Utility Registration");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([idx + 1, r.vcsVendorCode ?? "", r.vcsVendorName ?? "", r.vcsBillerCode ?? "", r.vcsVendorStatus]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Utility_Registration_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
      <h1 class="page-title">Account Payable / Utility Registration</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List of Utility Registration</h1>
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
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[800px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_code')">Payee Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_name')">Payee Name</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_biller_code')">Biller Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_status')">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.vcsId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.vcsVendorCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsVendorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsBillerCode ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.vcsVendorStatus === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                      >
                        {{ row.vcsVendorStatus }}
                      </span>
                    </td>
                    <td class="px-3 py-2">
                      <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100" title="Edit" @click="openEdit(row.vcsId)">
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
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">{{ editId == null ? "Utility Registration — Add" : "Utility Registration — Edit" }}</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div v-if="editId != null" class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Payee Code</label>
              <input type="text" :value="editVendorCode ?? ''" disabled class="w-full rounded-lg border border-slate-300 bg-slate-100 px-3 py-2 text-sm" />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Payee Name <span class="text-rose-600">*</span></label>
              <input v-model="form.vcsVendorName" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Biller Code <span class="text-rose-600">*</span></label>
              <input v-model="form.vcsBillerCode" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status <span class="text-rose-600">*</span></label>
              <select v-model.number="form.vcsVendorStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option :value="1">ACTIVE</option>
                <option :value="0">INACTIVE</option>
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
