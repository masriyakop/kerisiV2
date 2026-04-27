<script setup lang="ts">
/**
 * Vendor Portal / Vendor Portal (PAGEID 1622 / MENUID 1961)
 *
 * Source: legacy FIMS BL `NF_BL_PURCHASING_PORTAL_VENDOR` plus the
 * onload trigger `NF_JS_PURCHASING_PORTAL_VENDOR`. The legacy page is a
 * vendor-renewal application with a master form, seven sub-table
 * datatables (Category / Account / Address / Jobscope / SSM / MOF /
 * Other), an upload dropzone and a final submit. Renewal flow stages
 * every change in `temp_vend_*` tables.
 *
 * Migration scope:
 *   - Phase 2a (this commit): live "Application Information /
 *     Vendor Portal / Vendor Registration Detail" master form (3
 *     sections matching the legacy screenshot). Save hits
 *     `PUT /api/portal/vendor/profile` which mirrors the legacy
 *     `?detail_process=1` direct-edit branch (UPDATE on
 *     `vend_customer_supplier`).
 *   - The seven live sub-tables (Category / Account / Address /
 *     Jobscope / SSM / MOF / Other) remain READ-ONLY here; CRUD modals
 *     are Phase 2b.
 *
 * Out of scope (deferred to Phase 2c): the renewal/staging workflow
 * (`?addDetail=1` to `temp_vend_*`, `?submit=1`, `?uploadocument=1`
 * dropzone, "Continue/Cancel" workflow advance, system-managed
 * `vcs_unv_reg_date` / `vcs_unv_req_exp_date` / `vcs_vendor_status`
 * / `vcs_temp_code` writes). Those four columns are rendered as
 * disabled "system-managed" inputs in this commit so the layout
 * matches the legacy screenshot but the resolver/renewal flow stays
 * authoritative for them.
 *
 * Auth model:
 *   - Authenticated vendor edits their own row.
 *   - Operators with `audit.read` may pass `?vendor_code=ABC` (URL or
 *     "View as vendor" panel) to edit on behalf of another vendor.
 *     Such writes are audit-logged in the backend.
 */
import { computed, onMounted, onUnmounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Save, Search, Undo2, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  getVendorPortalLookups,
  getVendorPortalProfile,
  listVendorPortalAccounts,
  listVendorPortalAddresses,
  listVendorPortalCategories,
  listVendorPortalJobscopes,
  listVendorPortalMofLicences,
  listVendorPortalOtherLicences,
  listVendorPortalSsmLicences,
  updateVendorPortalProfile,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  VendorPortalAccountRow,
  VendorPortalAddressRow,
  VendorPortalCategoryRow,
  VendorPortalJobscopeRow,
  VendorPortalLicenceRow,
  VendorPortalLookups,
  VendorPortalOtherLicenceRow,
  VendorPortalProfile,
  VendorPortalProfileInput,
} from "@/types";

type SectionId =
  | "category"
  | "account"
  | "address"
  | "jobscope"
  | "ssm"
  | "mof"
  | "other";

const toast = useToast();
const route = useRoute();
const router = useRouter();

const profile = ref<VendorPortalProfile | null>(null);
const profileLoading = ref(false);

type ResolutionError = {
  message: string;
  authEmail: string | null;
  authName: string | null;
  tried: Record<string, string>;
};
const resolutionError = ref<ResolutionError | null>(null);

// Operator/admin "view as vendor" override. Initialised from
// `?vendor_code=` on the route so the override survives reloads, and
// kept in sync via `router.replace` whenever the operator changes it.
// Threaded into every API call as `?vendor_code=ABC` — the backend
// only honours it for callers with the `audit.read` permission, so a
// non-privileged user typing the URL by hand still sees the
// VENDOR_NOT_RESOLVED diagnostic.
const viewAsCode = ref<string>(
  typeof route.query.vendor_code === "string" ? route.query.vendor_code : "",
);
const overrideInput = ref<string>(viewAsCode.value);

const activeSection = ref<SectionId>("category");

// ---- Sub-table state (one paginated/searchable list per section) -----------

type ListState<T> = {
  rows: T[];
  total: number;
  page: number;
  limit: number;
  q: string;
  loading: boolean;
};

function makeState<T>(): ListState<T> {
  return { rows: [] as T[], total: 0, page: 1, limit: 10, q: "", loading: false };
}

const categoryState = ref<ListState<VendorPortalCategoryRow>>(makeState());
const accountState = ref<ListState<VendorPortalAccountRow>>(makeState());
const addressState = ref<ListState<VendorPortalAddressRow>>(makeState());
const jobscopeState = ref<ListState<VendorPortalJobscopeRow>>(makeState());
const ssmState = ref<ListState<VendorPortalLicenceRow>>(makeState());
const mofState = ref<ListState<VendorPortalLicenceRow>>(makeState());
const otherState = ref<ListState<VendorPortalOtherLicenceRow>>(makeState());

// Per-section debounce timers (debounce = 350ms; matches kerisi setup pages).
const debounceTimers: Record<SectionId, ReturnType<typeof setTimeout> | null> = {
  category: null,
  account: null,
  address: null,
  jobscope: null,
  ssm: null,
  mof: null,
  other: null,
};

function captureResolutionError(e: unknown): boolean {
  const err = e as { code?: string; details?: unknown; message?: string } | null;
  if (err && err.code === "VENDOR_NOT_RESOLVED") {
    const details = (err.details ?? {}) as {
      message?: string;
      auth?: { email?: string | null; name?: string | null };
      tried?: Record<string, string>;
    };
    resolutionError.value = {
      message:
        details.message ??
        err.message ??
        "Authenticated user could not be matched to a vendor record.",
      authEmail: details.auth?.email ?? null,
      authName: details.auth?.name ?? null,
      tried: details.tried ?? {},
    };
    return true;
  }
  return false;
}

function buildParams(state: ListState<unknown>): string {
  const params = new URLSearchParams({
    page: String(state.page),
    limit: String(state.limit),
  });
  if (state.q.trim() !== "") params.set("q", state.q.trim());
  if (viewAsCode.value.trim() !== "") params.set("vendor_code", viewAsCode.value.trim());
  return `?${params.toString()}`;
}

function buildProfileParams(): string {
  if (viewAsCode.value.trim() === "") return "";
  const params = new URLSearchParams({ vendor_code: viewAsCode.value.trim() });
  return `?${params.toString()}`;
}

async function loadProfile() {
  profileLoading.value = true;
  try {
    const res = await getVendorPortalProfile(buildProfileParams());
    profile.value = res.data;
    syncFormFromProfile(res.data);
    resolutionError.value = null;
  } catch (e) {
    if (!captureResolutionError(e)) {
      toast.error(
        "Load failed",
        e instanceof Error ? e.message : "Unable to load vendor profile.",
      );
    }
  } finally {
    profileLoading.value = false;
  }
}

async function applyOverride() {
  const code = overrideInput.value.trim();
  if (code === "") return;
  viewAsCode.value = code;
  // Persist to URL so reloads keep the override in place.
  void router.replace({ query: { ...route.query, vendor_code: code } });
  // Reset all sub-table state and re-fetch from page 1.
  for (const s of [
    categoryState,
    accountState,
    addressState,
    jobscopeState,
    ssmState,
    mofState,
    otherState,
  ]) {
    s.value.page = 1;
    s.value.q = "";
    s.value.rows = [];
    s.value.total = 0;
  }
  resolutionError.value = null;
  await loadProfile();
  if (!resolutionError.value) {
    await Promise.all([loadLookups(), sectionLoaders[activeSection.value]()]);
  }
}

async function clearOverride() {
  viewAsCode.value = "";
  overrideInput.value = "";
  const next = { ...route.query };
  delete next.vendor_code;
  void router.replace({ query: next });
  await loadProfile();
  if (!resolutionError.value) {
    await Promise.all([loadLookups(), sectionLoaders[activeSection.value]()]);
  }
}

async function loadCategory() {
  if (resolutionError.value) return;
  categoryState.value.loading = true;
  try {
    const res = await listVendorPortalCategories(buildParams(categoryState.value));
    categoryState.value.rows = res.data;
    categoryState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Categories failed to load.");
  } finally {
    categoryState.value.loading = false;
  }
}
async function loadAccount() {
  if (resolutionError.value) return;
  accountState.value.loading = true;
  try {
    const res = await listVendorPortalAccounts(buildParams(accountState.value));
    accountState.value.rows = res.data;
    accountState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Accounts failed to load.");
  } finally {
    accountState.value.loading = false;
  }
}
async function loadAddress() {
  if (resolutionError.value) return;
  addressState.value.loading = true;
  try {
    const res = await listVendorPortalAddresses(buildParams(addressState.value));
    addressState.value.rows = res.data;
    addressState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Addresses failed to load.");
  } finally {
    addressState.value.loading = false;
  }
}
async function loadJobscope() {
  if (resolutionError.value) return;
  jobscopeState.value.loading = true;
  try {
    const res = await listVendorPortalJobscopes(buildParams(jobscopeState.value));
    jobscopeState.value.rows = res.data;
    jobscopeState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Jobscopes failed to load.");
  } finally {
    jobscopeState.value.loading = false;
  }
}
async function loadSsm() {
  if (resolutionError.value) return;
  ssmState.value.loading = true;
  try {
    const res = await listVendorPortalSsmLicences(buildParams(ssmState.value));
    ssmState.value.rows = res.data;
    ssmState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "SSM licences failed to load.");
  } finally {
    ssmState.value.loading = false;
  }
}
async function loadMof() {
  if (resolutionError.value) return;
  mofState.value.loading = true;
  try {
    const res = await listVendorPortalMofLicences(buildParams(mofState.value));
    mofState.value.rows = res.data;
    mofState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "MOF licences failed to load.");
  } finally {
    mofState.value.loading = false;
  }
}
async function loadOther() {
  if (resolutionError.value) return;
  otherState.value.loading = true;
  try {
    const res = await listVendorPortalOtherLicences(buildParams(otherState.value));
    otherState.value.rows = res.data;
    otherState.value.total = Number(res.meta?.total ?? 0);
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Other licences failed to load.");
  } finally {
    otherState.value.loading = false;
  }
}

const sectionLoaders: Record<SectionId, () => Promise<void>> = {
  category: loadCategory,
  account: loadAccount,
  address: loadAddress,
  jobscope: loadJobscope,
  ssm: loadSsm,
  mof: loadMof,
  other: loadOther,
};

function setSection(id: SectionId) {
  activeSection.value = id;
  void sectionLoaders[id]();
}

function totalPagesFor(state: ListState<unknown>): number {
  return state.total ? Math.max(1, Math.ceil(state.total / state.limit)) : 1;
}

function startIdxFor(state: ListState<unknown>): number {
  return state.total === 0 ? 0 : (state.page - 1) * state.limit + 1;
}

function endIdxFor(state: ListState<unknown>): number {
  return Math.min(state.page * state.limit, state.total);
}

function prevPage(section: SectionId) {
  const state = stateOf(section);
  if (state.page > 1) {
    state.page -= 1;
    void sectionLoaders[section]();
  }
}

function nextPage(section: SectionId) {
  const state = stateOf(section);
  if (state.page < totalPagesFor(state)) {
    state.page += 1;
    void sectionLoaders[section]();
  }
}

function stateOf(section: SectionId): ListState<unknown> {
  switch (section) {
    case "category": return categoryState.value;
    case "account": return accountState.value;
    case "address": return addressState.value;
    case "jobscope": return jobscopeState.value;
    case "ssm": return ssmState.value;
    case "mof": return mofState.value;
    case "other": return otherState.value;
  }
}

function clearSearch(section: SectionId) {
  const state = stateOf(section);
  state.q = "";
  state.page = 1;
  void sectionLoaders[section]();
}

function onSearchInput(section: SectionId) {
  if (debounceTimers[section]) clearTimeout(debounceTimers[section]!);
  debounceTimers[section] = setTimeout(() => {
    const state = stateOf(section);
    state.page = 1;
    void sectionLoaders[section]();
  }, 350);
}

function flushSearch(section: SectionId) {
  if (debounceTimers[section]) {
    clearTimeout(debounceTimers[section]!);
    debounceTimers[section] = null;
  }
  const state = stateOf(section);
  state.page = 1;
  void sectionLoaders[section]();
}

// ---- Master form state (Application Info + Vendor Portal + Vendor Registration Detail) -----
//
// `formData` holds the editable view of `profile`. Date fields are kept
// in HTML5 `<input type="date">` (yyyy-mm-dd) form; conversion to the
// backend's `d/m/Y` wire format happens at save-time. Number fields
// (authorizeCapital / paidUpCapital) stay as strings so the input
// behaves naturally; we coerce on save.
type ProfileForm = {
  // Application Information (component 5257) — JSON keys on vcs_extended_field
  nameApplication: string;
  telNoApplication: string;
  // Vendor Portal (component 4737)
  vendorName: string;
  email: string;
  telNo: string;
  faxNo: string;
  bumiStatus: string;
  contactPerson: string;
  taxRegNo: string;
  epfNo: string;
  socsoNo: string;
  billerCode: string;
  isCreditor: "" | "Y" | "N";
  isDebtor: "" | "Y" | "N";
  icNo: string;
  companyCategory: string;
  authorizeCapital: string;
  paidUpCapital: string;
  // Vendor Registration Detail (component 4738) — date inputs in yyyy-mm-dd
  registrationNo: string;
  regDate: string;
  regExpDate: string;
  kkRegNo: string;
  kkExpiredDate: string;
  regNoKpm: string;
  regDateKpm: string;
  regExpdateKpm: string;
  rosNo: string;
};

function emptyForm(): ProfileForm {
  return {
    nameApplication: "",
    telNoApplication: "",
    vendorName: "",
    email: "",
    telNo: "",
    faxNo: "",
    bumiStatus: "",
    contactPerson: "",
    taxRegNo: "",
    epfNo: "",
    socsoNo: "",
    billerCode: "",
    isCreditor: "",
    isDebtor: "",
    icNo: "",
    companyCategory: "",
    authorizeCapital: "",
    paidUpCapital: "",
    registrationNo: "",
    regDate: "",
    regExpDate: "",
    kkRegNo: "",
    kkExpiredDate: "",
    regNoKpm: "",
    regDateKpm: "",
    regExpdateKpm: "",
    rosNo: "",
  };
}

const formData = reactive<ProfileForm>(emptyForm());
const saving = ref(false);
const lookups = ref<VendorPortalLookups | null>(null);

/** Convert legacy `d/m/Y` -> `yyyy-mm-dd` (HTML5 date input). */
function dmyToInputDate(s: string | null | undefined): string {
  if (!s) return "";
  const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(s);
  if (!m) return "";
  return `${m[3]}-${m[2]}-${m[1]}`;
}

/** Convert HTML5 `yyyy-mm-dd` -> legacy `d/m/Y` for the API payload. */
function inputDateToDmy(s: string | null | undefined): string | null {
  if (!s) return null;
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
  if (!m) return null;
  return `${m[3]}/${m[2]}/${m[1]}`;
}

function syncFormFromProfile(p: VendorPortalProfile | null) {
  const fresh = emptyForm();
  if (p) {
    fresh.nameApplication = p.nameApplication ?? "";
    fresh.telNoApplication = p.telNoApplication ?? "";
    fresh.vendorName = p.vendorName ?? "";
    fresh.email = p.emailAddress ?? "";
    fresh.telNo = p.telNo ?? "";
    fresh.faxNo = p.faxNo ?? "";
    fresh.bumiStatus = p.bumiStatus ?? "";
    fresh.contactPerson = p.contactPerson ?? "";
    fresh.taxRegNo = p.taxRegNo ?? "";
    fresh.epfNo = p.epfNo ?? "";
    fresh.socsoNo = p.socsoNo ?? "";
    fresh.billerCode = p.billerCode ?? "";
    fresh.isCreditor = p.isCreditor === "Y" || p.isCreditor === "N" ? p.isCreditor : "";
    fresh.isDebtor = p.isDebtor === "Y" || p.isDebtor === "N" ? p.isDebtor : "";
    fresh.icNo = p.icNo ?? "";
    fresh.companyCategory = p.companyCategory ?? "";
    fresh.authorizeCapital = p.authorizeCapital !== null ? String(p.authorizeCapital) : "";
    fresh.paidUpCapital = p.paidUpCapital !== null ? String(p.paidUpCapital) : "";
    fresh.registrationNo = p.registrationNo ?? "";
    fresh.regDate = dmyToInputDate(p.registrationDate);
    fresh.regExpDate = dmyToInputDate(p.registrationExpiryDate);
    fresh.kkRegNo = p.kkRegNo ?? "";
    fresh.kkExpiredDate = dmyToInputDate(p.kkExpiredDate);
    fresh.regNoKpm = p.regNoKpm ?? "";
    fresh.regDateKpm = dmyToInputDate(p.regDateKpm);
    fresh.regExpdateKpm = dmyToInputDate(p.regExpDateKpm);
    fresh.rosNo = p.rosNo ?? "";
  }
  Object.assign(formData, fresh);
}

function buildPayload(): VendorPortalProfileInput {
  const num = (s: string): number | null => {
    const t = s.trim();
    if (t === "") return null;
    const n = Number(t);
    return Number.isFinite(n) ? n : null;
  };
  const orNull = (s: string): string | null => {
    const t = s.trim();
    return t === "" ? null : t;
  };
  return {
    vendorName: formData.vendorName.trim(),
    email: formData.email.trim(),
    telNo: formData.telNo.trim(),
    faxNo: orNull(formData.faxNo),
    bumiStatus: formData.bumiStatus.trim(),
    contactPerson: formData.contactPerson.trim(),
    isCreditor: formData.isCreditor === "" ? null : formData.isCreditor,
    isDebtor: formData.isDebtor === "" ? null : formData.isDebtor,
    taxRegNo: orNull(formData.taxRegNo),
    epfNo: orNull(formData.epfNo),
    socsoNo: orNull(formData.socsoNo),
    billerCode: orNull(formData.billerCode),
    icNo: orNull(formData.icNo),
    companyCategory: orNull(formData.companyCategory),
    authorizeCapital: num(formData.authorizeCapital),
    paidUpCapital: num(formData.paidUpCapital),
    registrationNo: orNull(formData.registrationNo),
    regDate: inputDateToDmy(formData.regDate),
    regExpDate: inputDateToDmy(formData.regExpDate),
    kkRegNo: formData.kkRegNo.trim(),
    kkExpiredDate: inputDateToDmy(formData.kkExpiredDate),
    regNoKpm: orNull(formData.regNoKpm),
    regDateKpm: inputDateToDmy(formData.regDateKpm),
    regExpdateKpm: inputDateToDmy(formData.regExpdateKpm),
    rosNo: formData.rosNo.trim(),
    nameApplication: orNull(formData.nameApplication),
    telNoApplication: orNull(formData.telNoApplication),
  };
}

const requiredMissing = computed(() => {
  // Mirror UpdateVendorPortalProfileRequest::rules() so we surface a
  // friendly message before the API rejects the payload. Server-side
  // validation is still authoritative.
  const missing: string[] = [];
  if (!formData.nameApplication.trim()) missing.push("Name (Application)");
  if (!formData.telNoApplication.trim()) missing.push("Telephone No (Application)");
  if (!formData.vendorName.trim()) missing.push("Vendor Name");
  if (!formData.email.trim()) missing.push("Email");
  if (!formData.telNo.trim()) missing.push("Telephone No");
  if (!formData.bumiStatus.trim()) missing.push("Taraf");
  if (!formData.contactPerson.trim()) missing.push("Contact Person");
  if (!formData.kkRegNo.trim()) missing.push("Registration No (MOF)");
  if (!formData.rosNo.trim()) missing.push("ROS No");
  return missing;
});

async function saveProfile() {
  if (saving.value) return;
  if (requiredMissing.value.length) {
    toast.error(
      "Missing required fields",
      `Please fill in: ${requiredMissing.value.join(", ")}.`,
    );
    return;
  }
  saving.value = true;
  try {
    const res = await updateVendorPortalProfile(buildPayload(), buildProfileParams());
    profile.value = res.data;
    syncFormFromProfile(res.data);
    toast.success("Saved", "Vendor profile updated.");
  } catch (e) {
    if (captureResolutionError(e)) return;
    const err = e as { code?: string; details?: unknown; message?: string } | null;
    if (err && err.code === "VALIDATION_ERROR") {
      const details = (err.details ?? {}) as Record<string, string[] | string>;
      const lines: string[] = [];
      for (const [k, v] of Object.entries(details)) {
        const text = Array.isArray(v) ? v.join(" ") : String(v);
        lines.push(`${k}: ${text}`);
      }
      toast.error("Validation error", lines.join("\n") || "Please review the form.");
      return;
    }
    toast.error(
      "Save failed",
      e instanceof Error ? e.message : "Unable to save vendor profile.",
    );
  } finally {
    saving.value = false;
  }
}

function cancelEdits() {
  syncFormFromProfile(profile.value);
}

async function loadLookups() {
  try {
    const res = await getVendorPortalLookups(buildProfileParams());
    lookups.value = res.data;
  } catch (e) {
    if (captureResolutionError(e)) return;
    // Lookups are non-fatal — surface but keep the form usable.
    toast.error(
      "Lookups failed",
      e instanceof Error ? e.message : "Unable to load dropdown options.",
    );
  }
}

const sectionTabs: { id: SectionId; label: string }[] = [
  { id: "category", label: "Category" },
  { id: "account", label: "Account" },
  { id: "address", label: "Address" },
  { id: "jobscope", label: "Jobscope" },
  { id: "ssm", label: "SSM" },
  { id: "mof", label: "MOF" },
  { id: "other", label: "Other" },
];

watch(
  () => activeSection.value,
  () => {
    /* loaders are dispatched explicitly via setSection */
  },
);

onMounted(async () => {
  await loadProfile();
  if (!resolutionError.value) {
    await Promise.all([loadLookups(), loadCategory()]);
  }
});

onUnmounted(() => {
  for (const k of Object.keys(debounceTimers) as SectionId[]) {
    if (debounceTimers[k]) {
      clearTimeout(debounceTimers[k]!);
      debounceTimers[k] = null;
    }
  }
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Vendor Portal / Vendor Portal</h1>

      <article
        v-if="resolutionError"
        class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm"
      >
        <h2 class="text-base font-semibold text-amber-900">
          Vendor record not resolved
        </h2>
        <p class="mt-1 text-sm text-amber-900">{{ resolutionError.message }}</p>
        <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-amber-800">
              Authenticated email
            </dt>
            <dd class="text-sm text-amber-900">{{ resolutionError.authEmail || "—" }}</dd>
          </div>
          <div>
            <dt class="text-xs font-medium uppercase tracking-wide text-amber-800">
              Authenticated name
            </dt>
            <dd class="text-sm text-amber-900">{{ resolutionError.authName || "—" }}</dd>
          </div>
        </dl>
        <div v-if="Object.keys(resolutionError.tried).length" class="mt-3">
          <p class="text-xs font-medium uppercase tracking-wide text-amber-800">
            Identifiers tried against the legacy <code>vend_customer_supplier</code> table
          </p>
          <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900">
            <li v-for="(value, key) in resolutionError.tried" :key="key">
              <code>{{ key }}</code> = <code>{{ value }}</code>
            </li>
          </ul>
        </div>

        <!-- Operator override: lets admins (audit.read) preview the
             portal as a specific vendor without rebinding users.name. -->
        <form class="mt-4 space-y-2" @submit.prevent="applyOverride">
          <label class="text-xs font-medium uppercase tracking-wide text-amber-800">
            View as vendor (operators only)
          </label>
          <div class="flex flex-wrap items-center gap-2">
            <input
              v-model="overrideInput"
              type="text"
              placeholder="vcs_vendor_code (e.g. V0001)"
              class="w-full max-w-xs rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-amber-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            <button
              type="submit"
              class="rounded-lg border border-amber-400 bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-900 hover:bg-amber-200 disabled:opacity-50"
              :disabled="overrideInput.trim() === ''"
            >
              Load vendor
            </button>
            <button
              v-if="viewAsCode"
              type="button"
              class="rounded-lg border border-amber-300 bg-white px-3 py-1.5 text-sm text-amber-900 hover:bg-amber-50"
              @click="clearOverride"
            >
              Clear override
            </button>
          </div>
          <p class="text-xs text-amber-800">
            Requires the <code>audit.read</code> permission. Non-operators
            will keep seeing this panel even after submitting.
          </p>
        </form>

        <p class="mt-3 text-xs text-amber-800">
          Otherwise, set <code>users.name</code> to a value present in
          <code>vend_customer_supplier.vcs_vendor_code</code> (legacy FIMS
          convention: username == vendor code) or
          <code>users.email</code> to one present in
          <code>vcs_email_address</code>.
        </p>
      </article>

      <form
        v-if="!resolutionError"
        class="space-y-4"
        @submit.prevent="saveProfile"
      >
        <!-- Application Information (component 5257) -->
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Application Information</h2>
          <div v-if="profileLoading" class="mt-4 text-sm text-slate-500">Loading profile…</div>
          <div v-else class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Name <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.nameApplication"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Telephone No <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.telNoApplication"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
          </div>
        </article>

        <!-- Vendor Portal (component 4737) -->
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Vendor Portal</h2>
          <div v-if="profileLoading" class="mt-4 text-sm text-slate-500">Loading profile…</div>
          <div v-else class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
            <!-- Vendor Temp Code (system-managed by renewal flow) -->
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Vendor Temp Code</span>
              <input
                :value="profile?.tempCode || profile?.vendorCode || 'Auto Assigned'"
                type="text"
                disabled
                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500"
              />
            </label>
            <!-- spacer to keep two-column rhythm -->
            <span class="hidden md:block"></span>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Vendor Name <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.vendorName"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Email <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.email"
                type="email"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Telephone No <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.telNo"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Fax No</span>
              <input
                v-model="formData.faxNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Taraf <span class="text-rose-600">*</span>
              </span>
              <select
                v-model="formData.bumiStatus"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              >
                <option value="" disabled>Select…</option>
                <option
                  v-for="opt in lookups?.taraf ?? []"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Contact Person <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.contactPerson"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">No. GST</span>
              <input
                v-model="formData.taxRegNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Vendor Status</span>
              <input
                :value="profile?.vendorStatus || 'ENTRY'"
                type="text"
                disabled
                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500"
              />
            </label>

            <!-- The unv_reg_date / unv_req_exp_date columns are written
                 by the renewal/approval workflow (Phase 2c), so we
                 render them disabled here. The renewal flow defaults
                 them to today + 2 years on `addDetail`. -->
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Date</span>
              <input
                :value="profile?.unvRegDate || ''"
                type="text"
                disabled
                placeholder="dd/mm/yyyy"
                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Expiry Date</span>
              <input
                :value="profile?.unvReqExpDate || ''"
                type="text"
                disabled
                placeholder="dd/mm/yyyy"
                class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">KWSP No</span>
              <input
                v-model="formData.epfNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">SOCSO No</span>
              <input
                v-model="formData.socsoNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1 md:col-span-2">
              <span class="text-sm text-slate-700">JomPay Biller Code</span>
              <input
                v-model="formData.billerCode"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
          </div>
        </article>

        <!-- Vendor Registration Detail (component 4738) -->
        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
          <h2 class="text-base font-semibold text-slate-900">Vendor Registration Detail</h2>
          <div v-if="profileLoading" class="mt-4 text-sm text-slate-500">Loading profile…</div>
          <div v-else class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 md:grid-cols-2">
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration No (SSM)</span>
              <input
                v-model="formData.registrationNo"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">
                Registration No (MOF) <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.kkRegNo"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Date (SSM)</span>
              <input
                v-model="formData.regDate"
                type="date"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Expiry Date (MOF)</span>
              <input
                v-model="formData.kkExpiredDate"
                type="date"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Expiry Date (SSM)</span>
              <input
                v-model="formData.regExpDate"
                type="date"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration No (MOTAC)</span>
              <input
                v-model="formData.regNoKpm"
                type="text"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Expired Date (MOTAC)</span>
              <input
                v-model="formData.regExpdateKpm"
                type="date"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
            <label class="space-y-1">
              <span class="text-sm text-slate-700">Registration Date (MOTAC)</span>
              <input
                v-model="formData.regDateKpm"
                type="date"
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>

            <label class="space-y-1 md:col-span-2">
              <span class="text-sm text-slate-700">
                ROS No <span class="text-rose-600">*</span>
              </span>
              <input
                v-model="formData.rosNo"
                type="text"
                required
                class="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-900 focus:border-slate-500 focus:outline-none focus:ring-1 focus:ring-slate-500"
              />
            </label>
          </div>
        </article>

        <!-- Form actions -->
        <div class="flex items-center justify-end gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50"
            :disabled="profileLoading || saving"
            @click="cancelEdits"
          >
            <Undo2 class="h-4 w-4" />
            Cancel
          </button>
          <button
            type="submit"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-900 bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
            :disabled="profileLoading || saving"
          >
            <Save class="h-4 w-4" />
            {{ saving ? "Saving…" : "Save" }}
          </button>
        </div>
      </form>

      <nav v-if="!resolutionError" class="flex flex-wrap gap-2">
        <button
          v-for="t in sectionTabs"
          :key="t.id"
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm transition"
          :class="
            activeSection === t.id
              ? 'border-slate-900 bg-slate-900 text-white'
              : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
          "
          @click="setSection(t.id)"
        >
          {{ t.label }}
        </button>
      </nav>

      <!-- Category -->
      <article
        v-if="!resolutionError && activeSection === 'category'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">Category</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="categoryState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('category')"
              @keydown.enter.prevent="flushSearch('category')"
            />
            <button
              v-if="categoryState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('category')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="categoryState.loading">
                <td colspan="4" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="categoryState.rows.length === 0">
                <td colspan="4" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in categoryState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.categoryLabel || "—" }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(categoryState) }}–{{ endIdxFor(categoryState) }} of {{ categoryState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="categoryState.page <= 1" @click="prevPage('category')">Prev</button>
            <span>Page {{ categoryState.page }} of {{ totalPagesFor(categoryState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="categoryState.page >= totalPagesFor(categoryState)" @click="nextPage('category')">Next</button>
          </div>
        </footer>
      </article>

      <!-- Account -->
      <article
        v-if="!resolutionError && activeSection === 'account'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">Account</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="accountState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('account')"
              @keydown.enter.prevent="flushSearch('account')"
            />
            <button
              v-if="accountState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('account')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Bank</th>
                <th class="px-3 py-2">Account No.</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="accountState.loading">
                <td colspan="6" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="accountState.rows.length === 0">
                <td colspan="6" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in accountState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.bankName || "—" }}</td>
                <td class="px-3 py-2">{{ row.bankAccountNo || "—" }}</td>
                <td class="px-3 py-2">{{ row.status }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(accountState) }}–{{ endIdxFor(accountState) }} of {{ accountState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="accountState.page <= 1" @click="prevPage('account')">Prev</button>
            <span>Page {{ accountState.page }} of {{ totalPagesFor(accountState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="accountState.page >= totalPagesFor(accountState)" @click="nextPage('account')">Next</button>
          </div>
        </footer>
      </article>

      <!-- Address -->
      <article
        v-if="!resolutionError && activeSection === 'address'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">Address</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="addressState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('address')"
              @keydown.enter.prevent="flushSearch('address')"
            />
            <button
              v-if="addressState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('address')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">Address 1</th>
                <th class="px-3 py-2">Address 2</th>
                <th class="px-3 py-2">Address 3</th>
                <th class="px-3 py-2">Postcode</th>
                <th class="px-3 py-2">City</th>
                <th class="px-3 py-2">State</th>
                <th class="px-3 py-2">Country</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="addressState.loading">
                <td colspan="9" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="addressState.rows.length === 0">
                <td colspan="9" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in addressState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.addressTypeLabel || "—" }}</td>
                <td class="px-3 py-2">{{ row.address1 || "—" }}</td>
                <td class="px-3 py-2">{{ row.address2 || "—" }}</td>
                <td class="px-3 py-2">{{ row.address3 || "—" }}</td>
                <td class="px-3 py-2">{{ row.postcode || "—" }}</td>
                <td class="px-3 py-2">{{ row.city || "—" }}</td>
                <td class="px-3 py-2">{{ row.state || "—" }}</td>
                <td class="px-3 py-2">{{ row.country || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(addressState) }}–{{ endIdxFor(addressState) }} of {{ addressState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="addressState.page <= 1" @click="prevPage('address')">Prev</button>
            <span>Page {{ addressState.page }} of {{ totalPagesFor(addressState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="addressState.page >= totalPagesFor(addressState)" @click="nextPage('address')">Next</button>
          </div>
        </footer>
      </article>

      <!-- Jobscope -->
      <article
        v-if="!resolutionError && activeSection === 'jobscope'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">Jobscope</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="jobscopeState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('jobscope')"
              @keydown.enter.prevent="flushSearch('jobscope')"
            />
            <button
              v-if="jobscopeState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('jobscope')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Jobscope</th>
                <th class="px-3 py-2">Category</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="jobscopeState.loading">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="jobscopeState.rows.length === 0">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in jobscopeState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.jobscopeLabel || "—" }}</td>
                <td class="px-3 py-2">{{ row.category || "—" }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(jobscopeState) }}–{{ endIdxFor(jobscopeState) }} of {{ jobscopeState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="jobscopeState.page <= 1" @click="prevPage('jobscope')">Prev</button>
            <span>Page {{ jobscopeState.page }} of {{ totalPagesFor(jobscopeState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="jobscopeState.page >= totalPagesFor(jobscopeState)" @click="nextPage('jobscope')">Next</button>
          </div>
        </footer>
      </article>

      <!-- SSM -->
      <article
        v-if="!resolutionError && activeSection === 'ssm'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">SSM Licence</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="ssmState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('ssm')"
              @keydown.enter.prevent="flushSearch('ssm')"
            />
            <button
              v-if="ssmState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('ssm')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Licence</th>
                <th class="px-3 py-2">Description</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="ssmState.loading">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="ssmState.rows.length === 0">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in ssmState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceLabel || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceDesc || "—" }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(ssmState) }}–{{ endIdxFor(ssmState) }} of {{ ssmState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="ssmState.page <= 1" @click="prevPage('ssm')">Prev</button>
            <span>Page {{ ssmState.page }} of {{ totalPagesFor(ssmState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="ssmState.page >= totalPagesFor(ssmState)" @click="nextPage('ssm')">Next</button>
          </div>
        </footer>
      </article>

      <!-- MOF -->
      <article
        v-if="!resolutionError && activeSection === 'mof'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">MOF Licence</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="mofState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('mof')"
              @keydown.enter.prevent="flushSearch('mof')"
            />
            <button
              v-if="mofState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('mof')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Licence</th>
                <th class="px-3 py-2">Description</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="mofState.loading">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="mofState.rows.length === 0">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in mofState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceLabel || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceDesc || "—" }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(mofState) }}–{{ endIdxFor(mofState) }} of {{ mofState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="mofState.page <= 1" @click="prevPage('mof')">Prev</button>
            <span>Page {{ mofState.page }} of {{ totalPagesFor(mofState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="mofState.page >= totalPagesFor(mofState)" @click="nextPage('mof')">Next</button>
          </div>
        </footer>
      </article>

      <!-- Other -->
      <article
        v-if="!resolutionError && activeSection === 'other'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-base font-semibold text-slate-900">Other Licence</h2>
          <div class="relative w-full max-w-xs">
            <Search class="absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              v-model="otherState.q"
              type="search"
              placeholder="Filter rows…"
              class="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-8 text-sm text-slate-900"
              @input="onSearchInput('other')"
              @keydown.enter.prevent="flushSearch('other')"
            />
            <button
              v-if="otherState.q"
              type="button"
              class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              aria-label="Clear search"
              @click="clearSearch('other')"
            >
              <X class="h-4 w-4" />
            </button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
              <tr>
                <th class="px-3 py-2 w-12">#</th>
                <th class="px-3 py-2">Vendor Code</th>
                <th class="px-3 py-2">Licence Code</th>
                <th class="px-3 py-2">Description</th>
                <th class="px-3 py-2">Created</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-sm text-slate-900">
              <tr v-if="otherState.loading">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="otherState.rows.length === 0">
                <td colspan="5" class="px-3 py-6 text-center text-slate-500">No data.</td>
              </tr>
              <tr v-for="row in otherState.rows" :key="row.id ?? row.index">
                <td class="px-3 py-2 text-slate-500">{{ row.index }}</td>
                <td class="px-3 py-2">{{ row.vendorCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceCode || "—" }}</td>
                <td class="px-3 py-2">{{ row.licenceDesc || "—" }}</td>
                <td class="px-3 py-2">{{ row.createdDate || "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
          <span>{{ startIdxFor(otherState) }}–{{ endIdxFor(otherState) }} of {{ otherState.total }}</span>
          <div class="flex items-center gap-2">
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="otherState.page <= 1" @click="prevPage('other')">Prev</button>
            <span>Page {{ otherState.page }} of {{ totalPagesFor(otherState) }}</span>
            <button class="rounded border border-slate-300 bg-white px-2 py-1 disabled:opacity-50" :disabled="otherState.page >= totalPagesFor(otherState)" @click="nextPage('other')">Next</button>
          </div>
        </footer>
      </article>
    </div>
  </AdminLayout>
</template>
