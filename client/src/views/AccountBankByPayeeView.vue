<script setup lang="ts">
/**
 * Account Payable / Account Bank By Payee (PAGEID 2262, MENUID 2751)
 *
 * Source: FIMS component `AS_BL_AP_ACCOUNTBANKBPAYEE` — read-only datatable
 * driven by a top "Payee Type" filter:
 *   A   → STUDENT        (stud_account + student)
 *   B   → STAFF          (staff_account + staff, sta_salary_bank='Y')
 *   CDG → VENDOR         (vend_supplier_account + vend_customer_supplier)
 *   E   → SPONSOR        (sponsor)
 *   F   → INVESTMENT     (investment_institution)
 * Columns / smart filters differ per variant; generic variants (A/B/CDG) share
 * a Name / Status / Bank Code / Account Name / Account No. layout.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Download, FileDown, FileSpreadsheet, Filter, MoreVertical, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useDatatableFeatures } from "@/composables/useDatatableFeatures";
import type { DatatableRefApi } from "@/composables/useDatatableFeatures";
import { getAccountBankByPayeeOptions, listAccountBankByPayee } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  AccountBankByPayeeGenericRow,
  AccountBankByPayeeInvestmentRow,
  AccountBankByPayeeOptions,
  AccountBankByPayeeSponsorRow,
  AccountBankPayeeType,
} from "@/types";

type AnyRow = AccountBankByPayeeGenericRow | AccountBankByPayeeSponsorRow | AccountBankByPayeeInvestmentRow;

const toast = useToast();
const datatableRef = ref<DatatableRefApi | null>(null);
const payeeType = ref<AccountBankPayeeType | "">("");
const rows = ref<AnyRow[]>([]);
const page = ref(1);
const limit = ref(10);
const q = ref("");
const total = ref(0);
const loading = ref(false);
const showSmartFilter = ref(false);

const smartFilter = ref<Record<string, string>>({});
const options = ref<AccountBankByPayeeOptions>({ payeeType: [], smartFilter: {} });

const totalPages = computed(() => (total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1));
const startIdx = computed(() => (total.value === 0 ? 0 : (page.value - 1) * limit.value + 1));
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const isGeneric = computed(() => ["A", "B", "CDG"].includes(payeeType.value));
const isSponsor = computed(() => payeeType.value === "E");
const isInvestment = computed(() => payeeType.value === "F");

const genericRows = computed(() => rows.value as AccountBankByPayeeGenericRow[]);
const sponsorRows = computed(() => rows.value as AccountBankByPayeeSponsorRow[]);
const investmentRows = computed(() => rows.value as AccountBankByPayeeInvestmentRow[]);

async function loadOptions() {
  try {
    const res = await getAccountBankByPayeeOptions(
      (payeeType.value || undefined) as AccountBankPayeeType | undefined,
    );
    options.value = res.data;
  } catch (e) {
    toast.error("Failed to load options", e instanceof Error ? e.message : "Unable to fetch options.");
  }
}

async function loadRows() {
  if (!payeeType.value) {
    rows.value = [];
    total.value = 0;
    return;
  }
  loading.value = true;
  const query: Record<string, string> = {
    payee_type: payeeType.value,
    page: String(page.value),
    limit: String(limit.value),
  };
  if (q.value) query.q = q.value;
  for (const [key, val] of Object.entries(smartFilter.value)) {
    if (val) query[key] = val;
  }
  try {
    const res = await listAccountBankByPayee(`?${new URLSearchParams(query).toString()}`);
    rows.value = res.data as AnyRow[];
    total.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load rows.");
  } finally {
    loading.value = false;
  }
}

function applySmartFilter() {
  page.value = 1;
  showSmartFilter.value = false;
  void loadRows();
}

function resetSmartFilter() {
  smartFilter.value = {};
}

async function onPayeeTypeChange() {
  page.value = 1;
  smartFilter.value = {};
  q.value = "";
  await loadOptions();
  await loadRows();
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

const exportColumns = computed<string[]>(() => {
  if (isSponsor.value) {
    return ["Sponsor Code", "Sponsor Name", "Bank Name", "Account No.", "Address 1", "Address 2", "City", "Postcode", "State", "Contact Person", "Contact No"];
  }
  if (isInvestment.value) {
    return ["Inst Code", "Inst Name", "Bank Code", "Bank Shortname", "Bank Name", "Address 1", "Address 2", "Address 3", "City", "Postcode", "State", "Country"];
  }
  return ["Name", "Status", "Bank Code", "Account Name", "Account No."];
});

const { templateFileInputRef, onTemplateFileChange, handleDownloadPDF, handleDownloadCSV } = useDatatableFeatures({
  pageName: "Account Bank by Payee",
  apiDataPath: "/account-payable/account-bank-by-payee",
  defaultExportColumns: exportColumns as unknown as string[],
  getFilteredList: () => {
    if (isSponsor.value) {
      return sponsorRows.value.map((r) => ({
        "Sponsor Code": r.spnSponsorCode,
        "Sponsor Name": r.spnSponsorName ?? "",
        "Bank Name": r.spnBankNameCd ?? "",
        "Account No.": r.spnBankAccNo ?? "",
        "Address 1": r.spnAddress1 ?? "",
        "Address 2": r.spnAddress2 ?? "",
        City: r.spnCity ?? "",
        Postcode: r.spnPostcode ?? "",
        State: r.spnState ?? "",
        "Contact Person": r.spnContactPerson ?? "",
        "Contact No": r.spnContactNo ?? "",
      }));
    }
    if (isInvestment.value) {
      return investmentRows.value.map((r) => ({
        "Inst Code": r.iitInstCode,
        "Inst Name": r.iitInstName ?? "",
        "Bank Code": r.bnmBankCode ?? "",
        "Bank Shortname": r.bnmShortname ?? "",
        "Bank Name": r.bankName ?? "",
        "Address 1": r.iitAddress1 ?? "",
        "Address 2": r.iitAddress2 ?? "",
        "Address 3": r.iitAddress3 ?? "",
        City: r.iitCity ?? "",
        Postcode: r.iitPcode ?? "",
        State: r.iitState ?? "",
        Country: r.iitCountry ?? "",
      }));
    }
    return genericRows.value.map((r) => ({
      Name: r.name,
      Status: r.status ?? "",
      "Bank Code": r.acctCode ?? "",
      "Account Name": r.acctName ?? "",
      "Account No.": r.acctNo ?? "",
    }));
  },
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
    const ws = wb.addWorksheet("Account Bank by Payee");
    ws.addRow(["No", ...exportColumns.value]);

    if (isSponsor.value) {
      sponsorRows.value.forEach((r, idx) =>
        ws.addRow([
          idx + 1,
          r.spnSponsorCode,
          r.spnSponsorName ?? "",
          r.spnBankNameCd ?? "",
          r.spnBankAccNo ?? "",
          r.spnAddress1 ?? "",
          r.spnAddress2 ?? "",
          r.spnCity ?? "",
          r.spnPostcode ?? "",
          r.spnState ?? "",
          r.spnContactPerson ?? "",
          r.spnContactNo ?? "",
        ]),
      );
    } else if (isInvestment.value) {
      investmentRows.value.forEach((r, idx) =>
        ws.addRow([
          idx + 1,
          r.iitInstCode,
          r.iitInstName ?? "",
          r.bnmBankCode ?? "",
          r.bnmShortname ?? "",
          r.bankName ?? "",
          r.iitAddress1 ?? "",
          r.iitAddress2 ?? "",
          r.iitAddress3 ?? "",
          r.iitCity ?? "",
          r.iitPcode ?? "",
          r.iitState ?? "",
          r.iitCountry ?? "",
        ]),
      );
    } else {
      genericRows.value.forEach((r, idx) =>
        ws.addRow([idx + 1, r.name, r.status ?? "", r.acctCode ?? "", r.acctName ?? "", r.acctNo ?? ""]),
      );
    }

    const buf = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Account_Bank_By_Payee_${payeeType.value}_${new Date().toISOString().slice(0, 10)}.xlsx`;
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
});
onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <input ref="templateFileInputRef" type="file" accept=".json,application/json" class="hidden" @change="onTemplateFileChange" />
      <h1 class="page-title">Account Payable / Account Bank by Payee</h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Filter By</h1>
        </div>
        <div class="p-4">
          <label class="mb-1 block text-sm font-medium text-slate-700">Payee Type <span class="text-rose-600">*</span></label>
          <select
            v-model="payeeType"
            class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm"
            @change="onPayeeTypeChange"
          >
            <option value="">-- Please select --</option>
            <option v-for="opt in options.payeeType" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
          </select>
        </div>
      </article>

      <article v-if="payeeType" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">Listing</h1>
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
              <!-- Generic (STUDENT / STAFF / VENDOR) -->
              <table v-if="isGeneric" class="w-full min-w-[900px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Status</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account No.</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="6" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="(row, i) in genericRows" :key="`g-${i}-${row.name}`" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.name }}</td>
                    <td class="px-3 py-2">{{ row.status ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.acctNo ?? "-" }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Sponsor -->
              <table v-else-if="isSponsor" class="w-full min-w-[1400px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Sponsor Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Sponsor Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Account No.</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address 1</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address 2</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">City</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Postcode</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">State</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Contact Person</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Contact No</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="12" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="row in sponsorRows" :key="row.spnSponsorCode" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.spnSponsorCode }}</td>
                    <td class="px-3 py-2">{{ row.spnSponsorName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnBankNameCd ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnBankAccNo ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnAddress1 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnAddress2 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnCity ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnPostcode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnState ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnContactPerson ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.spnContactNo ?? "-" }}</td>
                  </tr>
                </tbody>
              </table>

              <!-- Investment Institution -->
              <table v-else-if="isInvestment" class="w-full min-w-[1500px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Inst Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Inst Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Code</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Shortname</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Bank Name</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address 1</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address 2</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Address 3</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">City</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Postcode</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">State</th>
                    <th class="px-3 py-2 text-xs font-semibold uppercase">Country</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading"><td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">Loading...</td></tr>
                  <tr v-else-if="rows.length === 0"><td colspan="13" class="px-3 py-6 text-center text-sm text-slate-500">No records found.</td></tr>
                  <tr v-for="row in investmentRows" :key="row.iitInstCode" class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">{{ row.iitInstCode }}</td>
                    <td class="px-3 py-2">{{ row.iitInstName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmBankCode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bnmShortname ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.bankName ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitAddress1 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitAddress2 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitAddress3 ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitCity ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitPcode ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitState ?? "-" }}</td>
                    <td class="px-3 py-2">{{ row.iitCountry ?? "-" }}</td>
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
            <!-- Generic A / B / CDG -->
            <template v-if="isGeneric">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                <select v-model="smartFilter.smlist_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.name" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select
                  v-model="
                    smartFilter[
                      payeeType === 'A' ? 'smlist_status_A' : payeeType === 'B' ? 'smlist_status_B' : 'smlist_status_CDG'
                    ]
                  "
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.status" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account Name</label>
                <select v-model="smartFilter.smlist_acct_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.accountName" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account No.</label>
                <input v-model="smartFilter.acct_no" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
            </template>

            <!-- Sponsor (E) -->
            <template v-else-if="isSponsor">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sponsor Code</label>
                <select v-model="smartFilter.spn_sponsor_code" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.sponsorCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sponsor Name</label>
                <select v-model="smartFilter.spn_sponsor_name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.sponsorName" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bank Name</label>
                <select v-model="smartFilter.spn_bank_name_cd" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.bankName" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account No.</label>
                <input v-model="smartFilter.spn_bank_acc_no" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
              </div>
            </template>

            <!-- Investment (F) -->
            <template v-else-if="isInvestment">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Inst Code</label>
                <select v-model="smartFilter.iit_inst_code" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.instCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bank Code</label>
                <select v-model="smartFilter.bnm_bank_code" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                  <option value="">Any</option>
                  <option v-for="opt in options.smartFilter.bankCode" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
                </select>
              </div>
            </template>
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
