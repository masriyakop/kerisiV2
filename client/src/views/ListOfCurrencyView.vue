<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, Edit3, FileDown, FileSpreadsheet, MoreVertical, Plus, Search, Trash2, X } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import FimsListTable, { type FimsColumn } from "@/components/fims/FimsListTable.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import {
  createCurrency,
  deleteCurrency,
  getCurrency,
  listCurrencies,
  searchCurrencyCountries,
  updateCurrency,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type { CountryOption, ListOfCurrencyInput, ListOfCurrencyRow, ListOfCurrencyUpdate } from "@/types";

// Setup and Maintenance > Currency > List of Currency (PAGEID 2636 / MENUID 3198).
// Legacy BL: QLA_API_GLOBAL_LISTOFCURRENCY — datatable + popup modal full CRUD
// on `currency_master`. New rows enforce uniqueness on country code; updates
// are limited to currency unit and enabled flag; deletion is rejected when
// linked currency_details rows exist.
const PAGE_NAME = "List of Currency";
const PAGE_BREADCRUMB = "Setup and Maintenance / Currency / List of Currency";

const toast = useToast();
const { confirm } = useConfirmDialog();

const rows = ref<ListOfCurrencyRow[]>([]);
const loading = ref(false);
const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const sortBy = ref("cym_currency_id");
const sortDir = ref<"asc" | "desc">("desc");

const datatableRef = ref<DatatableRefApi | null>(null);
const overflowOpen = ref(false);
const overflowRoot = ref<HTMLElement | null>(null);

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const showModal = ref(false);
const modalMode = ref<"create" | "edit">("create");
const editing = ref<ListOfCurrencyRow | null>(null);
const saving = ref(false);
const form = ref<ListOfCurrencyInput>({
  cymCurrencyCode: "",
  cymCurrencyDesc: "",
  cnyCountryCode: "",
  cydUnit: 1,
  cymEnabled: "Active",
});

const countryQuery = ref("");
const countryOptions = ref<CountryOption[]>([]);
const showCountryDropdown = ref(false);

const columns: FimsColumn<ListOfCurrencyRow>[] = [
  { key: "no", label: "No", value: (r) => r.index },
  { key: "cymCurrencyCode", label: "Currency Code", sortable: true, sortKey: "cym_currency_code", hideable: true, value: (r) => r.cymCurrencyCode ?? "" },
  { key: "cymCurrencyDesc", label: "Currency Description", sortable: true, sortKey: "cym_currency_desc", hideable: true, value: (r) => r.cymCurrencyDesc ?? "" },
  { key: "cydUnit", label: "Unit", sortable: true, sortKey: "cyd_unit", hideable: true, align: "right", value: (r) => (r.cydUnit != null ? Number(r.cydUnit).toString() : "") },
  { key: "cnyCountryCode", label: "Country Code", sortable: true, sortKey: "cny_country_code", hideable: true, value: (r) => r.cnyCountryCode ?? "" },
  { key: "cnyCountryDesc", label: "Country Description", hideable: true, value: (r) => r.cnyCountryDesc ?? "" },
  { key: "cymEnabled", label: "Status", sortable: true, sortKey: "cym_enabled", hideable: true, value: (r) => r.cymEnabled },
  { key: "action", label: "Action" },
];

async function loadRows() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      page: String(page.value),
      limit: String(limit.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
      ...(q.value.trim() ? { q: q.value.trim() } : {}),
    });
    const res = await listCurrencies(`?${params.toString()}`);
    rows.value = res.data;
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load currencies.");
  } finally {
    loading.value = false;
  }
}

function onSort(sortKey: string) {
  if (sortBy.value === sortKey) sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else { sortBy.value = sortKey; sortDir.value = "desc"; }
  page.value = 1;
  void loadRows();
}

function prevPage() { if (page.value > 1) { page.value -= 1; void loadRows(); } }
function nextPage() { if (page.value < totalPages.value) { page.value += 1; void loadRows(); } }

function openCreate() {
  modalMode.value = "create";
  editing.value = null;
  form.value = {
    cymCurrencyCode: "",
    cymCurrencyDesc: "",
    cnyCountryCode: "",
    cydUnit: 1,
    cymEnabled: "Active",
  };
  countryQuery.value = "";
  countryOptions.value = [];
  showModal.value = true;
}

async function openEdit(row: ListOfCurrencyRow) {
  modalMode.value = "edit";
  try {
    const res = await getCurrency(row.cymCurrencyId);
    editing.value = res.data;
    form.value = {
      cymCurrencyCode: res.data.cymCurrencyCode ?? "",
      cymCurrencyDesc: res.data.cymCurrencyDesc ?? "",
      cnyCountryCode: res.data.cnyCountryCode ?? "",
      cydUnit: res.data.cydUnit ?? 0,
      cymEnabled: (res.data.cymEnabled === "Active" ? "Active" : "Inactive"),
    };
    countryQuery.value = res.data.cnyCountryCode
      ? `${res.data.cnyCountryCode}${res.data.cnyCountryDesc ? " - " + res.data.cnyCountryDesc : ""}`
      : "";
    showModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load currency.");
  }
}

let countrySearchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(countryQuery, (val) => {
  if (countrySearchDebounce) clearTimeout(countrySearchDebounce);
  countrySearchDebounce = setTimeout(async () => {
    countrySearchDebounce = null;
    if (modalMode.value !== "create") return;
    try {
      const res = await searchCurrencyCountries(`?q=${encodeURIComponent(val.trim())}`);
      countryOptions.value = res.data;
      showCountryDropdown.value = true;
    } catch {
      countryOptions.value = [];
    }
  }, 250);
});

function pickCountry(opt: CountryOption) {
  form.value.cnyCountryCode = opt.code;
  countryQuery.value = opt.label;
  showCountryDropdown.value = false;
}

function onCountryBlur() {
  // small delay so click on dropdown option fires before it closes
  window.setTimeout(() => {
    showCountryDropdown.value = false;
  }, 200);
}

async function saveRow() {
  if (modalMode.value === "create") {
    if (!form.value.cymCurrencyCode.trim() || !form.value.cymCurrencyDesc.trim() || !form.value.cnyCountryCode.trim()) {
      toast.error("Validation failed", "Currency Code, Description and Country are required.");
      return;
    }
  }

  saving.value = true;
  try {
    if (modalMode.value === "create") {
      await createCurrency(form.value);
      toast.success("Saved", "Currency created.");
    } else if (editing.value) {
      const update: ListOfCurrencyUpdate = {
        cydUnit: Number(form.value.cydUnit),
        cymEnabled: form.value.cymEnabled === "Active" ? "Active" : "Inactive",
      };
      await updateCurrency(editing.value.cymCurrencyId, update);
      toast.success("Saved", "Currency updated.");
    }
    showModal.value = false;
    await loadRows();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    saving.value = false;
  }
}

async function deleteRow(row: ListOfCurrencyRow) {
  const ok = await confirm({
    title: "Delete currency?",
    message: `This will remove ${row.cymCurrencyCode} (${row.cymCurrencyDesc ?? "-"}). Continue?`,
    confirmText: "Delete",
    destructive: true,
  });
  if (!ok) return;
  try {
    await deleteCurrency(row.cymCurrencyId);
    toast.success("Deleted", "Currency removed.");
    await loadRows();
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete.");
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
  apiDataPath: "/global/currencies",
  defaultExportColumns: ["Currency Code", "Currency Description", "Unit", "Country Code", "Country Description", "Status"],
  getFilteredList: () => (datatableRef.value?.getExportConfig?.()?.data as Record<string, unknown>[]) ?? [],
  datatableRef,
  searchKeyword: q,
  applyFilters: () => void loadRows(),
});

async function exportExcel() {
  try {
    const cfg = datatableRef.value?.getExportConfig?.();
    const columnsOut = cfg?.columns ?? [];
    const data = (cfg?.data as Record<string, unknown>[]) ?? [];
    if (data.length === 0) {
      toast.info("No data", "There is nothing to export.");
      return;
    }
    const ExcelJS = await import("exceljs");
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet(PAGE_NAME);
    ws.addRow(["No", ...columnsOut]);
    data.forEach((row, idx) => {
      const values = columnsOut.map((c) => (row[c] ?? "") as string | number);
      ws.addRow([idx + 1, ...values]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `${PAGE_NAME.replace(/\s+/g, "_")}_${new Date().toISOString().slice(0, 10)}.xlsx`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success("Excel downloaded");
  } catch (e) {
    toast.error("Export failed", e instanceof Error ? e.message : "Excel export failed.");
  }
}

function onClickOutside(event: MouseEvent) {
  if (!overflowOpen.value) return;
  if (!overflowRoot.value?.contains(event.target as Node)) overflowOpen.value = false;
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
  await loadRows();
  document.addEventListener("click", onClickOutside);
});

onUnmounted(() => {
  if (qSearchDebounce) clearTimeout(qSearchDebounce);
  if (countrySearchDebounce) clearTimeout(countrySearchDebounce);
  document.removeEventListener("click", onClickOutside);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />

      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
          <div class="flex items-center gap-2">
            <button type="button" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800" @click="openCreate">
              <Plus class="h-3.5 w-3.5" /> Add
            </button>
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
                <input v-model="q" type="search" placeholder="Filter rows..." class="w-52 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm shadow-sm" @keyup.enter="page = 1; void loadRows()" />
                <button v-if="q" type="button" class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100" @click="q = ''; page = 1; loadRows()">
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="loading" class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-600">Loading&hellip;</div>
          <FimsListTable
            v-else
            ref="datatableRef"
            :rows="rows"
            :columns="columns"
            :grouped="isGrouped"
            :sort-by="sortBy"
            :sort-dir="sortDir"
            :row-key="(r) => r.cymCurrencyId"
            :group-by="(r) => `Status: ${r.cymEnabled}`"
            min-width="1100px"
            @sort="onSort"
          >
            <template #action="{ row }">
              <div class="flex items-center gap-1">
                <button type="button" class="rounded p-1 text-slate-600 hover:bg-slate-100" title="Edit" @click="openEdit(row as ListOfCurrencyRow)">
                  <Edit3 class="h-3.5 w-3.5" />
                </button>
                <button type="button" class="rounded p-1 text-red-600 hover:bg-red-50" title="Delete" @click="deleteRow(row as ListOfCurrencyRow)">
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </template>
          </FimsListTable>

          <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
            <div class="text-xs text-slate-500">Showing {{ startIdx }}-{{ endIdx }} of {{ total }}</div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" :disabled="page <= 1" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="prevPage">Prev</button>
              <span class="text-xs text-slate-600">Page {{ page }} / {{ totalPages }}</span>
              <button type="button" :disabled="page >= totalPages" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50" @click="nextPage">Next</button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadPDF">
                <Download class="h-3.5 w-3.5" /> PDF
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="handleDownloadCSV">
                <FileDown class="h-3.5 w-3.5" /> CSV
              </button>
              <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium shadow-sm hover:bg-slate-50" @click="exportExcel">
                <FileSpreadsheet class="h-3.5 w-3.5" /> Excel
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showModal = false">
        <div class="w-full max-w-xl rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">
              {{ modalMode === "create" ? "Add Currency" : `Edit Currency — ${form.cymCurrencyCode}` }}
            </h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Currency Code <span class="text-red-500">*</span></label>
              <input
                v-model="form.cymCurrencyCode"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                :disabled="saving || modalMode === 'edit'"
                maxlength="10"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
              <select v-model="form.cymEnabled" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" :disabled="saving">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Currency Description <span class="text-red-500">*</span></label>
              <input
                v-model="form.cymCurrencyDesc"
                type="text"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                :disabled="saving || modalMode === 'edit'"
              />
            </div>
            <div class="md:col-span-2">
              <label class="mb-1 block text-sm font-medium text-slate-700">Country <span class="text-red-500">*</span></label>
              <div class="relative">
                <input
                  v-model="countryQuery"
                  type="text"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  :disabled="saving || modalMode === 'edit'"
                  @focus="modalMode === 'create' && (showCountryDropdown = true)"
                  @blur="onCountryBlur"
                />
                <div v-if="showCountryDropdown && countryOptions.length > 0" class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                  <button
                    v-for="opt in countryOptions"
                    :key="opt.id"
                    type="button"
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                    @mousedown.prevent="pickCountry(opt)"
                  >
                    {{ opt.label }}
                  </button>
                </div>
              </div>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
              <input
                v-model.number="form.cydUnit"
                type="number"
                step="0.0001"
                min="0"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                :disabled="saving"
              />
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="saving" @click="showModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="saving" @click="saveRow">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
