<script setup lang="ts">
/**
 * Account Receivable / Debit Note Form (PAGEID 1462 / MENUID 1783).
 *
 * Source: FIMS BL `DT_AR_DEBIT_NOTE_FORM` + `PAGE_SECOND_LEVEL_MENU.json`
 * entries for MENUID 1783 (Debit Note Head / Debit / Credit / Action).
 *
 * Mirrors `CreditNoteFormView.vue` (MENUID 1782) — same search-and-pick
 * Customer (debtor) / Invoice combobox UX, same save-then-submit flow —
 * but uses "Customer" terminology to match the legacy DN page and a
 * slightly narrower table (no DN Tax Amount column, per legacy JSON).
 *
 * Legacy layout this view replicates:
 *
 *   BREADCRUMB: Account Receivable / Debit Note Form
 *
 *   ┌─ Debit Note Head ────────────────────────────────────────────┐
 *   │  Debit Note No *   :  [disabled]     Date *   : [dd/mm/yyyy] │
 *   │  Customer Type *   :  [dropdown]                             │
 *   │  Customer Name *   :  [autosuggest, full row]                │
 *   │  Customer ID *     :  [auto-filled, full row]                │
 *   │  Invoice No *      :  [autosuggest, full row] [Reload]       │
 *   │  Debit Note Desc   :  [textarea, full row]                   │
 *   │  Status            :  DRAFT          Total DN : MYR x.xx     │
 *   └──────────────────────────────────────────────────────────────┘
 *
 *   ┌─ Debit ──────────────────────────────────────────────────────┐
 *   │ No | Rev Cat | Rev Item | Fund | Activity | OU | Costcentre  │
 *   │    | Code SO | Account Code | Tax Code | Tax Amt | Inv Amt  │
 *   │    | DN Amount (editable) | Balance | Action                │
 *   └──────────────────────────────────────────────────────────────┘
 *
 *   ┌─ Credit ─────────────────────────────────────────────────────┐
 *   │ (same columns, read-only — audit trail of invoice credits)   │
 *   └──────────────────────────────────────────────────────────────┘
 *
 *   Action: [ Cancel DN ]  [ Save ]  [ Submit ]
 *
 * Route entry:
 *   /admin/kerisi/m/1783                 → create
 *   /admin/kerisi/m/1783?id=X            → edit
 *   /admin/kerisi/m/1783?id=X&mode=view  → read-only
 *
 * Workflow caveat: `submit` / `cancel` are stubs on the backend — see
 * DebitNoteFormController docblock.
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
  cancelDebitNoteForm,
  getDebitNoteForm,
  getDebitNoteFormInvoiceLines,
  listDebtorTypes,
  saveDebitNoteForm,
  searchArDebtors,
  searchArInvoicesByDebtor,
  submitDebitNoteForm,
} from "@/api/cms";
import type {
  DebitNoteFormHead,
  DebtorSearchOption,
  InvoiceLine,
  InvoiceSearchOption,
  LookupOption,
  NoteFormLine,
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
const dnId = computed(() => {
  const raw = route.query.id;
  return typeof raw === "string" && raw !== "" ? raw : null;
});

const loading = ref(false);
const saving = ref(false);
const submitting = ref(false);
const cancelling = ref(false);

// `listDebtorTypes` is the shared CUSTOMER_TYPE lookup — legacy uses the
// same `lookup_details` source for both CN and DN pages.
const customerTypes = ref<LookupOption[]>([]);

const head = ref<DebitNoteFormHead>({
  dnm_debit_note_master_id: null,
  dnm_dnnote_no: null,
  cim_invoice_no: null,
  cim_cust_invoice_id: null,
  dnm_cust_id: null,
  dnm_cust_type: null,
  dnm_cust_type_desc: null,
  dnm_cust_name: null,
  dnm_dnnote_desc: null,
  dnm_dnnote_date: todayDdMmYyyy(),
  dnm_dn_total_amount: 0,
  dnm_status_cd: "DRAFT",
  dnm_status_cd_desc: "DRAFT",
  invoiceTotalAmount: 0,
  invoiceBalanceAmount: 0,
});
const debit = ref<NoteFormLine[]>([]);
const credit = ref<NoteFormLine[]>([]);

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
    // Pass selected `Customer Type` so the backend filters by
    // creditor/debtor flag — mirrors legacy `BL_AUTOSUGGEST_RECC_FEE`.
    const res = await searchArDebtors(
      term,
      head.value.dnm_cust_type ?? "",
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
  head.value.dnm_cust_name = v || null;
  if (!v) head.value.dnm_cust_id = null;
  if (debtorTimer) clearTimeout(debtorTimer);
  debtorTimer = setTimeout(() => {
    void runDebtorSearch(v.trim());
  }, 300);
}

function pickDebtor(opt: DebtorSearchOption) {
  head.value.dnm_cust_id = opt.code;
  head.value.dnm_cust_name = opt.name;
  debtorQuery.value = opt.name;
  debtorOpen.value = false;
  clearInvoice();
}

function clearDebtor() {
  head.value.dnm_cust_id = null;
  head.value.dnm_cust_name = null;
  debtorQuery.value = "";
  debtorResults.value = [];
  debtorOpen.value = false;
  clearInvoice();
}

async function runInvoiceSearch(term: string) {
  const custId = head.value.dnm_cust_id ?? "";
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
  await loadInvoiceLines();
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
  if (!head.value.dnm_cust_id) return;
  invoiceOpen.value = true;
  void runInvoiceSearch(invoiceQuery.value.trim());
}

// Deferred close handlers for combobox blur — delay so a click on an item
// still registers the `@mousedown` pick handler before the list hides.
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

const totalDebitAmount = computed(() =>
  debit.value.reduce((sum, l) => sum + Number(l.dnAmt ?? 0), 0),
);

/**
 * Submit is allowed whenever the form is editable and the note is either
 * brand-new (will be saved inside handleSubmit first) or still sitting in
 * DRAFT. Matches the legacy `submitbatch()` binding which always rendered
 * the Submit button.
 */
const canSubmit = computed(() => {
  if (isReadonly.value) return false;
  const status = (head.value.dnm_status_cd ?? "DRAFT").toUpperCase();
  return !dnId.value || status === "DRAFT";
});

const canCancel = computed(
  () =>
    !isReadonly.value &&
    !!dnId.value &&
    ["DRAFT", "ENTRY"].includes(
      (head.value.dnm_status_cd ?? "").toUpperCase(),
    ),
);

function invoiceLineToFormLine(line: InvoiceLine): NoteFormLine {
  return {
    ID: line.ID,
    feeCategoryId: line.feeCategoryId,
    feeCategory: line.feeCategory,
    cii_item_code: line.cii_item_code,
    feeItem: line.feeItem,
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
    dnAmt: 0,
  };
}

async function loadDebitNote() {
  if (!dnId.value) return;
  loading.value = true;
  try {
    const res = await getDebitNoteForm(dnId.value);
    head.value = res.data.head;
    debtorQuery.value = head.value.dnm_cust_name ?? "";
    invoiceQuery.value = head.value.cim_invoice_no ?? "";
    debit.value = res.data.debit;
    credit.value = res.data.credit;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load debit note.",
    );
  } finally {
    loading.value = false;
  }
}

async function loadInvoiceLines() {
  const invoiceId = head.value.cim_cust_invoice_id ?? "";
  if (!invoiceId) {
    toast.error("Missing invoice", "Select an Invoice No from the dropdown.");
    return;
  }
  loading.value = true;
  try {
    const res = await getDebitNoteFormInvoiceLines(invoiceId);
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
  head.value.dnm_cust_type = value;
  const match = customerTypes.value.find((o) => o.value === value);
  head.value.dnm_cust_type_desc = match?.label ?? null;
  // Changing the customer type invalidates the current customer / invoice
  // selection because the autosuggest is scoped to the selected type.
  clearDebtor();
}

function buildSavePayload() {
  const toSavedLine = (l: NoteFormLine) => ({
    dnd_cust_invoice_detl_id: l.ID,
    dnd_item_category: l.feeCategoryId,
    cii_item_code: l.cii_item_code,
    cnd_detail_desc: l.cnd_detail_desc ?? null,
    fty_fund_type: l.fty_fund_type,
    at_activity_code: l.at_activity_code,
    oun_code: l.oun_code,
    ccr_costcentre: l.ccr_costcentre,
    cpa_project_no: l.cpa_project_no,
    acm_acct_code: l.acm_acct_code,
    dnd_taxcode: l.taxCode,
    dnd_invoice_amt: Number(l.amt ?? 0),
    dnd_dnnote_amt: Number(l.dnAmt ?? 0),
    dnd_dn_taxamt: Number(l.taxAmt ?? 0),
    dnd_bal_amt: Number(l.balance ?? 0),
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
    head: { ...head.value, dnm_dn_total_amount: totalDebitAmount.value },
    debit: debit.value.map(toSavedLine),
    credit: credit.value.map(toSavedLine),
  };
}

function validateHead(): boolean {
  if (!head.value.dnm_dnnote_date) {
    toast.error("Missing date", "Debit Note date is required.");
    return false;
  }
  if (!head.value.dnm_cust_type) {
    toast.error("Missing customer type", "Customer Type is required.");
    return false;
  }
  if (!head.value.dnm_cust_id || !head.value.dnm_cust_name) {
    toast.error(
      "Missing customer",
      "Select a Customer Name from the dropdown.",
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

/**
 * Save the debit note and return the resolved note ID (new or existing).
 * Used standalone by the Save button and internally by handleSubmit so that
 * the legacy `submitbatch()` flow (save then submit) works on new records.
 */
async function persistDraft(): Promise<string | null> {
  if (!validateHead()) return null;

  saving.value = true;
  try {
    const res = await saveDebitNoteForm(buildSavePayload());
    head.value.dnm_debit_note_master_id = res.data.dnID;
    head.value.dnm_dnnote_no = res.data.debitNoteNo;
    head.value.dnm_status_cd = res.data.status_cd;
    head.value.dnm_status_cd_desc = res.data.status_cd;
    if (!dnId.value) {
      await router.replace({
        path: "/admin/kerisi/m/1783",
        query: { id: res.data.dnID, mode: "edit" },
      });
    }
    return res.data.dnID;
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save debit note.",
    );
    return null;
  } finally {
    saving.value = false;
  }
}

async function handleSave() {
  const id = await persistDraft();
  if (id) toast.success("Saved", `Debit note ${head.value.dnm_dnnote_no ?? id} saved.`);
}

async function handleSubmit() {
  const ok = await confirm({
    title: "Submit debit note?",
    message:
      "Submit will save and mark this debit note as Entry. Note: FIMS workflow routing is not yet migrated, so no approver task will be created.",
    confirmText: "Submit",
  });
  if (!ok) return;

  // Save first (covers brand-new notes and any in-flight edits).
  const id = dnId.value ?? (await persistDraft());
  if (!id) return;

  submitting.value = true;
  try {
    const res = await submitDebitNoteForm(id);
    const payload = res.data as { status_cd?: string; message?: string };
    head.value.dnm_status_cd = payload.status_cd ?? "Entry";
    head.value.dnm_status_cd_desc = payload.status_cd ?? "Entry";
    toast.success("Submitted", payload.message ?? "Debit note submitted.");
  } catch (e) {
    toast.error(
      "Submit failed",
      e instanceof Error ? e.message : "Unable to submit debit note.",
    );
  } finally {
    submitting.value = false;
  }
}

async function handleCancel() {
  if (!dnId.value) return;
  const ok = await confirm({
    title: "Cancel debit note?",
    message:
      "This will mark the debit note as Cancelled. Enter a reason in the dialog that follows.",
    confirmText: "Cancel DN",
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
    const res = await cancelDebitNoteForm(dnId.value, reason.trim());
    const payload = res.data as { status_cd?: string; message?: string };
    head.value.dnm_status_cd = payload.status_cd ?? "CANCELLED";
    head.value.dnm_status_cd_desc = "Cancelled";
    toast.success("Cancelled", payload.message ?? "Debit note cancelled.");
  } catch (e) {
    toast.error(
      "Cancel failed",
      e instanceof Error ? e.message : "Unable to cancel debit note.",
    );
  } finally {
    cancelling.value = false;
  }
}

function goBack() {
  void router.push("/admin/kerisi/m/1042");
}

async function loadCustomerTypes() {
  try {
    const res = await listDebtorTypes();
    customerTypes.value = res.data;
  } catch {
    customerTypes.value = [];
  }
}

watch(
  () => route.query.id,
  () => {
    if (dnId.value) void loadDebitNote();
  },
);

onMounted(async () => {
  await loadCustomerTypes();
  if (dnId.value) {
    await loadDebitNote();
  }
});

onUnmounted(() => {
  if (debtorTimer) clearTimeout(debtorTimer);
  if (invoiceTimer) clearTimeout(invoiceTimer);
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-[1600px] space-y-4">
      <p class="text-sm font-medium text-slate-500">
        <button
          type="button"
          class="mr-2 inline-flex items-center rounded border border-slate-300 bg-white p-1 text-slate-500 hover:bg-slate-50"
          @click="goBack"
          aria-label="Back"
        >
          <ChevronLeft class="h-3.5 w-3.5" />
        </button>
        Account Receivable / <span class="text-slate-700">Debit Note Form</span>
      </p>

      <!-- Debit Note Head -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header
          class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5"
        >
          <h2 class="text-sm font-semibold text-slate-800">Debit Note Head</h2>
          <button
            v-if="!isReadonly && dnId"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
            :disabled="loading"
            @click="loadDebitNote"
          >
            <RefreshCcw class="h-3.5 w-3.5" />
            Reload
          </button>
        </header>

        <section class="grid gap-x-6 gap-y-3 p-4 md:grid-cols-2">
          <!-- Debit Note No -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Debit Note No <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <input
              v-model="head.dnm_dnnote_no"
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
              v-model="head.dnm_dnnote_date"
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
              :value="head.dnm_cust_type ?? ''"
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
          <div aria-hidden="true"><!-- spacer for 2-col grid --></div>

          <!-- Customer Name autosuggest combobox -->
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

          <!-- Customer ID (auto-filled from Customer Name; read-only on legacy) -->
          <div class="flex items-center gap-3 md:col-span-2">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Customer ID <span class="text-rose-500">*</span></label
            >
            <span class="text-slate-400">:</span>
            <input
              :value="head.dnm_cust_id ?? ''"
              type="text"
              readonly
              class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-600"
              placeholder="Auto-filled from Customer Name"
            />
          </div>

          <!-- Invoice No autosuggest combobox -->
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
                  :disabled="isReadonly || !head.dnm_cust_id"
                  class="w-full rounded-md border border-slate-300 py-1.5 pl-7 pr-8 text-sm disabled:bg-slate-50"
                  :placeholder="
                    head.dnm_cust_id
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
                v-if="invoiceOpen && !isReadonly && head.dnm_cust_id"
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

          <!-- Debit Note Description -->
          <div class="flex items-start gap-3 md:col-span-2">
            <label class="w-40 shrink-0 pt-1.5 text-xs font-medium text-slate-700"
              >Debit Note Description</label
            >
            <span class="pt-1.5 text-slate-400">:</span>
            <textarea
              v-model="head.dnm_dnnote_desc"
              :disabled="isReadonly"
              rows="2"
              class="flex-1 rounded-md border border-slate-300 px-3 py-1.5 text-sm"
            />
          </div>

          <!-- Status | Total DN -->
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Status</label
            >
            <span class="text-slate-400">:</span>
            <input
              :value="head.dnm_status_cd ?? 'DRAFT'"
              type="text"
              readonly
              class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-medium uppercase text-slate-700"
            />
          </div>
          <div class="flex items-center gap-3">
            <label class="w-40 shrink-0 text-xs font-medium text-slate-700"
              >Total DN</label
            >
            <span class="text-slate-400">:</span>
            <div class="flex flex-1 items-stretch">
              <span
                class="inline-flex items-center rounded-l-md border border-r-0 border-slate-300 bg-slate-100 px-2.5 text-xs font-medium text-slate-600"
                >MYR</span
              >
              <input
                :value="fmtMoney(totalDebitAmount)"
                type="text"
                readonly
                class="flex-1 rounded-r-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-right text-sm tabular-nums text-slate-700"
              />
            </div>
          </div>
        </section>
      </article>

      <!-- Debit datatable -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-4 py-2.5">
          <h2 class="text-sm font-semibold text-slate-800">Debit</h2>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1300px] text-xs">
            <thead class="bg-slate-50 text-slate-700">
              <tr class="border-b border-slate-200 text-left">
                <th class="px-2 py-2 font-semibold">No</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Category</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Item</th>
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
                  Invoice<br />Amount
                </th>
                <th class="px-2 py-2 text-right font-semibold">DN Amount</th>
                <th class="px-2 py-2 text-right font-semibold">Balance</th>
                <th v-if="!isReadonly" class="px-2 py-2 font-semibold">
                  Action
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="debit.length === 0">
                <td
                  :colspan="isReadonly ? 14 : 15"
                  class="px-2 py-8 text-center text-slate-500"
                >
                  No records
                </td>
              </tr>
              <tr
                v-for="(line, i) in debit"
                :key="`d-${line.dnd_id ?? line.ID ?? i}`"
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
                  {{ fmtMoney(line.amt) }}
                </td>
                <td class="px-2 py-1.5 text-right">
                  <input
                    v-model.number="line.dnAmt"
                    type="number"
                    :disabled="isReadonly"
                    min="0"
                    step="0.01"
                    class="w-24 rounded border border-slate-300 px-2 py-1 text-right text-xs tabular-nums"
                  />
                </td>
                <td class="px-2 py-1.5 text-right tabular-nums">
                  {{ fmtMoney(line.balance) }}
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

      <!-- Credit datatable -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="border-b border-slate-100 px-4 py-2.5">
          <h2 class="text-sm font-semibold text-slate-800">Credit</h2>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[1300px] text-xs">
            <thead class="bg-slate-50 text-slate-700">
              <tr class="border-b border-slate-200 text-left">
                <th class="px-2 py-2 font-semibold">No</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Category</th>
                <th class="px-2 py-2 font-semibold">Revenue<br />Item</th>
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
                  Invoice<br />Amount
                </th>
                <th class="px-2 py-2 text-right font-semibold">DN Amount</th>
                <th class="px-2 py-2 text-right font-semibold">Balance</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="credit.length === 0">
                <td colspan="14" class="px-2 py-8 text-center text-slate-500">
                  No records
                </td>
              </tr>
              <tr
                v-for="(line, i) in credit"
                :key="`c-${line.dnd_id ?? line.ID ?? i}`"
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
                  {{ fmtMoney(line.dnAmt) }}
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
          Cancel DN
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
