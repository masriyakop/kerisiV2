<script setup lang="ts">
/**
 * Account Receivable / Discount Note Form (MENUID 1784).
 *
 * Source: FIMS BL `DT_AR_DISCOUNT_NOTE_FORM` + `PAGE_SECOND_LEVEL_MENU.json`
 * entries for MENUID 1784 (Discount Note Head / Debit / Credit / Action).
 *
 * Layout (matches legacy):
 *
 *   BREADCRUMB: Account Receivable / Discount Note Form
 *
 *   ┌─ Discount Note Head ───────────────────────────────────────────┐
 *   │ Discount Note No * : [disabled]     Date *  : [dd/mm/yyyy]     │
 *   │ Customer Type *    : [dropdown]                                │
 *   │ Customer Name *    : [autosuggest — filtered by Customer Type] │
 *   │ Customer ID *      : [auto-filled]                             │
 *   │ Discount Policy *  : [dropdown — discount_note_policy]         │
 *   │ Invoice No *       : [autosuggest — require_balance=false]     │
 *   │ Description        : [textarea]                                │
 *   │ Status : DRAFT                     Total DC : MYR x.xx         │
 *   └────────────────────────────────────────────────────────────────┘
 *
 *   Debit  (15 cols): No | Rev Cat | Fee Item | Fund | Activity | OU |
 *                     Costcentre | Code SO | Account Code | Tax Code |
 *                     Tax Amount | Invoice Balance | Discount Type |
 *                     DC Amount (editable) | DC Tax Amount
 *
 *   Credit (15 cols): No | Rev Cat | Fee Item | Fund | Activity | OU |
 *                     Costcentre | Code SO | Account Code | Tax Code |
 *                     Tax Amount | Invoice Balance | DC Amount |
 *                     DC Tax Amount | Balance
 *
 *   Action: [ Cancel Note ]  [ Save ]  [ Submit ]
 *
 * Route entry:
 *   /admin/kerisi/m/1784                 → create
 *   /admin/kerisi/m/1784?id=X            → edit
 *   /admin/kerisi/m/1784?id=X&mode=view  → read-only
 *
 * Reuses the shared AR lookup endpoints (see `.cursor/rules/ar-note-form-pattern.mdc`):
 *   - `listDebtorTypes()`  — Customer Type dropdown
 *   - `searchArDebtors(q, custType)` — Customer Name combobox (filtered by type)
 *   - `searchArInvoicesByDebtor(custId, q, 20, { requireBalance: false })`
 *       — Invoice No combobox (fully-paid approved invoices included)
 *   - `listDiscountPolicies(q)` — Discount Policy dropdown (new, DC-specific)
 *
 * Workflow caveat: Submit / Cancel are stubs — see `DiscountNoteFormController`
 * docblock.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  ArrowLeft,
  Ban,
  ChevronLeft,
  Loader2,
  RefreshCcw,
  Save,
  Search,
  Send,
  Trash2,
  X,
} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import {
  cancelDiscountNoteForm,
  getDiscountNoteForm,
  getDiscountNoteFormInvoiceLines,
  listDebtorTypes,
  listDiscountPolicies,
  saveDiscountNoteForm,
  searchArDebtors,
  searchArInvoicesByDebtor,
  submitDiscountNoteForm,
} from "@/api/cms";
import type {
  DebtorSearchOption,
  DiscountInvoiceLine,
  DiscountNoteFormHead,
  DiscountNoteFormLine,
  DiscountPolicyOption,
  InvoiceSearchOption,
  LookupOption,
} from "@/types";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm } = useConfirmDialog();

const mode = computed(() =>
  route.query.mode === "view"
    ? "view"
    : String(route.query.id ?? "")
      ? "edit"
      : "create",
);
const isReadonly = computed(() => mode.value === "view");
const dcId = computed(() => {
  const raw = route.query.id;
  return typeof raw === "string" && raw !== "" ? raw : null;
});

const loading = ref(false);
const saving = ref(false);
const submitting = ref(false);
const cancelling = ref(false);

const customerTypes = ref<LookupOption[]>([]);
const discountPolicies = ref<DiscountPolicyOption[]>([]);

const head = ref<DiscountNoteFormHead>({
  dcm_discount_note_master_id: null,
  dcm_dcnote_no: null,
  cim_invoice_no: null,
  cim_cust_invoice_id: null,
  dcm_cust_id: null,
  dcm_cust_type: null,
  dcm_cust_type_desc: null,
  dcm_cust_name: null,
  dcm_dcnote_desc: null,
  dcm_dcnote_date: todayDdMmYyyy(),
  dcm_dc_total_amount: 0,
  dcm_status_cd: "DRAFT",
  dcm_status_cd_desc: "DRAFT",
  dcp_dc_policy_id: null,
  invoiceTotalAmount: 0,
  invoiceBalanceAmount: 0,
});
const debit = ref<DiscountNoteFormLine[]>([]);
const credit = ref<DiscountNoteFormLine[]>([]);

// ─── Customer (debtor) autosuggest combobox ─────────────────────────────────
const debtorQuery = ref("");
const debtorResults = ref<DebtorSearchOption[]>([]);
const debtorOpen = ref(false);
const debtorLoading = ref(false);
let debtorTimer: ReturnType<typeof setTimeout> | null = null;

// ─── Invoice autosuggest combobox ───────────────────────────────────────────
const invoiceQuery = ref("");
const invoiceResults = ref<InvoiceSearchOption[]>([]);
const invoiceOpen = ref(false);
const invoiceLoading = ref(false);
let invoiceTimer: ReturnType<typeof setTimeout> | null = null;

async function runDebtorSearch(term: string) {
  debtorLoading.value = true;
  try {
    const res = await searchArDebtors(
      term,
      head.value.dcm_cust_type ?? "",
    );
    debtorResults.value = res.data;
    debtorOpen.value = true;
  } catch {
    debtorResults.value = [];
  } finally {
    debtorLoading.value = false;
  }
}

function onDebtorInput(v: string) {
  debtorQuery.value = v;
  head.value.dcm_cust_name = v || null;
  if (!v) head.value.dcm_cust_id = null;
  if (debtorTimer) clearTimeout(debtorTimer);
  debtorTimer = setTimeout(() => {
    void runDebtorSearch(v.trim());
  }, 300);
}

function pickDebtor(opt: DebtorSearchOption) {
  head.value.dcm_cust_id = opt.code;
  head.value.dcm_cust_name = opt.name;
  debtorQuery.value = opt.name;
  debtorOpen.value = false;
  clearInvoice();
}

function clearDebtor() {
  head.value.dcm_cust_id = null;
  head.value.dcm_cust_name = null;
  debtorQuery.value = "";
  debtorResults.value = [];
  debtorOpen.value = false;
  clearInvoice();
}

async function runInvoiceSearch(term: string) {
  const custId = head.value.dcm_cust_id ?? "";
  if (!custId) {
    invoiceResults.value = [];
    invoiceOpen.value = true;
    return;
  }
  invoiceLoading.value = true;
  try {
    const res = await searchArInvoicesByDebtor(custId, term, 20, {
      requireBalance: false,
    });
    invoiceResults.value = res.data;
    invoiceOpen.value = true;
  } catch {
    invoiceResults.value = [];
  } finally {
    invoiceLoading.value = false;
  }
}

function onInvoiceInput(v: string) {
  invoiceQuery.value = v;
  head.value.cim_invoice_no = v || null;
  if (!v) head.value.cim_cust_invoice_id = null;
  if (invoiceTimer) clearTimeout(invoiceTimer);
  invoiceTimer = setTimeout(() => {
    void runInvoiceSearch(v.trim());
  }, 300);
}

async function pickInvoice(opt: InvoiceSearchOption) {
  head.value.cim_cust_invoice_id = opt.invoiceId;
  head.value.cim_invoice_no = opt.invoiceNo;
  invoiceQuery.value = opt.invoiceNo;
  invoiceOpen.value = false;
  if (head.value.dcp_dc_policy_id) {
    await loadInvoiceLines();
  }
}

function clearInvoice() {
  head.value.cim_cust_invoice_id = null;
  head.value.cim_invoice_no = null;
  invoiceQuery.value = "";
  invoiceResults.value = [];
  invoiceOpen.value = false;
  debit.value = [];
  credit.value = [];
}

function onInvoiceFocus() {
  if (!head.value.dcm_cust_id) return;
  invoiceOpen.value = true;
  void runInvoiceSearch(invoiceQuery.value.trim());
}

// Deferred close handlers for combobox blur — see .cursor/rules/ar-note-form-pattern.mdc §3.
function closeDebtorSoon() {
  window.setTimeout(() => (debtorOpen.value = false), 150);
}

function closeInvoiceSoon() {
  window.setTimeout(() => (invoiceOpen.value = false), 150);
}

function todayDdMmYyyy(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, "0");
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`;
}

function fmtMoney(amount: number | null | undefined): string {
  const n = Number(amount ?? 0);
  if (!Number.isFinite(n)) return "0.00";
  return new Intl.NumberFormat("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(n);
}

const totalDiscountAmount = computed(() =>
  debit.value.reduce((sum, l) => sum + Number(l.dcAmt ?? 0), 0),
);

const canSubmit = computed(() => {
  if (isReadonly.value) return false;
  const status = (head.value.dcm_status_cd ?? "DRAFT").toUpperCase();
  return !dcId.value || status === "DRAFT";
});

const canCancel = computed(
  () =>
    !isReadonly.value &&
    !!dcId.value &&
    ["DRAFT", "ENTRY"].includes(
      (head.value.dcm_status_cd ?? "").toUpperCase(),
    ),
);

function invoiceLineToFormLine(line: DiscountInvoiceLine): DiscountNoteFormLine {
  return {
    ID: line.ID,
    feeCategoryId: line.feeCategoryId,
    feeCategory: line.feeCategory,
    cii_item_code: line.cii_item_code,
    feeItem: line.feeItem,
    dcd_detail_desc: line.dcType ?? null,
    dcType: line.dcType ?? null,
    fty_fund_type: line.fty_fund_type,
    fundType: line.fundType,
    at_activity_code: line.at_activity_code,
    activityCode: line.activityCode,
    oun_code: line.oun_code,
    ptjCode: line.ptjCode,
    ccr_costcentre: line.ccr_costcentre,
    costcentre: line.costcentre,
    cpa_project_no: line.cpa_project_no,
    codeSO: line.codeSO,
    acm_acct_code: line.acm_acct_code,
    acctCode: line.acctCode,
    taxCode: line.taxCode,
    taxAmt: line.taxAmt,
    amt: line.amt,
    balance: line.balance ?? line.amt,
    dcAmt: Number(line.dcAmt ?? 0),
    dcTaxAmt: Number(line.dcTaxAmt ?? 0),
  };
}

async function loadDiscountNote() {
  if (!dcId.value) return;
  loading.value = true;
  try {
    const res = await getDiscountNoteForm(dcId.value);
    head.value = res.data.head;
    debtorQuery.value = head.value.dcm_cust_name ?? "";
    invoiceQuery.value = head.value.cim_invoice_no ?? "";
    debit.value = res.data.debit;
    credit.value = res.data.credit;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load discount note.",
    );
  } finally {
    loading.value = false;
  }
}

async function loadInvoiceLines() {
  const invoiceId = head.value.cim_cust_invoice_id ?? "";
  const policyId = head.value.dcp_dc_policy_id ?? "";
  if (!invoiceId) {
    toast.error("Missing invoice", "Select an Invoice No from the dropdown.");
    return;
  }
  if (!policyId) {
    toast.error("Missing policy", "Select a Discount Policy from the dropdown.");
    return;
  }
  loading.value = true;
  try {
    const res = await getDiscountNoteFormInvoiceLines(invoiceId, policyId);
    debit.value = res.data.debit.map(invoiceLineToFormLine);
    credit.value = res.data.credit.map(invoiceLineToFormLine);
    head.value.invoiceBalanceAmount = res.data.invoiceBalance;
    head.value.invoiceTotalAmount =
      res.data.debit.reduce((s, r) => s + Number(r.amt ?? 0), 0) ||
      head.value.invoiceTotalAmount;
  } catch (e) {
    toast.error(
      "Lookup failed",
      e instanceof Error ? e.message : "Unable to load invoice lines.",
    );
  } finally {
    loading.value = false;
  }
}

function removeDebitLine(i: number) {
  debit.value.splice(i, 1);
}

function onCustomerTypeChange(value: string) {
  head.value.dcm_cust_type = value;
  const match = customerTypes.value.find((o) => o.value === value);
  head.value.dcm_cust_type_desc = match?.label ?? null;
  // Customer Type change invalidates Customer + Invoice selection (scoped lookup).
  clearDebtor();
}

function onPolicyChange(value: string) {
  head.value.dcp_dc_policy_id = value || null;
  // If we already have an invoice selected, reload its lines under the new policy.
  if (value && head.value.cim_cust_invoice_id) {
    void loadInvoiceLines();
  } else {
    debit.value = [];
    credit.value = [];
  }
}

function buildSavePayload() {
  const toSavedLine = (l: DiscountNoteFormLine) => ({
    dcd_cust_invoice_detl_id: l.ID,
    dcd_item_category: l.feeCategoryId,
    cii_item_code: l.cii_item_code,
    dcd_detail_desc: l.dcd_detail_desc ?? l.dcType ?? null,
    fty_fund_type: l.fty_fund_type,
    at_activity_code: l.at_activity_code,
    oun_code: l.oun_code,
    ccr_costcentre: l.ccr_costcentre,
    cpa_project_no: l.cpa_project_no,
    acm_acct_code: l.acm_acct_code,
    dcd_taxcode: l.taxCode,
    dcd_taxamt: Number(l.taxAmt ?? 0),
    dcd_invoice_amt: Number(l.amt ?? 0),
    dcd_dcnote_amt: Number(l.dcAmt ?? 0),
    dcd_dc_taxamt: Number(l.dcTaxAmt ?? 0),
    dcd_bal_amt: Number(l.balance ?? 0),
    extended: {
      feeCategory: l.feeCategory,
      feeItem: l.feeItem,
      fundType: l.fundType,
      activityCode: l.activityCode,
      ptjCode: l.ptjCode,
      costcentre: l.costcentre,
      codeSO: l.codeSO,
      acctCode: l.acctCode,
    },
  });

  return {
    head: { ...head.value, dcm_dc_total_amount: totalDiscountAmount.value },
    debit: debit.value.map(toSavedLine),
    credit: credit.value.map(toSavedLine),
  };
}

function validateHead(): boolean {
  if (!head.value.dcm_dcnote_date) {
    toast.error("Missing date", "Discount Note date is required.");
    return false;
  }
  if (!head.value.dcm_cust_type) {
    toast.error("Missing customer type", "Customer Type is required.");
    return false;
  }
  if (!head.value.dcm_cust_id || !head.value.dcm_cust_name) {
    toast.error(
      "Missing customer",
      "Select a Customer Name from the dropdown.",
    );
    return false;
  }
  if (!head.value.dcp_dc_policy_id) {
    toast.error(
      "Missing policy",
      "Select a Discount Policy from the dropdown.",
    );
    return false;
  }
  if (!head.value.cim_invoice_no || !head.value.cim_cust_invoice_id) {
    toast.error(
      "Missing invoice",
      "Select an Invoice No from the dropdown.",
    );
    return false;
  }
  return true;
}

async function persistDraft(): Promise<string | null> {
  if (!validateHead()) return null;

  saving.value = true;
  try {
    const res = await saveDiscountNoteForm(buildSavePayload());
    head.value.dcm_discount_note_master_id = res.data.dcID;
    head.value.dcm_dcnote_no = res.data.discountNoteNo;
    head.value.dcm_status_cd = res.data.status_cd;
    head.value.dcm_status_cd_desc = res.data.status_cd;
    if (!dcId.value) {
      await router.replace({
        path: "/admin/kerisi/m/1784",
        query: { id: res.data.dcID, mode: "edit" },
      });
    }
    return res.data.dcID;
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save discount note.",
    );
    return null;
  } finally {
    saving.value = false;
  }
}

async function handleSave() {
  const id = await persistDraft();
  if (id)
    toast.success(
      "Saved",
      `Discount note ${head.value.dcm_dcnote_no ?? id} saved.`,
    );
}

async function handleSubmit() {
  const ok = await confirm({
    title: "Submit discount note?",
    message:
      "Submit will save and mark this discount note as Entry. Note: FIMS workflow routing is not yet migrated, so no approver task will be created.",
    confirmText: "Submit",
  });
  if (!ok) return;

  const id = dcId.value ?? (await persistDraft());
  if (!id) return;

  submitting.value = true;
  try {
    const res = await submitDiscountNoteForm(id);
    const payload = res.data as { status_cd?: string; message?: string };
    head.value.dcm_status_cd = payload.status_cd ?? "Entry";
    head.value.dcm_status_cd_desc = payload.status_cd ?? "Entry";
    toast.success("Submitted", payload.message ?? "Discount note submitted.");
  } catch (e) {
    toast.error(
      "Submit failed",
      e instanceof Error ? e.message : "Unable to submit discount note.",
    );
  } finally {
    submitting.value = false;
  }
}

async function handleCancel() {
  if (!dcId.value) return;
  const ok = await confirm({
    title: "Cancel discount note?",
    message:
      "This will mark the discount note as Cancelled. Enter a reason in the dialog that follows.",
    confirmText: "Cancel note",
    destructive: true,
  });
  if (!ok) return;

  const reason = window.prompt("Cancel reason (required):", "") ?? "";
  if (reason.trim().length < 3) {
    toast.error(
      "Reason required",
      "Please enter a cancel reason (min 3 chars).",
    );
    return;
  }

  cancelling.value = true;
  try {
    const res = await cancelDiscountNoteForm(dcId.value, reason.trim());
    const payload = res.data as { status_cd?: string; message?: string };
    head.value.dcm_status_cd = payload.status_cd ?? "CANCELLED";
    head.value.dcm_status_cd_desc = "Cancelled";
    toast.success("Cancelled", payload.message ?? "Discount note cancelled.");
  } catch (e) {
    toast.error(
      "Cancel failed",
      e instanceof Error ? e.message : "Unable to cancel discount note.",
    );
  } finally {
    cancelling.value = false;
  }
}

function goBack() {
  void router.push("/admin/kerisi/m/1043");
}

async function loadCustomerTypes() {
  try {
    const res = await listDebtorTypes();
    customerTypes.value = res.data;
  } catch {
    customerTypes.value = [];
  }
}

async function loadDiscountPolicies() {
  try {
    const res = await listDiscountPolicies();
    discountPolicies.value = res.data;
  } catch {
    discountPolicies.value = [];
  }
}

watch(
  () => route.query.id,
  () => {
    if (dcId.value) void loadDiscountNote();
  },
);

onMounted(async () => {
  await Promise.all([loadCustomerTypes(), loadDiscountPolicies()]);
  if (dcId.value) {
    await loadDiscountNote();
  }
});

onUnmounted(() => {
  if (debtorTimer) clearTimeout(debtorTimer);
  if (invoiceTimer) clearTimeout(invoiceTimer);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs text-slate-600 hover:bg-slate-50"
          @click="goBack"
          aria-label="Back"
        >
          <ChevronLeft class="h-3.5 w-3.5" />
          Back
        </button>
        <h1 class="page-title">Account Receivable / Discount Note Form</h1>
      </div>

      <!-- Discount Note Head -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header
          class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5"
        >
          <h2 class="text-sm font-semibold text-slate-800">
            Discount Note Head
          </h2>
          <button
            v-if="!isReadonly && dcId"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
            :disabled="loading"
            @click="loadDiscountNote"
          >
            <RefreshCcw class="h-3.5 w-3.5" />
            Reload
          </button>
        </header>

        <section class="grid gap-x-6 gap-y-3 p-4 md:grid-cols-2">
          <!-- Discount Note No -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Discount Note No <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <input
              v-model="head.dcm_dcnote_no"
              type="text"
              readonly
              class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-600"
              placeholder="Auto-generated on save"
            />
          </div>

          <!-- Date -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Date <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <input
              v-model="head.dcm_dcnote_date"
              type="text"
              :disabled="isReadonly"
              class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
              placeholder="dd/mm/yyyy"
            />
          </div>

          <!-- Customer Type -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Customer Type <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <select
              :value="head.dcm_cust_type ?? ''"
              :disabled="isReadonly"
              class="flex-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
              @change="
                onCustomerTypeChange(($event.target as HTMLSelectElement).value)
              "
            >
              <option value="">-- Select Customer Type --</option>
              <option
                v-for="opt in customerTypes"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>
          <div aria-hidden="true"><!-- spacer --></div>

          <!-- Customer Name (autosuggest) -->
          <div class="flex items-start gap-3 md:col-span-2">
            <label
              class="w-40 shrink-0 pt-1.5 text-xs font-medium text-slate-700"
              >Customer Name <span class="text-rose-500">*</span></label
            >
            <span class="pt-1.5 text-slate-400">:</span>
            <div class="relative flex-1">
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  :value="debtorQuery"
                  type="search"
                  :disabled="isReadonly"
                  class="w-full rounded-md border border-slate-300 py-1.5 pl-7 pr-8 text-sm"
                  placeholder="Type to search customer (code or name)"
                  autocomplete="off"
                  @input="onDebtorInput(($event.target as HTMLInputElement).value)"
                  @focus="debtorOpen = true"
                  @blur="closeDebtorSoon"
                />
                <button
                  v-if="debtorQuery && !isReadonly"
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                  title="Clear"
                  @click="clearDebtor"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>

              <div
                v-if="debtorOpen && !isReadonly"
                class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-md border border-slate-200 bg-white text-sm shadow-lg"
              >
                <div
                  v-if="debtorLoading"
                  class="flex items-center gap-2 px-3 py-2 text-xs text-slate-500"
                >
                  <Loader2 class="h-3.5 w-3.5 animate-spin" />
                  Searching...
                </div>
                <div
                  v-else-if="debtorResults.length === 0"
                  class="px-3 py-2 text-xs text-slate-500"
                >
                  {{
                    debtorQuery
                      ? "No matches found."
                      : "Type at least one character to search."
                  }}
                </div>
                <button
                  v-for="opt in debtorResults"
                  :key="opt.value"
                  type="button"
                  class="block w-full cursor-pointer px-3 py-1.5 text-left text-xs hover:bg-slate-50"
                  @mousedown.prevent="pickDebtor(opt)"
                >
                  <div class="font-medium text-slate-800">{{ opt.name }}</div>
                  <div class="text-slate-500">{{ opt.code }}</div>
                </button>
              </div>
            </div>
          </div>

          <!-- Customer ID -->
          <div class="flex items-center gap-3 md:col-span-2">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Customer ID <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <input
              :value="head.dcm_cust_id ?? ''"
              type="text"
              readonly
              class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-600"
              placeholder="Auto-filled from Customer Name"
            />
          </div>

          <!-- Discount Policy -->
          <div class="flex items-center gap-3 md:col-span-2">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Discount Policy <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <select
              :value="head.dcp_dc_policy_id ?? ''"
              :disabled="isReadonly"
              class="flex-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
              @change="
                onPolicyChange(($event.target as HTMLSelectElement).value)
              "
            >
              <option value="">-- Select Discount Policy --</option>
              <option
                v-for="opt in discountPolicies"
                :key="opt.value"
                :value="opt.value"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <!-- Invoice No (autosuggest) -->
          <div class="flex items-start gap-3 md:col-span-2">
            <label
              class="w-40 shrink-0 pt-1.5 text-xs font-medium text-slate-700"
              >Invoice No <span class="text-rose-500">*</span></label
            >
            <span class="pt-1.5 text-slate-400">:</span>
            <div class="relative flex-1">
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  :value="invoiceQuery"
                  type="search"
                  :disabled="isReadonly || !head.dcm_cust_id"
                  class="w-full rounded-md border border-slate-300 py-1.5 pl-7 pr-8 text-sm disabled:bg-slate-50"
                  :placeholder="
                    head.dcm_cust_id
                      ? 'Type to search invoice no'
                      : 'Select Customer Name first'
                  "
                  autocomplete="off"
                  @input="onInvoiceInput(($event.target as HTMLInputElement).value)"
                  @focus="onInvoiceFocus"
                  @blur="closeInvoiceSoon"
                />
                <button
                  v-if="invoiceQuery && !isReadonly"
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                  title="Clear"
                  @click="clearInvoice"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>

              <div
                v-if="invoiceOpen && !isReadonly && head.dcm_cust_id"
                class="absolute left-0 right-0 top-full z-20 mt-1 max-h-64 overflow-y-auto rounded-md border border-slate-200 bg-white text-sm shadow-lg"
              >
                <div
                  v-if="invoiceLoading"
                  class="flex items-center gap-2 px-3 py-2 text-xs text-slate-500"
                >
                  <Loader2 class="h-3.5 w-3.5 animate-spin" />
                  Searching...
                </div>
                <div
                  v-else-if="invoiceResults.length === 0"
                  class="px-3 py-2 text-xs text-slate-500"
                >
                  No approved invoices for this customer.
                </div>
                <button
                  v-for="opt in invoiceResults"
                  :key="opt.value"
                  type="button"
                  class="block w-full cursor-pointer px-3 py-1.5 text-left text-xs hover:bg-slate-50"
                  @mousedown.prevent="pickInvoice(opt)"
                >
                  <div class="font-medium text-slate-800">
                    {{ opt.invoiceNo }}
                  </div>
                  <div class="text-slate-500">{{ opt.label }}</div>
                </button>
              </div>
            </div>
            <button
              v-if="!isReadonly && head.cim_cust_invoice_id"
              type="button"
              class="shrink-0 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              :disabled="loading"
              @click="loadInvoiceLines"
              title="Reload invoice lines"
            >
              <RefreshCcw class="h-3.5 w-3.5" />
            </button>
          </div>

          <!-- Description -->
          <div class="flex items-start gap-3 md:col-span-2">
            <label class="w-40 shrink-0 pt-1.5 text-xs font-medium text-slate-700"
              >Discount Note Description</label
            >
            <span class="pt-1.5 text-slate-400">:</span>
            <textarea
              v-model="head.dcm_dcnote_desc"
              :disabled="isReadonly"
              rows="2"
              class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
            />
          </div>

          <!-- Status | Total DC -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Status</label
            >
            <span class="text-slate-400">:</span>
            <input
              :value="head.dcm_status_cd ?? 'DRAFT'"
              type="text"
              readonly
              class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium uppercase text-slate-700"
            />
          </div>
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Total DC</label
            >
            <span class="text-slate-400">:</span>
            <div class="flex flex-1 items-stretch">
              <span
                class="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-100 px-2.5 text-xs font-medium text-slate-600"
                >MYR</span
              >
              <input
                :value="fmtMoney(totalDiscountAmount)"
                type="text"
                readonly
                class="flex-1 rounded-r-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-right text-sm tabular-nums text-slate-700"
              />
            </div>
          </div>
        </section>
      </article>

      <!-- Debit datatable (15 cols) -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-4 py-2.5">
          <h2 class="text-sm font-semibold text-slate-800">Debit</h2>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1400px] text-xs">
            <thead class="bg-slate-50 text-slate-700">
              <tr class="border-b border-slate-200 text-left">
                <th class="px-2 py-2 font-semibold">No</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Category</th>
                <th class="px-2 py-2 font-semibold">Fee<br />Item</th>
                <th class="px-2 py-2 font-semibold">Fund</th>
                <th class="px-2 py-2 font-semibold">Activity</th>
                <th class="px-2 py-2 font-semibold">OU</th>
                <th class="px-2 py-2 font-semibold">Costcentre</th>
                <th class="px-2 py-2 font-semibold">Code<br />SO</th>
                <th class="px-2 py-2 font-semibold">Account<br />Code</th>
                <th class="px-2 py-2 font-semibold">Tax<br />Code</th>
                <th class="px-2 py-2 text-right font-semibold">
                  Tax<br />Amount
                </th>
                <th class="px-2 py-2 text-right font-semibold">
                  Invoice<br />Balance
                </th>
                <th class="px-2 py-2 font-semibold">Discount<br />Type</th>
                <th class="px-2 py-2 text-right font-semibold">DC<br />Amount</th>
                <th class="px-2 py-2 text-right font-semibold">
                  DC Tax<br />Amount
                </th>
                <th v-if="!isReadonly" class="px-2 py-2 font-semibold">
                  Action
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="debit.length === 0">
                <td
                  :colspan="isReadonly ? 15 : 16"
                  class="px-2 py-8 text-center text-slate-500"
                >
                  No records
                </td>
              </tr>
              <tr
                v-for="(line, i) in debit"
                :key="`d-${line.dcd_id ?? line.ID ?? i}`"
                class="border-b border-slate-100 hover:bg-slate-50/60"
              >
                <td class="px-2 py-1.5">{{ i + 1 }}</td>
                <td class="px-2 py-1.5">{{ line.feeCategory ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.feeItem ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.fundType ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.activityCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.ptjCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.costcentre ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.codeSO ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.acctCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.taxCode ?? "-" }}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.taxAmt) }}
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.balance) }}
                </td>
                <td class="px-2 py-1.5">{{ line.dcType ?? "-" }}</td>
                <td class="px-2 py-1.5 text-right">
                  <input
                    v-model.number="line.dcAmt"
                    type="number"
                    :disabled="isReadonly"
                    min="0"
                    step="0.01"
                    class="w-24 rounded border border-slate-300 px-2 py-1 text-right text-xs tabular-nums"
                  />
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(Number(line.dcTaxAmt ?? 0)) }}
                </td>
                <td v-if="!isReadonly" class="px-2 py-1.5">
                  <button
                    type="button"
                    title="Remove"
                    class="rounded p-1 text-rose-500 hover:bg-rose-50"
                    @click="removeDebitLine(i)"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <!-- Credit datatable (15 cols) -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-4 py-2.5">
          <h2 class="text-sm font-semibold text-slate-800">Credit</h2>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1400px] text-xs">
            <thead class="bg-slate-50 text-slate-700">
              <tr class="border-b border-slate-200 text-left">
                <th class="px-2 py-2 font-semibold">No</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Category</th>
                <th class="px-2 py-2 font-semibold">Fee<br />Item</th>
                <th class="px-2 py-2 font-semibold">Fund</th>
                <th class="px-2 py-2 font-semibold">Activity</th>
                <th class="px-2 py-2 font-semibold">OU</th>
                <th class="px-2 py-2 font-semibold">Costcentre</th>
                <th class="px-2 py-2 font-semibold">Code<br />SO</th>
                <th class="px-2 py-2 font-semibold">Account<br />Code</th>
                <th class="px-2 py-2 font-semibold">Tax<br />Code</th>
                <th class="px-2 py-2 text-right font-semibold">
                  Tax<br />Amount
                </th>
                <th class="px-2 py-2 text-right font-semibold">
                  Invoice<br />Balance
                </th>
                <th class="px-2 py-2 text-right font-semibold">DC<br />Amount</th>
                <th class="px-2 py-2 text-right font-semibold">
                  DC Tax<br />Amount
                </th>
                <th class="px-2 py-2 text-right font-semibold">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="credit.length === 0">
                <td colspan="15" class="px-2 py-8 text-center text-slate-500">
                  No records
                </td>
              </tr>
              <tr
                v-for="(line, i) in credit"
                :key="`c-${line.dcd_id ?? line.ID ?? i}`"
                class="border-b border-slate-100"
              >
                <td class="px-2 py-1.5">{{ i + 1 }}</td>
                <td class="px-2 py-1.5">{{ line.feeCategory ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.feeItem ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.fundType ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.activityCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.ptjCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.costcentre ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.codeSO ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.acctCode ?? "-" }}</td>
                <td class="px-2 py-1.5">{{ line.taxCode ?? "-" }}</td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.taxAmt) }}
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.amt) }}
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.dcAmt) }}
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(Number(line.dcTaxAmt ?? 0)) }}
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.balance) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>

      <!-- Action bar -->
      <div
        v-if="!isReadonly"
        class="flex items-center justify-center gap-2 py-2"
      >
        <button
          v-if="canCancel"
          type="button"
          class="inline-flex items-center gap-1 rounded-md border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-60"
          :disabled="cancelling"
          @click="handleCancel"
        >
          <Ban class="h-4 w-4" />
          Cancel Note
        </button>
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
          :disabled="saving"
          @click="handleSave"
        >
          <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
          <Save v-else class="h-4 w-4" />
          Save
        </button>
        <button
          v-if="canSubmit"
          type="button"
          class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
          :disabled="submitting"
          @click="handleSubmit"
        >
          <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
          <Send v-else class="h-4 w-4" />
          Submit
        </button>
      </div>
      <div v-else class="flex items-center justify-center gap-2 py-2">
        <button
          type="button"
          class="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          @click="goBack"
        >
          <ArrowLeft class="h-4 w-4" />
          Back
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
