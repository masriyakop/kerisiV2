<script setup lang="ts">
/**
 * Account Payable / Payee Registration (PAGEID 1403, MENUID 1711)
 *
 * Source: FIMS component `NF_BL_AP_PAY_REGISTRATION` — read-only datatable with
 * a smart filter (payee code / state / status / year register). The legacy
 * "Edit" action deep-linked to menuID 1713 which is NOT in the migrated menu
 * set, so this screen intentionally ships without row-level CRUD.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getPayeeRegistrationOptions, listPayeeRegistration } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { PayeeRegistrationOptions, PayeeRegistrationRow } from "@/types";

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const rows = ref<PayeeRegistrationRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const sortBy = ref("vcs_vendor_code");
const sortDir = ref<"asc" | "desc">("asc");
const showSmartFilter = ref(false);
const loading = ref(false);

const smartFilter = ref({
  vcsVendorCode: "",
  state: "",
  vcsVendorStatus: "",
  yearRegister: "",
});

const options = ref<PayeeRegistrationOptions>({
  smartFilter: { payeeCode: [], state: [], status: [], yearRegister: [] },
});

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

async function loadOptions() {
  try {
    const res = await getPayeeRegistrationOptions();
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
    ...(smartFilter.value.vcsVendorCode ? { vcs_vendor_code: smartFilter.value.vcsVendorCode } : {}),
    ...(smartFilter.value.state ? { state: smartFilter.value.state } : {}),
    ...(smartFilter.value.vcsVendorStatus ? { vcs_vendor_status: smartFilter.value.vcsVendorStatus } : {}),
    ...(smartFilter.value.yearRegister ? { year_register: smartFilter.value.yearRegister } : {}),
  });
  try {
    const res = await listPayeeRegistration(`?${params.toString()}`);
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
  smartFilter.value = { vcsVendorCode: "", state: "", vcsVendorStatus: "", yearRegister: "" };
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

const exportColumns = [
  "Payee Code",
  "Payee Name",
  "Address Line 1",
  "Address Line 2",
  "Address Line 3",
  "Town",
  "State",
  "Bank Name",
  "Bank Acc No",
  "JomPAY Biller Code",
  "Phone",
  "Email",
  "Contact Person",
  "IC No",
  "SSM No",
  "Vendor Status",
];

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Payee Registration",
  apiDataPath: "/account-payable/payee-registration",
  defaultExportColumns: exportColumns,
  getFilteredList: () =>
    rows.value.map((r) => ({
      "Payee Code": r.vcsVendorCode ?? "",
      "Payee Name": r.vcsVendorName ?? "",
      "Address Line 1": r.vcsAddr1 ?? "",
      "Address Line 2": r.vcsAddr2 ?? "",
      "Address Line 3": r.vcsAddr3 ?? "",
      Town: r.vcsTown ?? "",
      State: r.state ?? "",
      "Bank Name": r.vendorBank ?? "",
      "Bank Acc No": r.vcsBankAccno ?? "",
      "JomPAY Biller Code": r.vcsBillerCode ?? "",
      Phone: r.vcsTelNo ?? "",
      Email: r.vcsEmailAddress ?? "",
      "Contact Person": r.vcsContactPerson ?? "",
      "IC No": r.vcsIcNo ?? "",
      "SSM No": r.vcsRegistrationNo ?? "",
      "Vendor Status": r.vcsVendorStatus,
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
    const ws = wb.addWorksheet("Payee Registration");
    ws.addRow(["No", ...exportColumns]);
    rows.value.forEach((r, idx) => {
      ws.addRow([
        idx + 1,
        r.vcsVendorCode ?? "",
        r.vcsVendorName ?? "",
        r.vcsAddr1 ?? "",
        r.vcsAddr2 ?? "",
        r.vcsAddr3 ?? "",
        r.vcsTown ?? "",
        r.state ?? "",
        r.vendorBank ?? "",
        r.vcsBankAccno ?? "",
        r.vcsBillerCode ?? "",
        r.vcsTelNo ?? "",
        r.vcsEmailAddress ?? "",
        r.vcsContactPerson ?? "",
        r.vcsIcNo ?? "",
        r.vcsRegistrationNo ?? "",
        r.vcsVendorStatus,
      ]);
    });
    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Payee_Registration_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
      <h1 class="page-title">Account Payable / Payee Registration</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">List Of Payee Registration (Others)</h1>
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
              <table class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_code')">Payee Code</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_name')">Payee Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address Line 1</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address Line 2</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address Line 3</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Town</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_state')">State</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Acc No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">JomPAY Biller Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Phone</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Email</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Contact Person</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">IC No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">SSM No</th>
                    <th class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase" @click="toggleSort('vcs_vendor_status')">Vendor Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="17" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="17" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td>
                  </tr>
                  <tr v-for="row in rows" :key="row.vcsId" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.vcsVendorCode }}</td>
                    <td class="px-3 py-2">{{ row.vcsVendorName }}</td>
                    <td class="px-3 py-2">{{ row.vcsAddr1 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsAddr2 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsAddr3 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsTown ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.state ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vendorBank ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsBankAccno ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsBillerCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsTelNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsEmailAddress ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsContactPerson ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsIcNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.vcsRegistrationNo ?? "-" }}</td>
                    <td class="px-3 py-2">
                      <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="row.vcsVendorStatus === 'ACTIVE' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'"
                      >
                        {{ row.vcsVendorStatus }}
                      </span>
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
              <label class="mb-1 block text-sm font-medium text-slate-700">Payee Code</label>
              <select v-model="smartFilter.vcsVendorCode" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.payeeCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">State</label>
              <select v-model="smartFilter.state" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.state" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Vendor Status</label>
              <select v-model="smartFilter.vcsVendorStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Year Register</label>
              <select v-model="smartFilter.yearRegister" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Any</option>
                <option v-for="opt in options.smartFilter.yearRegister" :key="opt.id" :value="String(opt.id)">{{ opt.label }}</option>
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
