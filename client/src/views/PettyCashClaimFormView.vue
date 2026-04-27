<script setup lang="ts">
/**
 * Petty Cash / Petty Cash Claim Form (PAGEID 1544 / MENUID 1872).
 *
 * Source: FIMS BL `MM_API_PETTYCASH_PETTYCASHCLAIMFORM` +
 * `PAGE_SECOND_LEVEL_MENU.json` entries for MENUID 1872 (Information +
 * Petty Cash Detail). This view mirrors the legacy screen layout
 * (two-column Information card, Petty Cash Detail table with a `+ New`
 * button below-right, centred Save action, and a Petty Cash Detail
 * modal with `code - description` composites).
 *
 * Route entry:
 *   /admin/kerisi/m/1872                 → create
 *   /admin/kerisi/m/1872?id=X            → edit
 *   /admin/kerisi/m/1872?id=X&mode=view  → read-only
 *
 * Workflow caveat: `submit`/`cancel` are stubs on the backend — see
 * PettyCashClaimFormController docblock.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Ban,
  Calendar,
  ChevronLeft,
  Loader2,
  Pencil,
  Plus,
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
  cancelPettyCashClaim,
  getPettyCashClaim,
  savePettyCashClaim,
  submitPettyCashClaim,
  suggestPettyCashClaimAccountCode,
  suggestPettyCashClaimActivityCode,
  suggestPettyCashClaimCostCentre,
  suggestPettyCashClaimFundType,
  suggestPettyCashClaimOun,
  suggestPettyCashClaimPcm,
  suggestPettyCashClaimRequestBy,
} from "@/api/cms";
import type {
  PettyCashClaimAccountCodeSuggestion,
  PettyCashClaimDimensionSuggestion,
  PettyCashClaimFormHead,
  PettyCashClaimFormLine,
  PettyCashClaimPcmSuggestion,
  PettyCashClaimRequestBySuggestion,
  PettyCashClaimSavePayload,
} from "@/types";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm } = useConfirmDialog();

const mode = computed(() =>
  route.query.mode === "view" ? "view" : route.query.id ? "edit" : "create",
);
const isReadonly = computed(() => mode.value === "view");
const pmsIdParam = computed(() => {
  const raw = route.query.id;
  if (typeof raw === "string" && raw !== "") return Number(raw);
  return null;
});

const loading = ref(false);
const saving = ref(false);
const submitting = ref(false);
const cancelling = ref(false);

const head = ref<PettyCashClaimFormHead>(defaultHead());
const lines = ref<PettyCashClaimFormLine[]>([]);

function defaultHead(): PettyCashClaimFormHead {
  return {
    pmsId: 0,
    pmsApplicationNo: "",
    pmsRequestBy: "",
    pmsRequestByDesc: "",
    pmsRequestDate: todayIso(),
    pmsTotalAmt: 0,
    pmsStatus: "ENTRY",
  };
}

function todayIso(): string {
  return new Date().toISOString().slice(0, 10);
}

// Legacy form displays dates as dd/MM/yyyy; we still persist ISO on the wire.
function isoToDmy(iso: string): string {
  if (!iso) return "";
  const [y, m, d] = iso.split("-");
  if (!y || !m || !d) return iso;
  return `${d}/${m}/${y}`;
}

function dmyToIso(dmy: string): string {
  const match = dmy.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (!match) return "";
  return `${match[3]}-${match[2]}-${match[1]}`;
}

const totalAmount = computed(() =>
  lines.value.reduce((sum, l) => sum + Number(l.pcdTransAmt ?? 0), 0),
);
watch(totalAmount, (v) => {
  head.value.pmsTotalAmt = v;
});

const isEditable = computed(
  () =>
    !isReadonly.value &&
    (mode.value === "create" ||
      ["ENTRY", "DRAFT"].includes((head.value.pmsStatus || "").toUpperCase())),
);

const isExistingRecord = computed(() => Boolean(pmsIdParam.value));

// ── Request By autosuggest ─────────────────────────────────────────────────
const rbQuery = ref("");
const rbResults = ref<PettyCashClaimRequestBySuggestion[]>([]);
const rbOpen = ref(false);
const rbLoading = ref(false);
let rbTimer: ReturnType<typeof setTimeout> | null = null;

async function runRequestBySearch(term: string) {
  rbLoading.value = true;
  try {
    const res = await suggestPettyCashClaimRequestBy(term);
    rbResults.value = res.data;
    rbOpen.value = true;
  } catch {
    rbResults.value = [];
  } finally {
    rbLoading.value = false;
  }
}

function onRequestByInput(v: string) {
  rbQuery.value = v;
  head.value.pmsRequestByDesc = v;
  if (!v) head.value.pmsRequestBy = "";
  if (rbTimer) clearTimeout(rbTimer);
  rbTimer = setTimeout(() => {
    void runRequestBySearch(v.trim());
  }, 300);
}

function pickRequestBy(opt: PettyCashClaimRequestBySuggestion) {
  head.value.pmsRequestBy = opt.id;
  head.value.pmsRequestByDesc = opt.text;
  rbQuery.value = opt.text;
  rbOpen.value = false;
}

function clearRequestBy() {
  head.value.pmsRequestBy = "";
  head.value.pmsRequestByDesc = "";
  rbQuery.value = "";
  rbResults.value = [];
  rbOpen.value = false;
}

function closeRbSoon() {
  window.setTimeout(() => (rbOpen.value = false), 150);
}

// ── Line detail modal ─────────────────────────────────────────────────────
const editing = ref<PettyCashClaimFormLine | null>(null);
const editingIndex = ref<number>(-1);
const showDetailModal = ref(false);

function openAddModal() {
  editing.value = blankLine();
  editingIndex.value = -1;
  pcmQuery.value = "";
  pcmResults.value = [];
  pcmOpen.value = false;
  acctQuery.value = "";
  acctResults.value = [];
  acctOpen.value = false;
  syncDimQueriesFromEditing();
  showDetailModal.value = true;
}

function openEditModal(idx: number) {
  const row = lines.value[idx];
  editing.value = { ...row };
  editingIndex.value = idx;
  pcmQuery.value = row.pcmPaytoId
    ? `${row.pcmPaytoId} - ${row.pcmPaytoName}`
    : "";
  pcmResults.value = [];
  pcmOpen.value = false;
  acctQuery.value = row.acmAcctCode
    ? `${row.acmAcctCode} - ${row.acmAcctDesc}`
    : "";
  acctResults.value = [];
  acctOpen.value = false;
  syncDimQueriesFromEditing();
  showDetailModal.value = true;
}

function closeDetailModal() {
  showDetailModal.value = false;
  editing.value = null;
  editingIndex.value = -1;
}

function blankLine(): PettyCashClaimFormLine {
  return {
    pcdId: 0,
    pcdReceiptNo: "",
    pcdTransDesc: "",
    pcdTransAmt: 0,
    pcdStatus: "ENTRY",
    pcmId: 0,
    pcmPaytoId: "",
    pcmPaytoName: "",
    pcmMaxPerReceipt: null,
    ftyFundType: "",
    ftyFundDesc: "",
    atActivityCode: "",
    atActivityDesc: "",
    ounCode: "",
    ounDesc: "",
    ccrCostcentre: "",
    ccrCostcentreDesc: "",
    cpaProjectNo: "",
    soCode: "",
    acmAcctCode: "",
    acmAcctDesc: "",
  };
}

async function removeLine(idx: number) {
  if (!isEditable.value) return;
  const ok = await confirm({
    title: "Remove receipt?",
    message: "This receipt line will be removed when you save the application.",
    confirmText: "Remove",
    destructive: true,
  });
  if (!ok) return;
  lines.value = lines.value.filter((_, i) => i !== idx);
}

function saveDetail() {
  if (!editing.value) return;
  const row = editing.value;
  // Petty Cash Main is optional — a line can be constructed entirely
  // from Fund Type / Activity / OU / Cost Centre overrides.
  if (!row.pcdReceiptNo.trim()) {
    toast.error("Missing receipt no", "Receipt No is required.");
    return;
  }
  if (!row.pcdTransDesc.trim()) {
    toast.error("Missing description", "Description is required.");
    return;
  }
  if (!row.ftyFundType) {
    toast.error("Missing fund type", "Pick a Fund Type.");
    return;
  }
  if (!row.atActivityCode) {
    toast.error("Missing activity code", "Pick an Activity Code.");
    return;
  }
  if (!row.ounCode) {
    toast.error("Missing OU", "Pick an OU.");
    return;
  }
  if (!row.ccrCostcentre) {
    toast.error("Missing cost center", "Pick a Cost Center.");
    return;
  }
  if (!(row.pcdTransAmt > 0)) {
    toast.error("Invalid amount", "Amount must be greater than zero.");
    return;
  }
  if (row.pcmMaxPerReceipt !== null && row.pcdTransAmt > row.pcmMaxPerReceipt) {
    toast.error(
      "Amount exceeds cap",
      `Max per receipt is ${row.pcmMaxPerReceipt.toFixed(2)}.`,
    );
    return;
  }
  if (!row.acmAcctCode) {
    toast.error("Missing account code", "Pick an Account Code.");
    return;
  }

  if (editingIndex.value >= 0) {
    lines.value = lines.value.map((l, i) =>
      i === editingIndex.value ? { ...row } : l,
    );
  } else {
    lines.value = [...lines.value, { ...row }];
  }
  closeDetailModal();
}

// ── Petty Cash Main (pcm_id) autosuggest ─────────────────────────────────
const pcmQuery = ref("");
const pcmResults = ref<PettyCashClaimPcmSuggestion[]>([]);
const pcmOpen = ref(false);
const pcmLoading = ref(false);
let pcmTimer: ReturnType<typeof setTimeout> | null = null;

async function runPcmSearch(term: string) {
  pcmLoading.value = true;
  try {
    const res = await suggestPettyCashClaimPcm(term);
    pcmResults.value = res.data;
    pcmOpen.value = true;
  } catch {
    pcmResults.value = [];
  } finally {
    pcmLoading.value = false;
  }
}

function onPcmInput(v: string) {
  pcmQuery.value = v;
  if (!editing.value) return;
  if (!v) {
    editing.value.pcmId = 0;
    editing.value.pcmPaytoId = "";
    editing.value.pcmPaytoName = "";
  }
  if (pcmTimer) clearTimeout(pcmTimer);
  pcmTimer = setTimeout(() => {
    void runPcmSearch(v.trim());
  }, 300);
}

function pickPcm(opt: PettyCashClaimPcmSuggestion) {
  if (!editing.value) return;
  editing.value.pcmId = opt.id;
  const parts = opt.text.split(" - ");
  editing.value.pcmPaytoId = parts[0] ?? "";
  editing.value.pcmPaytoName = parts.slice(1).join(" - ") ?? "";
  editing.value.pcmMaxPerReceipt = opt.maxPerReceipt;
  editing.value.ftyFundType = opt.defaults.ftyFundType;
  editing.value.ftyFundDesc = opt.defaults.ftyFundDesc;
  editing.value.atActivityCode = opt.defaults.atActivityCode;
  editing.value.atActivityDesc = opt.defaults.atActivityDesc;
  editing.value.ounCode = opt.defaults.ounCode;
  editing.value.ounDesc = opt.defaults.ounDesc;
  editing.value.ccrCostcentre = opt.defaults.ccrCostcentre;
  editing.value.ccrCostcentreDesc = opt.defaults.ccrCostcentreDesc;
  editing.value.soCode = opt.defaults.soCode;
  pcmQuery.value = opt.text;
  pcmOpen.value = false;
  editing.value.acmAcctCode = "";
  editing.value.acmAcctDesc = "";
  acctQuery.value = "";
  acctResults.value = [];
  syncDimQueriesFromEditing();
}

function clearPcm() {
  if (!editing.value) return;
  editing.value.pcmId = 0;
  editing.value.pcmPaytoId = "";
  editing.value.pcmPaytoName = "";
  editing.value.pcmMaxPerReceipt = null;
  editing.value.ftyFundType = "";
  editing.value.ftyFundDesc = "";
  editing.value.atActivityCode = "";
  editing.value.atActivityDesc = "";
  editing.value.ounCode = "";
  editing.value.ounDesc = "";
  editing.value.ccrCostcentre = "";
  editing.value.ccrCostcentreDesc = "";
  editing.value.soCode = "";
  editing.value.acmAcctCode = "";
  editing.value.acmAcctDesc = "";
  pcmQuery.value = "";
  pcmResults.value = [];
  pcmOpen.value = false;
  acctQuery.value = "";
  acctResults.value = [];
  syncDimQueriesFromEditing();
}

function closePcmSoon() {
  window.setTimeout(() => (pcmOpen.value = false), 150);
}

// ── Account code autosuggest ─────────────────────────────────────────────
const acctQuery = ref("");
const acctResults = ref<PettyCashClaimAccountCodeSuggestion[]>([]);
const acctOpen = ref(false);
const acctLoading = ref(false);
let acctTimer: ReturnType<typeof setTimeout> | null = null;

async function runAcctSearch(term: string) {
  acctLoading.value = true;
  try {
    const fund = editing.value?.ftyFundType ?? "";
    const res = await suggestPettyCashClaimAccountCode(term, fund);
    acctResults.value = res.data;
    acctOpen.value = true;
  } catch {
    acctResults.value = [];
  } finally {
    acctLoading.value = false;
  }
}

function onAcctInput(v: string) {
  acctQuery.value = v;
  if (!editing.value) return;
  if (!v) {
    editing.value.acmAcctCode = "";
    editing.value.acmAcctDesc = "";
  }
  if (acctTimer) clearTimeout(acctTimer);
  acctTimer = setTimeout(() => {
    void runAcctSearch(v.trim());
  }, 300);
}

function pickAcct(opt: PettyCashClaimAccountCodeSuggestion) {
  if (!editing.value) return;
  const parts = opt.text.split(" - ");
  editing.value.acmAcctCode = opt.id;
  editing.value.acmAcctDesc = parts.slice(1).join(" - ") ?? "";
  acctQuery.value = opt.text;
  acctOpen.value = false;
}

function clearAcct() {
  if (!editing.value) return;
  editing.value.acmAcctCode = "";
  editing.value.acmAcctDesc = "";
  acctQuery.value = "";
  acctResults.value = [];
  acctOpen.value = false;
}

function closeAcctSoon() {
  window.setTimeout(() => (acctOpen.value = false), 150);
}

// ── Dimension autosuggests (Fund Type → Activity → OU → Cost Centre) ────
// Legacy flow: the user can change any field, with later fields narrowed
// to combinations actually configured in `petty_cash_main`.
type DimKey = "fund" | "activity" | "oun" | "cc";

const dimQuery = ref<Record<DimKey, string>>({
  fund: "",
  activity: "",
  oun: "",
  cc: "",
});
const dimOpen = ref<Record<DimKey, boolean>>({
  fund: false,
  activity: false,
  oun: false,
  cc: false,
});
const dimLoading = ref<Record<DimKey, boolean>>({
  fund: false,
  activity: false,
  oun: false,
  cc: false,
});
const dimResults = ref<Record<DimKey, PettyCashClaimDimensionSuggestion[]>>({
  fund: [],
  activity: [],
  oun: [],
  cc: [],
});
const dimTimers: Record<DimKey, ReturnType<typeof setTimeout> | null> = {
  fund: null,
  activity: null,
  oun: null,
  cc: null,
};

async function runDimSearch(key: DimKey, term: string) {
  dimLoading.value[key] = true;
  try {
    const fund = editing.value?.ftyFundType ?? "";
    const activity = editing.value?.atActivityCode ?? "";
    const ou = editing.value?.ounCode ?? "";
    let res: { data: PettyCashClaimDimensionSuggestion[] };
    if (key === "fund") {
      res = await suggestPettyCashClaimFundType(term);
    } else if (key === "activity") {
      res = await suggestPettyCashClaimActivityCode(term, fund);
    } else if (key === "oun") {
      res = await suggestPettyCashClaimOun(term, {
        fundType: fund,
        activityCode: activity,
      });
    } else {
      res = await suggestPettyCashClaimCostCentre(term, {
        fundType: fund,
        activityCode: activity,
        ounCode: ou,
      });
    }
    dimResults.value[key] = res.data;
    dimOpen.value[key] = true;
  } catch {
    dimResults.value[key] = [];
  } finally {
    dimLoading.value[key] = false;
  }
}

// Returns the current `code - desc` composite for a dim field — used to
// detect when the input still shows the pre-filled value, in which case
// we open the full (un-narrowed) picker instead of searching for that
// exact composite.
function dimSelectedComposite(key: DimKey): string {
  if (!editing.value) return "";
  if (key === "fund")
    return composite(editing.value.ftyFundType, editing.value.ftyFundDesc);
  if (key === "activity")
    return composite(editing.value.atActivityCode, editing.value.atActivityDesc);
  if (key === "oun")
    return composite(editing.value.ounCode, editing.value.ounDesc);
  return composite(editing.value.ccrCostcentre, editing.value.ccrCostcentreDesc);
}

function onDimInput(key: DimKey, v: string) {
  dimQuery.value[key] = v;
  const timer = dimTimers[key];
  if (timer) clearTimeout(timer);
  dimTimers[key] = setTimeout(() => {
    void runDimSearch(key, v.trim());
  }, 300);
}

function onDimFocus(key: DimKey, evt?: FocusEvent) {
  dimOpen.value[key] = true;
  // If the field currently displays the already-selected value (e.g.
  // pre-filled from a Petty Cash Main pick), open with the full cascade
  // list so the user can replace it. Otherwise keep whatever the user
  // has typed. Also select the existing text so typing overwrites it.
  const selected = dimSelectedComposite(key);
  const term =
    dimQuery.value[key] && dimQuery.value[key] === selected
      ? ""
      : dimQuery.value[key].trim();
  void runDimSearch(key, term);
  const target = evt?.target as HTMLInputElement | undefined;
  if (target && typeof target.select === "function") target.select();
}

function closeDimSoon(key: DimKey) {
  window.setTimeout(() => (dimOpen.value[key] = false), 150);
}

function pickDim(key: DimKey, opt: PettyCashClaimDimensionSuggestion) {
  if (!editing.value) return;
  if (key === "fund") {
    editing.value.ftyFundType = opt.id;
    editing.value.ftyFundDesc = opt.desc;
    // Changing Fund Type clears downstream selections and the dependent
    // account-code search (legacy cascade).
    editing.value.atActivityCode = "";
    editing.value.atActivityDesc = "";
    editing.value.ounCode = "";
    editing.value.ounDesc = "";
    editing.value.ccrCostcentre = "";
    editing.value.ccrCostcentreDesc = "";
    dimQuery.value.activity = "";
    dimQuery.value.oun = "";
    dimQuery.value.cc = "";
    dimResults.value.activity = [];
    dimResults.value.oun = [];
    dimResults.value.cc = [];
    editing.value.acmAcctCode = "";
    editing.value.acmAcctDesc = "";
    acctQuery.value = "";
    acctResults.value = [];
  } else if (key === "activity") {
    editing.value.atActivityCode = opt.id;
    editing.value.atActivityDesc = opt.desc;
    editing.value.ounCode = "";
    editing.value.ounDesc = "";
    editing.value.ccrCostcentre = "";
    editing.value.ccrCostcentreDesc = "";
    dimQuery.value.oun = "";
    dimQuery.value.cc = "";
    dimResults.value.oun = [];
    dimResults.value.cc = [];
  } else if (key === "oun") {
    editing.value.ounCode = opt.id;
    editing.value.ounDesc = opt.desc;
    editing.value.ccrCostcentre = "";
    editing.value.ccrCostcentreDesc = "";
    dimQuery.value.cc = "";
    dimResults.value.cc = [];
  } else {
    editing.value.ccrCostcentre = opt.id;
    editing.value.ccrCostcentreDesc = opt.desc;
  }
  dimQuery.value[key] = opt.text;
  dimOpen.value[key] = false;
}

function clearDim(key: DimKey) {
  if (!editing.value) return;
  if (key === "fund") {
    editing.value.ftyFundType = "";
    editing.value.ftyFundDesc = "";
  } else if (key === "activity") {
    editing.value.atActivityCode = "";
    editing.value.atActivityDesc = "";
  } else if (key === "oun") {
    editing.value.ounCode = "";
    editing.value.ounDesc = "";
  } else {
    editing.value.ccrCostcentre = "";
    editing.value.ccrCostcentreDesc = "";
  }
  dimQuery.value[key] = "";
  dimResults.value[key] = [];
  dimOpen.value[key] = false;
}

function syncDimQueriesFromEditing() {
  if (!editing.value) {
    dimQuery.value.fund = "";
    dimQuery.value.activity = "";
    dimQuery.value.oun = "";
    dimQuery.value.cc = "";
    dimResults.value.fund = [];
    dimResults.value.activity = [];
    dimResults.value.oun = [];
    dimResults.value.cc = [];
    return;
  }
  dimQuery.value.fund = composite(
    editing.value.ftyFundType,
    editing.value.ftyFundDesc,
  );
  dimQuery.value.activity = composite(
    editing.value.atActivityCode,
    editing.value.atActivityDesc,
  );
  dimQuery.value.oun = composite(editing.value.ounCode, editing.value.ounDesc);
  dimQuery.value.cc = composite(
    editing.value.ccrCostcentre,
    editing.value.ccrCostcentreDesc,
  );
  dimResults.value.fund = [];
  dimResults.value.activity = [];
  dimResults.value.oun = [];
  dimResults.value.cc = [];
}

// ── Load + save + submit + cancel ────────────────────────────────────────
async function loadExisting(id: number) {
  loading.value = true;
  try {
    const res = await getPettyCashClaim(id);
    head.value = res.data.head;
    lines.value = res.data.lines;
    rbQuery.value = head.value.pmsRequestByDesc || head.value.pmsRequestBy;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load petty cash application.",
    );
  } finally {
    loading.value = false;
  }
}

function buildPayload(): PettyCashClaimSavePayload {
  const firstLine = lines.value[0];
  return {
    head: {
      pmsId: head.value.pmsId || null,
      pmsApplicationNo: head.value.pmsApplicationNo || null,
      pmsRequestBy: head.value.pmsRequestBy,
      pmsRequestByDesc: head.value.pmsRequestByDesc || null,
      pmsRequestDate: head.value.pmsRequestDate,
      pmsTotalAmt: totalAmount.value,
      pcmId: firstLine?.pcmId ?? 0,
    },
    lines: lines.value.map((l) => ({
      pcdId: l.pcdId > 0 ? l.pcdId : null,
      pcdReceiptNo: l.pcdReceiptNo,
      pcdTransDesc: l.pcdTransDesc,
      pcdTransAmt: Number(l.pcdTransAmt),
      pcmId: l.pcmId,
      ftyFundType: l.ftyFundType || null,
      atActivityCode: l.atActivityCode || null,
      ounCode: l.ounCode || null,
      ccrCostcentre: l.ccrCostcentre || null,
      cpaProjectNo: l.cpaProjectNo || null,
      soCode: l.soCode || null,
      acmAcctCode: l.acmAcctCode || null,
    })),
  };
}

function validateHead(): boolean {
  if (!head.value.pmsRequestBy) {
    toast.error("Missing Request By", "Select a requester first.");
    return false;
  }
  if (!head.value.pmsRequestDate) {
    toast.error("Missing date", "Request Date is required.");
    return false;
  }
  if (lines.value.length === 0) {
    toast.error("No receipts", "Add at least one receipt line.");
    return false;
  }
  return true;
}

async function persistDraft(): Promise<number | null> {
  if (!validateHead()) return null;
  saving.value = true;
  try {
    const res = await savePettyCashClaim(buildPayload());
    head.value.pmsId = res.data.pmsId;
    head.value.pmsApplicationNo = res.data.pmsApplicationNo;
    head.value.pmsTotalAmt = res.data.pmsTotalAmt;
    head.value.pmsStatus = res.data.pmsStatus;
    if (!pmsIdParam.value) {
      await router.replace({
        path: "/admin/kerisi/m/1872",
        query: { id: String(res.data.pmsId), mode: "edit" },
      });
    } else {
      await loadExisting(res.data.pmsId);
    }
    return res.data.pmsId;
  } catch (e) {
    toast.error(
      "Save failed",
      e instanceof Error
        ? e.message
        : "Unable to save petty cash application.",
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
      `Application ${head.value.pmsApplicationNo || id} saved.`,
    );
}

async function handleSubmit() {
  const ok = await confirm({
    title: "Submit petty cash claim?",
    message:
      "Submit will save and mark this application as Entry. Note: FIMS workflow routing is not yet migrated, so no approver task will be created.",
    confirmText: "Submit",
  });
  if (!ok) return;

  const id = pmsIdParam.value ?? (await persistDraft());
  if (!id) return;

  submitting.value = true;
  try {
    const res = await submitPettyCashClaim(id);
    head.value.pmsStatus = res.data.pmsStatus ?? "ENTRY";
    toast.success(
      "Submitted",
      res.data.message ?? "Petty cash application submitted.",
    );
  } catch (e) {
    toast.error(
      "Submit failed",
      e instanceof Error
        ? e.message
        : "Unable to submit petty cash application.",
    );
  } finally {
    submitting.value = false;
  }
}

async function handleCancel() {
  const id = pmsIdParam.value;
  if (!id) return;
  const ok = await confirm({
    title: "Cancel petty cash claim?",
    message:
      "This will mark the application as Cancelled. Enter a reason in the dialog that follows.",
    confirmText: "Cancel",
    destructive: true,
  });
  if (!ok) return;

  const reason = window.prompt("Cancel reason (required):", "") ?? "";
  if (reason.trim().length < 3) {
    toast.error("Reason required", "Please enter a cancel reason (min 3 chars).");
    return;
  }

  cancelling.value = true;
  try {
    const res = await cancelPettyCashClaim(id, reason.trim());
    head.value.pmsStatus = res.data.pmsStatus ?? "CANCELLED";
    toast.success(
      "Cancelled",
      res.data.message ?? "Petty cash application cancelled.",
    );
  } catch (e) {
    toast.error(
      "Cancel failed",
      e instanceof Error
        ? e.message
        : "Unable to cancel petty cash application.",
    );
  } finally {
    cancelling.value = false;
  }
}

function goBack() {
  void router.push("/admin/kerisi/m/1490");
}

function formatMoney(v: number | null | undefined): string {
  const n = Number(v ?? 0);
  return n.toLocaleString("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function composite(code: string, desc: string): string {
  if (!code && !desc) return "";
  if (!desc) return code;
  return `${code} - ${desc}`;
}

onMounted(() => {
  if (pmsIdParam.value) {
    void loadExisting(pmsIdParam.value);
  }
});

onUnmounted(() => {
  if (rbTimer) clearTimeout(rbTimer);
  if (pcmTimer) clearTimeout(pcmTimer);
  if (acctTimer) clearTimeout(acctTimer);
  (Object.keys(dimTimers) as DimKey[]).forEach((k) => {
    const t = dimTimers[k];
    if (t) clearTimeout(t);
  });
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-3 p-4">
      <!-- Breadcrumb header -->
      <div class="flex items-center gap-2 border-b border-slate-200 pb-2">
        <button
          type="button"
          class="rounded p-1 text-slate-600 hover:bg-slate-100"
          title="Back"
          @click="goBack"
        >
          <ChevronLeft class="h-5 w-5" />
        </button>
        <h1 class="page-title">
          Petty Cash (PTJ) / Petty Cash Claim Form
        </h1>
      </div>

      <div
        v-if="loading"
        class="flex items-center gap-2 text-sm text-slate-500"
      >
        <Loader2 class="h-4 w-4 animate-spin" /> Loading application…
      </div>

      <!-- Information card -->
      <section class="rounded-md border border-slate-200 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2">
          <h2 class="text-sm font-semibold text-slate-800">Information</h2>
        </div>
        <div class="grid grid-cols-1 gap-x-6 gap-y-3 px-4 py-3 md:grid-cols-2">
          <!-- Request By -->
          <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
            <label class="text-sm text-slate-700">
              Request By <span class="text-red-500">*</span>
            </label>
            <span class="text-sm text-slate-500">:</span>
            <div class="relative">
              <input
                class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                :class="{ 'bg-slate-100 text-slate-500': !isEditable }"
                :value="rbQuery"
                :disabled="!isEditable"
                placeholder="Search staff by id or name…"
                @input="(e) => onRequestByInput((e.target as HTMLInputElement).value)"
                @focus="rbOpen = true"
                @blur="closeRbSoon"
              />
              <button
                v-if="isEditable && rbQuery"
                type="button"
                class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                title="Clear"
                @click="clearRequestBy"
              >
                <X class="h-3.5 w-3.5" />
              </button>
              <div
                v-if="rbOpen && isEditable"
                class="absolute z-20 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
              >
                <div
                  v-if="rbLoading"
                  class="px-3 py-2 text-xs text-slate-500"
                >
                  Searching…
                </div>
                <div
                  v-else-if="rbResults.length === 0"
                  class="px-3 py-2 text-xs text-slate-500"
                >
                  No match.
                </div>
                <button
                  v-for="opt in rbResults"
                  :key="opt.id"
                  type="button"
                  class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                  @mousedown.prevent="pickRequestBy(opt)"
                >
                  {{ opt.text }}
                </button>
              </div>
            </div>
          </div>

          <!-- Request Date -->
          <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
            <label class="text-sm text-slate-700">Request Date</label>
            <span class="text-sm text-slate-500">:</span>
            <div class="relative">
              <input
                :value="isoToDmy(head.pmsRequestDate)"
                type="text"
                placeholder="dd/mm/yyyy"
                class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                :class="{ 'bg-slate-100 text-slate-500': !isEditable }"
                :disabled="!isEditable"
                @input="
                  (e) => {
                    const v = (e.target as HTMLInputElement).value;
                    const iso = dmyToIso(v);
                    if (iso) head.pmsRequestDate = iso;
                  }
                "
              />
              <label
                class="absolute inset-y-0 right-2 flex cursor-pointer items-center text-slate-400 hover:text-slate-600"
                title="Pick date"
              >
                <Calendar class="h-4 w-4" />
                <input
                  type="date"
                  class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                  :disabled="!isEditable"
                  :value="head.pmsRequestDate"
                  @change="
                    (e) => (head.pmsRequestDate = (e.target as HTMLInputElement).value)
                  "
                />
              </label>
            </div>
          </div>

          <!-- Total Amount -->
          <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
            <label class="text-sm text-slate-700">Total Amount</label>
            <span class="text-sm text-slate-500">:</span>
            <input
              class="w-full rounded border border-slate-300 bg-slate-100 px-2 py-1 text-right font-mono text-sm text-slate-700"
              :value="formatMoney(totalAmount)"
              disabled
            />
          </div>

          <!-- Request No (shown only once saved) -->
          <div
            v-if="head.pmsApplicationNo"
            class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2"
          >
            <label class="text-sm text-slate-700">Request No</label>
            <span class="text-sm text-slate-500">:</span>
            <input
              class="w-full rounded border border-slate-300 bg-slate-100 px-2 py-1 text-sm text-slate-700"
              :value="head.pmsApplicationNo"
              disabled
            />
          </div>
        </div>
      </section>

      <!-- Detail table -->
      <section class="rounded-md border border-slate-200 bg-white">
        <div class="border-b border-slate-200 bg-slate-50 px-4 py-2">
          <h2 class="text-sm font-semibold text-slate-800">Petty Cash Detail</h2>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-100 text-xs font-semibold text-slate-700">
              <tr>
                <th class="px-3 py-2 text-left">Receipt No</th>
                <th class="px-3 py-2 text-left">Petty Cash Main</th>
                <th class="px-3 py-2 text-left">Description</th>
                <th class="px-3 py-2 text-left">Fund Type</th>
                <th class="px-3 py-2 text-left">Activity Code</th>
                <th class="px-3 py-2 text-left">PTJ</th>
                <th class="px-3 py-2 text-left">Cost Center</th>
                <th class="px-3 py-2 text-left">Account Code</th>
                <th class="px-3 py-2 text-left">Code SO</th>
                <th class="px-3 py-2 text-right">Amount</th>
                <th v-if="isEditable" class="px-3 py-2 text-center">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-if="lines.length === 0">
                <td
                  class="px-3 py-6 text-center text-xs text-slate-500"
                  :colspan="isEditable ? 11 : 10"
                >
                  No receipts added yet.
                </td>
              </tr>
              <tr
                v-for="(line, idx) in lines"
                :key="line.pcdId || `new-${idx}`"
                class="hover:bg-slate-50"
              >
                <td class="px-3 py-2 font-medium text-slate-800">
                  {{ line.pcdReceiptNo }}
                </td>
                <td class="px-3 py-2 text-slate-700">
                  <div>{{ line.pcmPaytoId }}</div>
                  <div class="text-xs text-slate-500">
                    {{ line.pcmPaytoName }}
                  </div>
                </td>
                <td class="px-3 py-2 text-slate-700">{{ line.pcdTransDesc }}</td>
                <td class="px-3 py-2 text-slate-700">{{ line.ftyFundType }}</td>
                <td class="px-3 py-2 text-slate-700">
                  {{ line.atActivityCode }}
                </td>
                <td class="px-3 py-2 text-slate-700">{{ line.ounCode }}</td>
                <td class="px-3 py-2 text-slate-700">
                  {{ line.ccrCostcentre }}
                </td>
                <td class="px-3 py-2 text-slate-700">{{ line.acmAcctCode }}</td>
                <td class="px-3 py-2 text-slate-700">{{ line.soCode }}</td>
                <td class="px-3 py-2 text-right font-mono text-slate-800">
                  {{ formatMoney(line.pcdTransAmt) }}
                </td>
                <td v-if="isEditable" class="px-3 py-2 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button
                      type="button"
                      class="rounded border border-slate-300 p-1 text-slate-600 hover:bg-slate-100 hover:text-blue-600"
                      title="Edit"
                      @click="openEditModal(idx)"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                    <button
                      type="button"
                      class="rounded border border-slate-300 p-1 text-slate-600 hover:bg-red-50 hover:text-red-600"
                      title="Remove"
                      @click="removeLine(idx)"
                    >
                      <Trash2 class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- + New below table, right aligned (matches legacy) -->
        <div
          v-if="isEditable"
          class="flex items-center justify-end border-t border-slate-100 bg-slate-50 px-3 py-2"
        >
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700"
            @click="openAddModal"
          >
            <Plus class="h-4 w-4" /> New
          </button>
        </div>
      </section>

      <!-- Action row -->
      <div
        v-if="!isReadonly"
        class="flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white py-3"
      >
        <button
          v-if="isExistingRecord && isEditable"
          type="button"
          :disabled="cancelling"
          class="inline-flex items-center gap-1 rounded-md border border-red-300 bg-white px-4 py-1.5 text-sm text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60"
          @click="handleCancel"
        >
          <Ban v-if="!cancelling" class="h-4 w-4" />
          <Loader2 v-else class="h-4 w-4 animate-spin" />
          Cancel
        </button>
        <button
          type="button"
          :disabled="!isEditable || saving"
          class="inline-flex items-center gap-1 rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
          @click="handleSave"
        >
          <Save v-if="!saving" class="h-4 w-4" />
          <Loader2 v-else class="h-4 w-4 animate-spin" />
          Save
        </button>
        <button
          v-if="isExistingRecord && isEditable"
          type="button"
          :disabled="submitting"
          class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
          @click="handleSubmit"
        >
          <Send v-if="!submitting" class="h-4 w-4" />
          <Loader2 v-else class="h-4 w-4 animate-spin" />
          Submit
        </button>
      </div>

      <!-- Petty Cash Detail modal -->
      <Teleport to="body">
        <div
          v-if="showDetailModal && editing"
          class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4"
        >
          <div class="w-full max-w-2xl rounded-md bg-white shadow-xl">
            <!-- Purple header like legacy -->
            <div
              class="flex items-center justify-between rounded-t-md bg-indigo-400 px-4 py-2 text-white"
            >
              <h3 class="text-sm font-semibold">Petty Cash Detail</h3>
              <button
                type="button"
                class="rounded p-1 hover:bg-white/20"
                @click="closeDetailModal"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <div class="space-y-3 px-4 py-4">
              <!-- Receipt No -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm text-slate-700">Receipt No</label>
                <span class="text-sm text-slate-500">:</span>
                <input
                  v-model="editing.pcdReceiptNo"
                  type="text"
                  class="w-full rounded border border-slate-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                />
              </div>

              <!-- Petty Cash Main autosuggest -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm text-slate-700">Petty Cash Main</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="pcmQuery"
                    placeholder=""
                    @input="(e) => onPcmInput((e.target as HTMLInputElement).value)"
                    @focus="pcmOpen = true"
                    @blur="closePcmSoon"
                  />
                  <button
                    v-if="pcmQuery"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearPcm"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="pcmOpen"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="pcmLoading"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="pcmResults.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in pcmResults"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickPcm(opt)"
                    >
                      <div class="font-medium">{{ opt.text }}</div>
                      <div class="text-[11px] text-slate-500">
                        {{ opt.defaults.ftyFundType }} ·
                        {{ opt.defaults.atActivityCode }} ·
                        {{ opt.defaults.ounCode }}
                      </div>
                    </button>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <div class="grid grid-cols-[140px_10px_1fr] items-start gap-x-2">
                <label class="pt-1 text-sm text-slate-700">Description</label>
                <span class="pt-1 text-sm text-slate-500">:</span>
                <textarea
                  v-model="editing.pcdTransDesc"
                  rows="2"
                  class="w-full resize-y rounded border border-slate-300 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                ></textarea>
              </div>

              <!-- Fund Type dropdown -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">Fund Type</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="dimQuery.fund"
                    @input="(e) => onDimInput('fund', (e.target as HTMLInputElement).value)"
                    @focus="(e) => onDimFocus('fund', e)"
                    @blur="closeDimSoon('fund')"
                  />
                  <button
                    v-if="dimQuery.fund"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearDim('fund')"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="dimOpen.fund"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="dimLoading.fund"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="dimResults.fund.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in dimResults.fund"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickDim('fund', opt)"
                    >
                      {{ opt.text }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Activity Code dropdown (filtered by Fund Type) -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">Activity Code</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="dimQuery.activity"
                    :disabled="!editing.ftyFundType"
                    :class="{ 'bg-slate-100 text-slate-500': !editing.ftyFundType }"
                    @input="(e) => onDimInput('activity', (e.target as HTMLInputElement).value)"
                    @focus="(e) => onDimFocus('activity', e)"
                    @blur="closeDimSoon('activity')"
                  />
                  <button
                    v-if="dimQuery.activity && editing.ftyFundType"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearDim('activity')"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="dimOpen.activity"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="dimLoading.activity"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="dimResults.activity.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in dimResults.activity"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickDim('activity', opt)"
                    >
                      {{ opt.text }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- OU dropdown (filtered by Fund + Activity) -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">OU</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="dimQuery.oun"
                    :disabled="!editing.atActivityCode"
                    :class="{ 'bg-slate-100 text-slate-500': !editing.atActivityCode }"
                    @input="(e) => onDimInput('oun', (e.target as HTMLInputElement).value)"
                    @focus="(e) => onDimFocus('oun', e)"
                    @blur="closeDimSoon('oun')"
                  />
                  <button
                    v-if="dimQuery.oun && editing.atActivityCode"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearDim('oun')"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="dimOpen.oun"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="dimLoading.oun"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="dimResults.oun.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in dimResults.oun"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickDim('oun', opt)"
                    >
                      {{ opt.text }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Cost Center dropdown (filtered by Fund + Activity + OU) -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">Cost Center</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="dimQuery.cc"
                    :disabled="!editing.ounCode"
                    :class="{ 'bg-slate-100 text-slate-500': !editing.ounCode }"
                    @input="(e) => onDimInput('cc', (e.target as HTMLInputElement).value)"
                    @focus="(e) => onDimFocus('cc', e)"
                    @blur="closeDimSoon('cc')"
                  />
                  <button
                    v-if="dimQuery.cc && editing.ounCode"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearDim('cc')"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="dimOpen.cc"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="dimLoading.cc"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="dimResults.cc.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in dimResults.cc"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickDim('cc', opt)"
                    >
                      {{ opt.text }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Account Code autosuggest -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">
                  Account Code
                </label>
                <span class="text-sm text-slate-500">:</span>
                <div class="relative">
                  <input
                    class="w-full rounded border border-slate-300 px-2 py-1 pr-8 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                    :value="acctQuery"
                    :disabled="!editing.ftyFundType"
                    :class="{ 'bg-slate-100 text-slate-500': !editing.ftyFundType }"
                    placeholder=""
                    @input="(e) => onAcctInput((e.target as HTMLInputElement).value)"
                    @focus="acctOpen = true"
                    @blur="closeAcctSoon"
                  />
                  <button
                    v-if="acctQuery && editing.ftyFundType"
                    type="button"
                    class="absolute inset-y-0 right-2 flex items-center text-slate-400 hover:text-slate-600"
                    title="Clear"
                    @click="clearAcct"
                  >
                    <X class="h-3.5 w-3.5" />
                  </button>
                  <div
                    v-if="acctOpen"
                    class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded border border-slate-200 bg-white shadow-lg"
                  >
                    <div
                      v-if="acctLoading"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      Searching…
                    </div>
                    <div
                      v-else-if="acctResults.length === 0"
                      class="px-3 py-2 text-xs text-slate-500"
                    >
                      No match.
                    </div>
                    <button
                      v-for="opt in acctResults"
                      :key="opt.id"
                      type="button"
                      class="block w-full px-3 py-2 text-left text-xs hover:bg-slate-50"
                      @mousedown.prevent="pickAcct(opt)"
                    >
                      {{ opt.text }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Code SO (highlighted yellow like legacy) -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">Code SO</label>
                <span class="text-sm text-slate-500">:</span>
                <input
                  v-model="editing.soCode"
                  type="text"
                  class="w-full rounded border border-slate-300 bg-yellow-100 px-2 py-1 text-sm focus:border-blue-400 focus:outline-none focus:ring-1 focus:ring-blue-200"
                />
              </div>

              <!-- Amount with MYR prefix -->
              <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
                <label class="text-sm font-semibold text-slate-700">Amount</label>
                <span class="text-sm text-slate-500">:</span>
                <div class="flex items-stretch overflow-hidden rounded border border-slate-300 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-200">
                  <span
                    class="border-r border-slate-300 bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600"
                  >
                    MYR
                  </span>
                  <input
                    v-model.number="editing.pcdTransAmt"
                    type="number"
                    step="0.01"
                    min="0"
                    class="w-full px-2 py-1 text-right font-mono text-sm focus:outline-none"
                  />
                </div>
              </div>
              <p
                v-if="editing.pcmMaxPerReceipt !== null"
                class="ml-[150px] text-[11px] text-slate-500"
              >
                Max per receipt: {{ formatMoney(editing.pcmMaxPerReceipt) }}
              </p>
            </div>

            <!-- Footer buttons: red Cancel + blue Ok -->
            <div
              class="flex items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3"
            >
              <button
                type="button"
                class="rounded-md bg-red-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-red-700"
                @click="closeDetailModal"
              >
                Cancel
              </button>
              <button
                type="button"
                class="rounded-md bg-blue-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                @click="saveDetail"
              >
                Ok
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </AdminLayout>
</template>
