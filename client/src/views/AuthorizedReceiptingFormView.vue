<script setup lang="ts">
/**
 * Account Receivable / Authorized Receipting Form (MENUID 1953).
 *
 * Legacy source: BL `V2_AUTHORIZED_RECEIPTING_FORM_API` + the `onload`
 * trigger (FLC_TRIGGER_PAGE PAGEID 1614). The page has 4 distinct parts:
 *
 *   1. "Details" card — top half is read-only, populated from the logged-in
 *      user's `staff` + `staff_service` record (legacy `$_USER['...']`
 *      superglobal). Bottom half is editable: Collection Type dropdown
 *      (EVENT / GENERAL), Event autosuggest (shown only when Collection
 *      Type = EVENT), Collection Purpose, Status (read-only DRAFT).
 *   2. "Authorized Staff" grid — a list of staff authorised to issue
 *      offline receipts for the duration. Rows are added via a modal that
 *      autosuggests from `staff` + `staff_service`; the selected row fills
 *      No KP / PTJ / Position / Jobcode / Contact / Fax automatically.
 *   3. "Process Flow" grid — workflow history. The legacy SPs that drive
 *      this (`workflowSubmit`, `wf_task_history`) are NOT migrated yet, so
 *      for now we render a read-only template of the 4 standard stages
 *      (ENDORSE by PTJ → VERIFICATION → ENDORSE by BURSAR → APPROVAL).
 *   4. Footer actions — "Save" (draft) and "Save & Submit" (save-then-submit).
 *
 * Workflow caveat: Submit & Cancel just flip `are_status` and return
 * `workflow_stub=true` — no approver task is created. See
 * `AuthorizedReceiptingFormController` docblock.
 */
import { computed, onMounted, ref, watch } from "vue";
import {
  ArrowLeft,
  Ban,
  ChevronLeft,
  Loader2,
  Plus,
  RefreshCcw,
  Save,
  Send,
  Trash2,
  X,
} from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import {
  cancelAuthorizedReceiptingForm,
  getAuthorizedReceiptingForm,
  getAuthorizedReceiptingFormProcessFlow,
  getCurrentStaffProfile,
  saveAuthorizedReceiptingForm,
  searchArAuthorizedStaff,
  searchArEvents,
  submitAuthorizedReceiptingForm,
} from "@/api/cms";
import type {
  ArEventSearchOption,
  ArStaffSearchOption,
  AuthorizedReceiptingFormHead,
  AuthorizedReceiptingStaffRow,
  CurrentStaffProfile,
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
const areId = computed(() => {
  const raw = route.query.id;
  return typeof raw === "string" && raw !== "" ? raw : null;
});

const loading = ref(false);
const saving = ref(false);
const submitting = ref(false);
const cancelling = ref(false);

// Collection-type (aka `are_purposed_code`) options — legacy hard-coded
// dropdown: `SELECT 'EVENT', 'GENERAL'`.
const collectionTypes = [
  { value: "EVENT", label: "EVENT" },
  { value: "GENERAL", label: "GENERAL" },
];

// Read-only process-flow template used when the legacy workflow tables are
// empty (current state: Wave B — workflow not migrated). The screenshot of
// the legacy page shows these exact 4 steps in the same order even before
// any task is raised, so we keep them hard-coded here. Once
// `/process-flow` starts returning real rows, `processFlowRows` below will
// prefer those over the template.
const processFlowTemplate = [
  { no: 1, process: "ENDORSE by PTJ", by: null, ptj: null, email: null, telefon: null, status: null, comment: null, date: null },
  { no: 2, process: "VERIFICATION", by: null, ptj: null, email: null, telefon: null, status: null, comment: null, date: null },
  { no: 3, process: "ENDORSE by BURSAR", by: null, ptj: null, email: null, telefon: null, status: null, comment: null, date: null },
  { no: 4, process: "APPROVAL", by: null, ptj: null, email: null, telefon: null, status: null, comment: null, date: null },
];

const currentStaff = ref<CurrentStaffProfile>({
  stf_staff_id: null,
  stf_staff_name: null,
  stf_ic_no: null,
  stf_email_addr: null,
  oun_code_ptj: null,
  oun_code_ptj_desc: null,
  stf_position: null,
  stf_position_desc: null,
  stf_employment_status: null,
  stf_jobcode: null,
  stf_job_desc: null,
  stf_telno_work: null,
  stf_fax_no: null,
  resolved: false,
});

const head = ref<AuthorizedReceiptingFormHead>({
  are_authorized_receipting_id: null,
  are_application_no: null,
  stf_staff_id: null,
  stf_staff_name: null,
  oun_code_ptj: null,
  oun_code_ptj_desc: null,
  are_position_code: null,
  are_event_code: null,
  are_reason: null,
  are_purposed_code: "EVENT",
  are_employment_code: null,
  are_duration_from: null,
  are_duration_to: null,
  are_status: "DRAFT",
  are_counter_no: null,
  are_receipt_type: null,
});
const dtAuthorized = ref<AuthorizedReceiptingStaffRow[]>([]);
const requestDate = ref<string>(formatToday());
const processFlowRows = ref<Array<Record<string, unknown>>>([]);

function formatToday(): string {
  const d = new Date();
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  return `${dd}/${mm}/${d.getFullYear()}`;
}

function formatAnyDate(raw: string | null | undefined): string {
  if (!raw) return "";
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) return raw;
  const parsed = new Date(raw);
  if (Number.isNaN(parsed.getTime())) return String(raw);
  const dd = String(parsed.getDate()).padStart(2, "0");
  const mm = String(parsed.getMonth() + 1).padStart(2, "0");
  return `${dd}/${mm}/${parsed.getFullYear()}`;
}

const isEventMode = computed(() => head.value.are_purposed_code === "EVENT");

const canSubmit = computed(
  () =>
    !isReadonly.value &&
    ["DRAFT", "", null].includes(head.value.are_status as string | null),
);

const canCancel = computed(
  () =>
    !isReadonly.value &&
    !!areId.value &&
    ["DRAFT", "ENTRY", "ENDORSED"].includes(
      (head.value.are_status ?? "").toUpperCase(),
    ),
);

const processFlowDisplay = computed(() =>
  processFlowRows.value.length > 0 ? processFlowRows.value : processFlowTemplate,
);

// ─── Event autosuggest combobox ────────────────────────────────────────────
const eventQuery = ref("");
const eventResults = ref<ArEventSearchOption[]>([]);
const eventOpen = ref(false);
const eventLoading = ref(false);
let eventTimer: ReturnType<typeof setTimeout> | null = null;

async function runEventSearch(term: string) {
  eventLoading.value = true;
  try {
    const res = await searchArEvents(
      term,
      currentStaff.value.stf_staff_id ?? "",
    );
    eventResults.value = res.data;
    eventOpen.value = true;
  } catch {
    eventResults.value = [];
  } finally {
    eventLoading.value = false;
  }
}

function onEventInput(event: Event) {
  const value = (event.target as HTMLInputElement).value;
  eventQuery.value = value;
  if (eventTimer) clearTimeout(eventTimer);
  eventTimer = setTimeout(() => {
    void runEventSearch(value.trim());
  }, 350);
}

function onEventFocus() {
  if (eventResults.value.length === 0) {
    void runEventSearch(eventQuery.value.trim());
  } else {
    eventOpen.value = true;
  }
}

function closeEventSoon() {
  window.setTimeout(() => (eventOpen.value = false), 150);
}

function pickEvent(opt: ArEventSearchOption) {
  head.value.are_event_code = opt.projectNo;
  head.value.are_position_code = opt.ptj;
  eventQuery.value = opt.label;
  eventOpen.value = false;
}

function clearEvent() {
  head.value.are_event_code = null;
  head.value.are_position_code = null;
  eventQuery.value = "";
  eventResults.value = [];
  eventOpen.value = false;
}

function onCollectionTypeChange(value: string) {
  head.value.are_purposed_code = value;
  // Changing to GENERAL clears the event selection (legacy JS hides the
  // control and the reason becomes required instead).
  if (value !== "EVENT") {
    clearEvent();
  }
}

// ─── Authorized Staff modal (Add / Edit row) ───────────────────────────────
type StaffDraft = AuthorizedReceiptingStaffRow & { _editingIndex: number | null };

const staffModalOpen = ref(false);
const staffDraft = ref<StaffDraft>(emptyStaffDraft());

function emptyStaffDraft(): StaffDraft {
  return {
    _editingIndex: null,
    ors_id: undefined,
    ors_staff_id: "",
    ors_staff_name: "",
    ors_ic: "",
    ors_oun_code: "",
    ors_contact_no: "",
    ors_fax_no: "",
    ors_email: "",
    ors_position: "",
    sts_jobcode: "",
    ors_process_flag: "Y",
    ors_reason: "",
  };
}

function openStaffModal() {
  if (isReadonly.value) return;
  staffDraft.value = emptyStaffDraft();
  staffQuery.value = "";
  staffResults.value = [];
  staffOpen.value = false;
  staffModalOpen.value = true;
}

function openStaffModalForEdit(index: number) {
  if (isReadonly.value) return;
  const row = dtAuthorized.value[index];
  if (!row) return;
  staffDraft.value = {
    _editingIndex: index,
    ors_id: row.ors_id,
    ors_staff_id: row.ors_staff_id ?? "",
    ors_staff_name: row.ors_staff_name ?? "",
    ors_ic: row.ors_ic ?? "",
    ors_oun_code: row.ors_oun_code ?? "",
    ors_contact_no: row.ors_contact_no ?? "",
    ors_fax_no: row.ors_fax_no ?? "",
    ors_email: row.ors_email ?? "",
    ors_position: row.ors_position ?? "",
    sts_jobcode: row.sts_jobcode ?? "",
    ors_process_flag: row.ors_process_flag ?? "Y",
    ors_reason: row.ors_reason ?? "",
  };
  staffQuery.value = row.ors_staff_name
    ? `${row.ors_staff_id} - ${row.ors_staff_name}`
    : (row.ors_staff_id ?? "");
  staffResults.value = [];
  staffOpen.value = false;
  staffModalOpen.value = true;
}

function closeStaffModal() {
  staffModalOpen.value = false;
}

function saveStaffDraft() {
  if (!staffDraft.value.ors_staff_id) {
    toast.error(
      "Missing staff",
      "Select a staff member from the suggestion list before saving.",
    );
    return;
  }
  const { _editingIndex, ...row } = staffDraft.value;
  if (_editingIndex !== null && _editingIndex >= 0) {
    dtAuthorized.value.splice(_editingIndex, 1, row);
  } else {
    dtAuthorized.value.push(row);
  }
  staffModalOpen.value = false;
}

function removeStaffRow(i: number) {
  dtAuthorized.value.splice(i, 1);
}

// ─── Staff autosuggest inside the modal ────────────────────────────────────
const staffQuery = ref("");
const staffResults = ref<ArStaffSearchOption[]>([]);
const staffOpen = ref(false);
const staffLoading = ref(false);
let staffTimer: ReturnType<typeof setTimeout> | null = null;

async function runStaffSearch(term: string) {
  staffLoading.value = true;
  try {
    const res = await searchArAuthorizedStaff(
      term,
      currentStaff.value.oun_code_ptj ?? "",
    );
    staffResults.value = res.data;
    staffOpen.value = true;
  } catch {
    staffResults.value = [];
  } finally {
    staffLoading.value = false;
  }
}

function onStaffInput(event: Event) {
  const value = (event.target as HTMLInputElement).value;
  staffQuery.value = value;
  if (staffTimer) clearTimeout(staffTimer);
  staffTimer = setTimeout(() => {
    void runStaffSearch(value.trim());
  }, 350);
}

function onStaffFocus() {
  if (staffResults.value.length === 0) {
    void runStaffSearch(staffQuery.value.trim());
  } else {
    staffOpen.value = true;
  }
}

function closeStaffSoon() {
  window.setTimeout(() => (staffOpen.value = false), 150);
}

function pickStaff(opt: ArStaffSearchOption) {
  staffDraft.value.ors_staff_id = opt.staffId;
  staffDraft.value.ors_staff_name = opt.staffName ?? "";
  staffDraft.value.ors_ic = opt.ic ?? "";
  staffDraft.value.ors_oun_code = opt.ptj ?? "";
  staffDraft.value.ors_contact_no = opt.contact ?? "";
  staffDraft.value.ors_fax_no = opt.fax ?? "";
  staffDraft.value.ors_email = opt.email ?? "";
  staffDraft.value.ors_position = opt.position ?? "";
  staffDraft.value.sts_jobcode = opt.jobcode ?? "";
  staffQuery.value = opt.label;
  staffOpen.value = false;
}

// ─── Form lifecycle ────────────────────────────────────────────────────────
async function loadCurrentStaff() {
  try {
    const res = await getCurrentStaffProfile();
    currentStaff.value = res.data;
    // If we're creating a fresh record, auto-fill the fields the legacy JS
    // stamped from `$_USER`.
    if (!areId.value) {
      head.value.stf_staff_id = res.data.stf_staff_id;
      head.value.stf_staff_name = res.data.stf_staff_name;
      head.value.oun_code_ptj = res.data.oun_code_ptj;
      head.value.oun_code_ptj_desc = res.data.oun_code_ptj_desc;
      head.value.are_employment_code = res.data.stf_employment_status;
    }
  } catch (e) {
    toast.error(
      "Unable to resolve staff profile",
      e instanceof Error ? e.message : "Please log in again.",
    );
  }
}

async function loadForm() {
  if (!areId.value) return;
  loading.value = true;
  try {
    const res = await getAuthorizedReceiptingForm(areId.value);
    head.value = res.data.head;
    dtAuthorized.value = res.data.dt_authorized;
    eventQuery.value = head.value.are_event_code ?? "";
    requestDate.value = formatAnyDate(head.value.are_duration_from) || formatToday();
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load authorized receipting.",
    );
  } finally {
    loading.value = false;
  }
}

async function loadProcessFlow() {
  if (!areId.value) {
    processFlowRows.value = [];
    return;
  }
  try {
    const res = await getAuthorizedReceiptingFormProcessFlow(areId.value);
    processFlowRows.value = Array.isArray(res.data)
      ? (res.data as Array<Record<string, unknown>>)
      : [];
  } catch {
    processFlowRows.value = [];
  }
}

function buildPayload() {
  return {
    are_authorized_receipting_id: head.value.are_authorized_receipting_id,
    stf_staff_id: head.value.stf_staff_id,
    stf_staff_id_desc: head.value.stf_staff_name ?? null,
    oun_code_ptj: head.value.oun_code_ptj,
    are_position_code: head.value.are_position_code,
    are_event_code: head.value.are_event_code,
    are_reason: head.value.are_reason,
    are_purposed_code: head.value.are_purposed_code,
    are_employment_code: head.value.are_employment_code,
    are_duration_from: head.value.are_duration_from,
    are_duration_to: head.value.are_duration_to,
    are_status: head.value.are_status,
    extended: {
      oun_code_ptj_desc: head.value.oun_code_ptj_desc ?? null,
    },
    dt_authorized: dtAuthorized.value.map((r) => ({
      ors_id: r.ors_id,
      ors_staff_id: r.ors_staff_id,
      ors_staff_name: r.ors_staff_name ?? null,
      ors_ic: r.ors_ic ?? null,
      ors_oun_code: r.ors_oun_code ?? null,
      ors_contact_no: r.ors_contact_no ?? null,
      ors_fax_no: r.ors_fax_no ?? null,
      ors_email: r.ors_email ?? null,
      ors_position: r.ors_position ?? null,
      sts_jobcode: r.sts_jobcode ?? null,
      ors_process_flag: r.ors_process_flag ?? "Y",
      ors_reason: r.ors_reason ?? null,
    })),
  };
}

function validateBeforeSave(): boolean {
  if (!head.value.stf_staff_id) {
    toast.error(
      "Missing staff",
      "Your staff profile could not be resolved. Please contact the admin.",
    );
    return false;
  }
  if (!head.value.oun_code_ptj) {
    toast.error(
      "Missing PTJ",
      "Your PTJ (oun_code_ptj) is required and could not be resolved.",
    );
    return false;
  }
  if (!head.value.are_purposed_code) {
    toast.error("Missing collection type", "Select a Collection Type.");
    return false;
  }
  if (head.value.are_purposed_code === "EVENT" && !head.value.are_event_code) {
    toast.error("Missing event", "Select an Event when Collection Type is EVENT.");
    return false;
  }
  if (dtAuthorized.value.length === 0) {
    toast.error(
      "No authorized staff",
      'Add at least one authorized staff row before saving (click "+ New").',
    );
    return false;
  }
  for (const row of dtAuthorized.value) {
    if (!row.ors_staff_id) {
      toast.error(
        "Missing staff ID",
        "Each authorized row must have a staff selected.",
      );
      return false;
    }
  }
  return true;
}

async function persistDraft(): Promise<string | null> {
  const res = await saveAuthorizedReceiptingForm(buildPayload());
  head.value.are_authorized_receipting_id = res.data.are_authorized_receipting_id;
  head.value.are_application_no =
    res.data.are_application_no ?? head.value.are_application_no;
  head.value.are_status = res.data.are_status;
  if (!areId.value) {
    await router.replace({
      path: "/admin/kerisi/m/1953",
      query: {
        id: res.data.are_authorized_receipting_id,
        mode: "edit",
      },
    });
  }
  return res.data.are_authorized_receipting_id ?? null;
}

async function handleSave() {
  if (!validateBeforeSave()) return;
  saving.value = true;
  try {
    const id = await persistDraft();
    toast.success(
      "Draft saved",
      `Application ${head.value.are_application_no ?? id} saved.`,
    );
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save authorized receipting.",
    );
  } finally {
    saving.value = false;
  }
}

async function handleSubmit() {
  if (!validateBeforeSave()) return;
  const ok = await confirm({
    title: "Save & submit application?",
    message:
      "This will persist the draft and then mark the application as Entry. Note: FIMS workflow routing (AUTHORIZED_RECEIPT / AUTHORIZED_RCP_DP) is not yet migrated, so no approver task will be created.",
    confirmText: "Save & Submit",
  });
  if (!ok) return;

  submitting.value = true;
  try {
    // Save-then-submit (mirrors legacy "Save & Submit" button behaviour).
    const id = (await persistDraft()) ?? areId.value;
    if (!id) {
      toast.error("Submit failed", "Unable to persist draft before submit.");
      return;
    }
    const res = await submitAuthorizedReceiptingForm(id, buildPayload());
    const payload = res.data as { are_status?: string; message?: string };
    head.value.are_status = payload.are_status ?? "ENTRY";
    toast.success("Submitted", payload.message ?? "Application submitted.");
    await loadProcessFlow();
  } catch (e) {
    toast.error(
      "Submit failed",
      e instanceof Error ? e.message : "Unable to submit application.",
    );
  } finally {
    submitting.value = false;
  }
}

async function handleCancel() {
  if (!areId.value) return;
  const ok = await confirm({
    title: "Cancel application?",
    message:
      "This will mark the application as Cancelled. Enter a reason in the dialog that follows.",
    confirmText: "Cancel application",
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
    const res = await cancelAuthorizedReceiptingForm(
      areId.value,
      reason.trim(),
    );
    const payload = res.data as { are_status?: string; message?: string };
    head.value.are_status = payload.are_status ?? "CANCELLED";
    toast.success("Cancelled", payload.message ?? "Application cancelled.");
  } catch (e) {
    toast.error(
      "Cancel failed",
      e instanceof Error ? e.message : "Unable to cancel application.",
    );
  } finally {
    cancelling.value = false;
  }
}

function goBack() {
  void router.push("/admin/kerisi/m/1952");
}

watch(
  () => route.query.id,
  () => {
    if (areId.value) {
      void loadForm();
      void loadProcessFlow();
    }
  },
);

onMounted(async () => {
  await loadCurrentStaff();
  if (areId.value) {
    await loadForm();
    await loadProcessFlow();
  }
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">
        Account Receivable / Authorized Receipting Form
        <span v-if="mode !== 'create'" class="text-slate-700"
          >/ {{ head.are_application_no ?? areId }}</span
        >
      </h1>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <!-- Header bar -->
        <header
          class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3"
        >
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white p-1.5 text-slate-600 hover:bg-slate-50"
              @click="goBack"
            >
              <ChevronLeft class="h-4 w-4" />
            </button>
            <h1 class="text-base font-semibold text-slate-900">
              {{
                mode === "create"
                  ? "New Authorized Receipting"
                  : `Application ${head.are_application_no ?? areId}`
              }}
            </h1>
            <span
              v-if="head.are_status"
              class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
            >
              {{ head.are_status }}
            </span>
            <span
              v-if="head.are_counter_no"
              class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"
            >
              Counter: {{ head.are_counter_no }}
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              v-if="!isReadonly && areId"
              type="button"
              class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              :disabled="loading"
              @click="loadForm"
            >
              <RefreshCcw class="h-3.5 w-3.5" />
              Reload
            </button>
            <button
              v-if="canCancel"
              type="button"
              class="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-white px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-60"
              :disabled="cancelling"
              @click="handleCancel"
            >
              <Ban class="h-3.5 w-3.5" />
              Cancel
            </button>
            <button
              v-if="isReadonly"
              type="button"
              class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              @click="goBack"
            >
              <ArrowLeft class="h-3.5 w-3.5" />
              Back
            </button>
          </div>
        </header>

        <!-- DETAILS section -->
        <section class="p-4">
          <h2 class="mb-3 text-sm font-semibold text-slate-800">Details</h2>

          <div class="grid gap-x-6 gap-y-2.5 md:grid-cols-2">
            <!-- Staff -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Staff</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="currentStaff.stf_staff_name ?? ''"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>

            <!-- Request Date -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Request Date</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="requestDate"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>

            <!-- PTJ -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >PTJ</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="
                  currentStaff.oun_code_ptj_desc
                    ? `${currentStaff.oun_code_ptj} - ${currentStaff.oun_code_ptj_desc}`
                    : (currentStaff.oun_code_ptj ?? '')
                "
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>

            <!-- Position -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Position</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="currentStaff.stf_position_desc ?? ''"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>

            <!-- Employment Status -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Employment Status</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="currentStaff.stf_employment_status ?? ''"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>

            <!-- No KP -->
            <div class="flex items-center gap-3">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >No KP</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="currentStaff.stf_ic_no ?? ''"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
          </div>

          <p class="mt-3 text-center text-xs italic text-slate-500">
            Please select from event management system if choose collection type
            event
          </p>

          <div class="mt-3 grid gap-x-6 gap-y-2.5 md:grid-cols-2">
            <!-- Collection Type -->
            <div class="flex items-center gap-3 md:col-span-2">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Collection Type <span class="text-rose-500">*</span></label
              >
              <span class="text-slate-400">:</span>
              <select
                :value="head.are_purposed_code ?? ''"
                :disabled="isReadonly"
                class="flex-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
                @change="
                  onCollectionTypeChange(
                    ($event.target as HTMLSelectElement).value,
                  )
                "
              >
                <option
                  v-for="opt in collectionTypes"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </div>

            <!-- Event (autosuggest) -->
            <div
              v-if="isEventMode"
              class="flex items-center gap-3 md:col-span-2"
            >
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Event <span class="text-rose-500">*</span></label
              >
              <span class="text-slate-400">:</span>
              <div class="relative flex-1">
                <input
                  v-model="eventQuery"
                  type="search"
                  :disabled="isReadonly"
                  placeholder="Search event (code or description)…"
                  class="w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 pr-8 text-sm"
                  @input="onEventInput"
                  @focus="onEventFocus"
                  @blur="closeEventSoon"
                />
                <button
                  v-if="head.are_event_code && !isReadonly"
                  type="button"
                  class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  title="Clear event"
                  @click="clearEvent"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
                <ul
                  v-if="eventOpen"
                  class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white text-sm shadow-lg"
                >
                  <li
                    v-if="eventLoading"
                    class="px-3 py-2 text-slate-500"
                  >
                    Searching…
                  </li>
                  <li
                    v-else-if="eventResults.length === 0"
                    class="px-3 py-2 text-slate-500"
                  >
                    No matching open event was found for your staff / PTJ.
                  </li>
                  <li
                    v-for="opt in eventResults"
                    :key="opt.projectNo"
                    class="cursor-pointer px-3 py-1.5 hover:bg-slate-50"
                    @mousedown.prevent="pickEvent(opt)"
                  >
                    <div class="font-medium text-slate-800">
                      {{ opt.label }}
                    </div>
                    <div class="text-xs text-slate-500">
                      PTJ: {{ opt.ptj ?? "-" }}
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- Collection Purpose -->
            <div class="flex items-start gap-3 md:col-span-2">
              <label
                class="mt-1.5 w-36 shrink-0 text-xs font-medium text-slate-700"
                >Collection Purpose</label
              >
              <span class="mt-1.5 text-slate-400">:</span>
              <textarea
                v-model="head.are_reason"
                :disabled="isReadonly"
                rows="2"
                class="flex-1 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
              />
            </div>

            <!-- Status -->
            <div class="flex items-center gap-3 md:col-span-2">
              <label class="w-36 shrink-0 text-xs font-medium text-slate-700"
                >Status</label
              >
              <span class="text-slate-400">:</span>
              <input
                :value="head.are_status ?? 'DRAFT'"
                readonly
                class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
          </div>
        </section>

        <!-- AUTHORIZED STAFF -->
        <section class="border-t border-slate-100 p-4">
          <div class="mb-3 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-800">
              Authorized Staff
            </h2>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[1100px] text-xs">
              <thead class="bg-indigo-100 text-slate-800">
                <tr class="border-b border-indigo-200 text-left">
                  <th class="px-2 py-2 font-semibold">No</th>
                  <th class="px-2 py-2 font-semibold">Staff Name</th>
                  <th class="px-2 py-2 font-semibold">No KP</th>
                  <th class="px-2 py-2 font-semibold">PTJ</th>
                  <th class="px-2 py-2 font-semibold">Position</th>
                  <th class="px-2 py-2 font-semibold">Jobcode</th>
                  <th class="px-2 py-2 font-semibold">Contact No</th>
                  <th class="px-2 py-2 font-semibold">Fax No</th>
                  <th class="px-2 py-2 font-semibold">Reason</th>
                  <th class="px-2 py-2 font-semibold">Process</th>
                  <th v-if="!isReadonly" class="px-2 py-2 font-semibold">
                    Action
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="dtAuthorized.length === 0">
                  <td
                    :colspan="isReadonly ? 10 : 11"
                    class="px-2 py-6 text-center text-slate-500"
                  >
                    No records
                  </td>
                </tr>
                <tr
                  v-for="(row, i) in dtAuthorized"
                  :key="`a-${row.ors_id ?? i}`"
                  class="border-b border-slate-100 hover:bg-slate-50/60"
                >
                  <td class="px-2 py-1.5">{{ i + 1 }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_staff_name ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_ic ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_oun_code ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_position ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.sts_jobcode ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_contact_no ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_fax_no ?? "-" }}</td>
                  <td class="px-2 py-1.5">{{ row.ors_reason ?? "-" }}</td>
                  <td class="px-2 py-1.5">
                    {{ row.ors_process_flag === "Y" ? "YES" : "NO" }}
                  </td>
                  <td v-if="!isReadonly" class="space-x-1 px-2 py-1.5">
                    <button
                      type="button"
                      title="Edit"
                      class="rounded bg-slate-100 px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-200"
                      @click="openStaffModalForEdit(i)"
                    >
                      Edit
                    </button>
                    <button
                      type="button"
                      title="Remove"
                      class="rounded p-1 text-rose-500 hover:bg-rose-50"
                      @click="removeStaffRow(i)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-2 flex justify-end">
            <button
              v-if="!isReadonly"
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
              @click="openStaffModal"
            >
              <Plus class="h-3.5 w-3.5" />
              New
            </button>
          </div>
        </section>

        <!-- PROCESS FLOW -->
        <section class="border-t border-slate-100 p-4">
          <h2 class="mb-3 text-sm font-semibold text-slate-800">Process Flow</h2>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <table class="w-full min-w-[1100px] text-xs">
              <thead class="bg-indigo-100 text-slate-800">
                <tr class="border-b border-indigo-200 text-left">
                  <th class="px-2 py-2 font-semibold">No</th>
                  <th class="px-2 py-2 font-semibold">Process</th>
                  <th class="px-2 py-2 font-semibold">By</th>
                  <th class="px-2 py-2 font-semibold">PTJ</th>
                  <th class="px-2 py-2 font-semibold">Email</th>
                  <th class="px-2 py-2 font-semibold">No Telefon</th>
                  <th class="px-2 py-2 font-semibold">Status</th>
                  <th class="px-2 py-2 font-semibold">Comment</th>
                  <th class="px-2 py-2 font-semibold">Date</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="(row, i) in processFlowDisplay"
                  :key="`pf-${i}`"
                  class="border-b border-slate-100"
                >
                  <td class="px-2 py-2 align-top">
                    {{ (row as { no?: number }).no ?? i + 1 }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { process?: string }).process ?? "-" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { by?: string | null }).by ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { ptj?: string | null }).ptj ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { email?: string | null }).email ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { telefon?: string | null }).telefon ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { status?: string | null }).status ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { comment?: string | null }).comment ?? "" }}
                  </td>
                  <td class="px-2 py-2 align-top">
                    {{ (row as { date?: string | null }).date ?? "" }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <p
            v-if="processFlowRows.length === 0"
            class="mt-2 text-[11px] italic text-slate-500"
          >
            Workflow history (wf_task_history / wf_application_status) is not
            yet migrated — showing the standard 4-stage template.
          </p>
        </section>

        <!-- Footer actions (Save / Save & Submit) -->
        <footer
          v-if="!isReadonly"
          class="flex items-center justify-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3"
        >
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg bg-indigo-500 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-600 disabled:opacity-60"
            :disabled="saving || submitting"
            @click="handleSave"
          >
            <Loader2 v-if="saving" class="h-3.5 w-3.5 animate-spin" />
            <Save v-else class="h-3.5 w-3.5" />
            Save
          </button>
          <button
            v-if="canSubmit"
            type="button"
            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-60"
            :disabled="submitting || saving"
            @click="handleSubmit"
          >
            <Loader2 v-if="submitting" class="h-3.5 w-3.5 animate-spin" />
            <Send v-else class="h-3.5 w-3.5" />
            Save &amp; Submit
          </button>
        </footer>
      </article>
    </div>

    <!-- Staff modal (Add / Edit) -->
    <Teleport to="body">
      <div
        v-if="staffModalOpen"
        class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/40 p-4"
        @click.self="closeStaffModal"
      >
        <div
          class="w-full max-w-2xl rounded-lg border border-slate-200 bg-white shadow-xl"
        >
          <header
            class="flex items-center justify-between border-b border-slate-100 px-4 py-3"
          >
            <h3 class="text-sm font-semibold text-slate-800">
              {{
                staffDraft._editingIndex !== null
                  ? "Edit Authorized Staff"
                  : "Add Authorized Staff"
              }}
            </h3>
            <button
              type="button"
              class="rounded p-1 text-slate-500 hover:bg-slate-100"
              @click="closeStaffModal"
            >
              <X class="h-4 w-4" />
            </button>
          </header>

          <section class="grid gap-3 p-4 md:grid-cols-2">
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700"
                >Authorized Receipting Staff
                <span class="text-rose-500">*</span></label
              >
              <div class="relative mt-1">
                <input
                  v-model="staffQuery"
                  type="search"
                  placeholder="Search staff (id or name)…"
                  class="w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                  @input="onStaffInput"
                  @focus="onStaffFocus"
                  @blur="closeStaffSoon"
                />
                <ul
                  v-if="staffOpen"
                  class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-slate-200 bg-white text-sm shadow-lg"
                >
                  <li
                    v-if="staffLoading"
                    class="px-3 py-2 text-slate-500"
                  >
                    Searching…
                  </li>
                  <li
                    v-else-if="staffResults.length === 0"
                    class="px-3 py-2 text-slate-500"
                  >
                    No staff matched. Try a different term.
                  </li>
                  <li
                    v-for="opt in staffResults"
                    :key="opt.staffId"
                    class="cursor-pointer px-3 py-1.5 hover:bg-slate-50"
                    @mousedown.prevent="pickStaff(opt)"
                  >
                    <div class="font-medium text-slate-800">
                      {{ opt.label }}
                    </div>
                    <div class="text-xs text-slate-500">
                      PTJ: {{ opt.ptj ?? "-" }} · Position:
                      {{ opt.position ?? "-" }}
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Staff Id</label
              >
              <input
                :value="staffDraft.ors_staff_id"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Staff Name</label
              >
              <input
                :value="staffDraft.ors_staff_name ?? ''"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >No KP</label
              >
              <input
                :value="staffDraft.ors_ic ?? ''"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >PTJ</label
              >
              <input
                :value="staffDraft.ors_oun_code ?? ''"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Position</label
              >
              <input
                :value="staffDraft.ors_position ?? ''"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Jobcode</label
              >
              <input
                :value="staffDraft.sts_jobcode ?? ''"
                readonly
                class="mt-1 w-full rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Contact No</label
              >
              <input
                v-model="staffDraft.ors_contact_no"
                type="text"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Fax No</label
              >
              <input
                v-model="staffDraft.ors_fax_no"
                type="text"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Email</label
              >
              <input
                v-model="staffDraft.ors_email"
                type="email"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700"
                >Process ?</label
              >
              <select
                v-model="staffDraft.ors_process_flag"
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm"
              >
                <option value="Y">YES</option>
                <option value="N">NO</option>
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-xs font-medium text-slate-700"
                >Reason</label
              >
              <textarea
                v-model="staffDraft.ors_reason"
                rows="2"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm"
              />
            </div>
          </section>

          <footer
            class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3"
          >
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
              @click="closeStaffModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
              @click="saveStaffDraft"
            >
              Save
            </button>
          </footer>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
