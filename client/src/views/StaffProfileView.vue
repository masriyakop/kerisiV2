<script setup lang="ts">
/**
 * Portal / Staff Profile (PAGEID 1581 / MENUID 1914)
 *
 * Source: legacy FIMS BL `API_PORTAL_SALARYPROFILEINFORMATION`. Self-
 * service portal that shows a read-only profile card on top and lets
 * the staff toggle between four sections (Address, Marital Status,
 * Spouse, Children).
 *
 * Migration scope:
 *   - Read profile (master), address, children, spouses, family-children
 *   - Save address (with handphone)
 *   - Update marital status (modal)
 *
 * Out of scope (legacy MENUIDs 3301 / 3305 — spouse / child detail
 * forms): the "New" / "Edit" / "View" actions in the legacy datatables
 * routed to those sub-pages. They are NOT migrated here, so the
 * datatables surface as read-only.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Save, Search, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  getStaffProfileAddress,
  getStaffProfileMaster,
  getStaffProfileOptions,
  listStaffProfileChildren,
  listStaffProfileSpouseChildren,
  listStaffProfileSpouses,
  updateStaffProfileAddress,
  updateStaffProfileMaritalStatus,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  StaffProfileAddress,
  StaffProfileChildRow,
  StaffProfileMaster,
  StaffProfileOptions,
  StaffProfileSpouseRow,
} from "@/types";

type SectionId = "address" | "marital" | "spouse" | "children";

const toast = useToast();
const confirmDialog = useConfirmDialog();

const activeSection = ref<SectionId>("address");

// Master profile.
const profile = ref<StaffProfileMaster | null>(null);
const profileLoading = ref(false);

// Resolution diagnostic (captured when STAFF_NOT_RESOLVED is returned).
type ResolutionError = {
  message: string;
  authEmail: string | null;
  authName: string | null;
  tried: Record<string, string>;
};
const resolutionError = ref<ResolutionError | null>(null);

// Lookup options (state, country, marital, address-type).
const options = ref<StaffProfileOptions>({
  maritalStatus: [],
  state: [],
  country: [],
  addressType: [],
});

// Address form.
const addressForm = ref<StaffProfileAddress | null>(null);
const addressLoading = ref(false);
const addressSaving = ref(false);
const addressClarified = ref(false);

// Marital modal.
const maritalOpen = ref(false);
const maritalValue = ref("");
const maritalClarified = ref(false);
const maritalSaving = ref(false);

// Spouse datatable.
const spouseRows = ref<StaffProfileSpouseRow[]>([]);
const spouseTotal = ref(0);
const spousePage = ref(1);
const spouseLimit = ref(10);
const spouseQ = ref("");
const spouseSortBy = ref<
  | "spo_spouse_seq"
  | "spo_name"
  | "spo_ic_no"
  | "spo_tax_no"
  | "spo_marriage_date"
  | "spo_divorce_date"
  | "spo_death_date"
>("spo_spouse_seq");
const spouseSortDir = ref<"asc" | "desc">("asc");
const spouseLoading = ref(false);
let spouseDebounce: ReturnType<typeof setTimeout> | null = null;

// Children (all + per-spouse).
const childrenRows = ref<StaffProfileChildRow[]>([]);
const childrenTotal = ref(0);
const childrenPage = ref(1);
const childrenLimit = ref(10);
const childrenQ = ref("");
const childrenSortBy = ref<
  | "stc_child_seq"
  | "stc_name"
  | "stc_ic_ref_no"
  | "stc_bod"
  | "stc_relation"
  | "age"
  | "stc_level_study"
  | "stc_disability_status"
  | "stc_pcb_status"
  | "stc_death_date"
>("stc_child_seq");
const childrenSortDir = ref<"asc" | "desc">("asc");
const childrenLoading = ref(false);
let childrenDebounce: ReturnType<typeof setTimeout> | null = null;

// Drilldown: children for a specific spouse seq.
const selectedSpouseSeq = ref<string | null>(null);
const spouseChildrenRows = ref<StaffProfileChildRow[]>([]);
const spouseChildrenTotal = ref(0);
const spouseChildrenLoading = ref(false);

const spouseTotalPages = computed(() =>
  spouseTotal.value ? Math.max(1, Math.ceil(spouseTotal.value / spouseLimit.value)) : 1,
);
const childrenTotalPages = computed(() =>
  childrenTotal.value ? Math.max(1, Math.ceil(childrenTotal.value / childrenLimit.value)) : 1,
);

function captureResolutionError(e: unknown): boolean {
  const err = e as { code?: string; details?: unknown; message?: string } | null;
  if (err && err.code === "STAFF_NOT_RESOLVED") {
    const details = (err.details ?? {}) as {
      message?: string;
      auth?: { email?: string | null; name?: string | null };
      tried?: Record<string, string>;
    };
    resolutionError.value = {
      message: details.message ?? err.message ?? "Authenticated user could not be matched to a staff record.",
      authEmail: details.auth?.email ?? null,
      authName: details.auth?.name ?? null,
      tried: details.tried ?? {},
    };
    return true;
  }
  return false;
}

async function loadProfile() {
  profileLoading.value = true;
  try {
    const res = await getStaffProfileMaster();
    profile.value = res.data;
    resolutionError.value = null;
  } catch (e) {
    if (!captureResolutionError(e)) {
      toast.error("Load failed", e instanceof Error ? e.message : "Unable to load staff profile.");
    }
  } finally {
    profileLoading.value = false;
  }
}

async function loadOptions() {
  try {
    const res = await getStaffProfileOptions();
    options.value = res.data;
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Options unavailable", e instanceof Error ? e.message : "Lookup options failed to load.");
  }
}

async function loadAddress() {
  if (resolutionError.value) return;
  addressLoading.value = true;
  try {
    const res = await getStaffProfileAddress();
    addressForm.value = res.data;
    addressClarified.value = res.data.isAcknowledgement === 1;
  } catch (e) {
    if (captureResolutionError(e)) return;
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load address.");
  } finally {
    addressLoading.value = false;
  }
}

async function saveAddress() {
  if (!addressForm.value) return;
  if (!addressClarified.value) {
    toast.error("Acknowledgement required", "Please confirm the clarification before saving.");
    return;
  }
  if (!addressForm.value.saAddress1?.trim()) {
    toast.error("Validation error", "Address line 1 is required.");
    return;
  }
  addressSaving.value = true;
  try {
    await updateStaffProfileAddress({
      saAddressType: addressForm.value.saAddressType ?? 1,
      saAddress1: addressForm.value.saAddress1 ?? "",
      saAddress2: addressForm.value.saAddress2 ?? null,
      saPcode: addressForm.value.saPcode ?? null,
      saCity: addressForm.value.saCity ?? null,
      saState: addressForm.value.saState ?? null,
      saCountry: addressForm.value.saCountry ?? null,
      stfHandphoneNo: addressForm.value.stfHandphoneNo ?? null,
    });
    toast.success("Saved", "Address updated successfully.");
    await loadAddress();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save address.");
  } finally {
    addressSaving.value = false;
  }
}

function openMaritalModal() {
  maritalValue.value = profile.value?.staffDetails?.stfMaritalStatus ?? "";
  maritalClarified.value =
    (profile.value?.staffDetails?.isAcknowledgeMarital ?? 0) === 1;
  maritalOpen.value = true;
}

async function saveMarital() {
  if (!maritalValue.value) {
    toast.error("Validation error", "Please select a marital status.");
    return;
  }
  if (!maritalClarified.value) {
    toast.error("Acknowledgement required", "Please confirm the clarification before saving.");
    return;
  }
  const ok = await confirmDialog.confirm({
    title: "Update marital status",
    message: "This will update your profile and cannot be undone without contacting HR.",
    confirmText: "Update",
  });
  if (!ok) return;

  maritalSaving.value = true;
  try {
    await updateStaffProfileMaritalStatus({ maritalStatus: maritalValue.value });
    toast.success("Updated", "Marital status updated successfully.");
    maritalOpen.value = false;
    await loadProfile();
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to update marital status.");
  } finally {
    maritalSaving.value = false;
  }
}

async function loadSpouses() {
  spouseLoading.value = true;
  const params = new URLSearchParams({
    page: String(spousePage.value),
    limit: String(spouseLimit.value),
    sort_by: spouseSortBy.value,
    sort_dir: spouseSortDir.value,
    ...(spouseQ.value ? { q: spouseQ.value } : {}),
  });
  try {
    const res = await listStaffProfileSpouses(`?${params.toString()}`);
    spouseRows.value = res.data;
    spouseTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load spouses.");
  } finally {
    spouseLoading.value = false;
  }
}

async function loadChildren() {
  childrenLoading.value = true;
  const params = new URLSearchParams({
    page: String(childrenPage.value),
    limit: String(childrenLimit.value),
    sort_by: childrenSortBy.value,
    sort_dir: childrenSortDir.value,
    ...(childrenQ.value ? { q: childrenQ.value } : {}),
  });
  try {
    const res = await listStaffProfileChildren(`?${params.toString()}`);
    childrenRows.value = res.data;
    childrenTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load children.");
  } finally {
    childrenLoading.value = false;
  }
}

async function loadSpouseChildren(seq: string) {
  spouseChildrenLoading.value = true;
  selectedSpouseSeq.value = seq;
  try {
    const res = await listStaffProfileSpouseChildren(seq, "?page=1&limit=50");
    spouseChildrenRows.value = res.data;
    spouseChildrenTotal.value = Number(res.meta?.total ?? 0);
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load children for spouse.");
  } finally {
    spouseChildrenLoading.value = false;
  }
}

function clearSpouseSelection() {
  selectedSpouseSeq.value = null;
  spouseChildrenRows.value = [];
  spouseChildrenTotal.value = 0;
}

function debouncedSpouseSearch(value: string) {
  spouseQ.value = value;
  if (spouseDebounce) clearTimeout(spouseDebounce);
  spouseDebounce = setTimeout(() => {
    spousePage.value = 1;
    loadSpouses();
  }, 350);
}

function debouncedChildrenSearch(value: string) {
  childrenQ.value = value;
  if (childrenDebounce) clearTimeout(childrenDebounce);
  childrenDebounce = setTimeout(() => {
    childrenPage.value = 1;
    loadChildren();
  }, 350);
}

function flushSpouseSearch() {
  if (spouseDebounce) clearTimeout(spouseDebounce);
  spousePage.value = 1;
  loadSpouses();
}

function flushChildrenSearch() {
  if (childrenDebounce) clearTimeout(childrenDebounce);
  childrenPage.value = 1;
  loadChildren();
}

function clearSpouseSearch() {
  spouseQ.value = "";
  flushSpouseSearch();
}

function clearChildrenSearch() {
  childrenQ.value = "";
  flushChildrenSearch();
}

function setSection(next: SectionId) {
  activeSection.value = next;
  if (next === "address") loadAddress();
  else if (next === "spouse") {
    clearSpouseSelection();
    loadSpouses();
  } else if (next === "children") loadChildren();
  else if (next === "marital") openMaritalModal();
}

watch(
  () => [spousePage.value, spouseSortBy.value, spouseSortDir.value],
  () => {
    if (activeSection.value === "spouse") loadSpouses();
  },
);

watch(
  () => [childrenPage.value, childrenSortBy.value, childrenSortDir.value],
  () => {
    if (activeSection.value === "children") loadChildren();
  },
);

onMounted(async () => {
  await Promise.all([loadProfile(), loadOptions()]);
  // Open the address section by default — the legacy onload doesn't
  // pre-select any of the four panels, but the user always lands on
  // the page wanting to do *something* with their profile. Address is
  // the most common write-path so we open it here.
  await loadAddress();
});

onUnmounted(() => {
  if (spouseDebounce) clearTimeout(spouseDebounce);
  if (childrenDebounce) clearTimeout(childrenDebounce);
});

const profileFields = computed(() => {
  const d = profile.value?.staffDetails;
  if (!d) return [] as { label: string; value: string }[];
  return [
    { label: "Staff Name", value: d.stfStaffName ?? "—" },
    { label: "Staff No", value: d.stfStaffId ?? "—" },
    { label: "NRIC", value: d.stfIcNo ?? "—" },
    { label: "Position", value: d.jobStatus ?? "—" },
    { label: "PTJ", value: d.ounDesc ?? "—" },
    { label: "Cost Centre", value: d.ccrCostcentreDesc || "—" },
    { label: "Service Scheme", value: d.sscServiceDescProfile ?? "—" },
    { label: "Salary Grade", value: d.stsSalaryGrade ?? "—" },
    { label: "Join Date", value: d.stsJoinDate ?? "—" },
    { label: "Marital Status", value: d.maritalstatusDesc ?? "—" },
    { label: "Email", value: d.stfEmailAddr ?? "—" },
    { label: "Office Tel", value: d.stfTelnoWork ?? "—" },
    { label: "Handphone", value: d.stfHandphoneNo ?? "—" },
    { label: "Basic Salary", value: d.salBasicSalary ?? "—" },
    { label: "Bank Account No", value: d.staAcctNoProfile ?? "—" },
    { label: "Bank Account Name", value: d.staAcctNameProfile ?? "—" },
    { label: "Pension Status", value: d.pensionstatusDesc ?? "—" },
    { label: "Tax Category", value: d.salTaxCategory ?? "—" },
    { label: "Tax Group", value: d.salTaxGroup ?? "—" },
    { label: "SOCSO", value: d.salSocsoStatus ?? "—" },
    { label: "EPF (Employee)", value: d.salEpfStatus ?? "—" },
    {
      label: "Zakat",
      value:
        d.salZakatDesc ||
        (profile.value?.zakatAmount
          ? `${profile.value.zakatAmount}${profile.value.zakatPeriod ? ` (${profile.value.zakatPeriod})` : ""}`
          : "—"),
    },
    { label: "Salary Increment Month", value: d.stfSalIncrDate ?? "—" },
  ];
});

function toggleSpouseSort(col: typeof spouseSortBy.value) {
  if (spouseSortBy.value === col) {
    spouseSortDir.value = spouseSortDir.value === "asc" ? "desc" : "asc";
  } else {
    spouseSortBy.value = col;
    spouseSortDir.value = "asc";
  }
}

function toggleChildrenSort(col: typeof childrenSortBy.value) {
  if (childrenSortBy.value === col) {
    childrenSortDir.value = childrenSortDir.value === "asc" ? "desc" : "asc";
  } else {
    childrenSortBy.value = col;
    childrenSortDir.value = "asc";
  }
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Portal / Staff Profile</h1>

      <article
        v-if="resolutionError"
        class="rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm"
      >
        <h2 class="text-base font-semibold text-amber-900">
          Staff record not resolved
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
            Identifiers tried against the legacy <code>staff</code> table
          </p>
          <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-amber-900">
            <li v-for="(value, key) in resolutionError.tried" :key="key">
              <code>{{ key }}</code> = <code>{{ value }}</code>
            </li>
          </ul>
        </div>
        <p class="mt-3 text-xs text-amber-800">
          Update the logged-in user's <code>email</code> to match a row in
          <code>staff.stf_email_addr</code>, or set <code>users.name</code> to
          a value present in <code>staff.stf_ad_username</code> or
          <code>staff.stf_staff_id</code>.
        </p>
      </article>

      <article
        v-if="!resolutionError"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
      >
        <h2 class="text-base font-semibold text-slate-900">Profile</h2>
        <p class="mt-1 text-sm text-slate-500">Read-only summary sourced from your HR records.</p>

        <div v-if="profileLoading" class="mt-4 text-sm text-slate-500">Loading profile…</div>
        <dl
          v-else-if="profile?.staffDetails"
          class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3"
        >
          <div v-for="(f, idx) in profileFields" :key="idx">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ f.label }}</dt>
            <dd class="text-sm text-slate-900">{{ f.value }}</dd>
          </div>
        </dl>
        <p v-else class="mt-4 text-sm text-rose-600">
          We could not match your account to an active staff record. Please contact HR if this is unexpected.
        </p>
      </article>

      <nav class="flex flex-wrap gap-2">
        <button
          v-for="s in ([
            { id: 'address', label: 'Address' },
            { id: 'marital', label: 'Marital Status' },
            { id: 'spouse', label: 'Spouse' },
            { id: 'children', label: 'Children' },
          ] as { id: SectionId; label: string }[])"
          :key="s.id"
          type="button"
          class="rounded-lg border px-3 py-1.5 text-sm transition"
          :class="
            activeSection === s.id
              ? 'border-slate-900 bg-slate-900 text-white'
              : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
          "
          @click="setSection(s.id)"
        >
          {{ s.label }}
        </button>
      </nav>

      <article
        v-if="activeSection === 'address'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4"
      >
        <header class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-900">Address</h2>
          <span v-if="addressLoading" class="text-xs text-slate-500">Loading…</span>
        </header>

        <div v-if="addressForm" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-medium text-slate-600">Address Type</label>
            <select
              v-model="addressForm.saAddressType"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            >
              <option v-for="o in options.addressType" :key="o.value" :value="Number(o.value)">{{ o.label }}</option>
              <option v-if="!options.addressType.length" :value="1">Current</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600">Postcode</label>
            <input
              v-model="addressForm.saPcode"
              type="text"
              maxlength="20"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600">Address Line 1 <span class="text-rose-500">*</span></label>
            <input
              v-model="addressForm.saAddress1"
              type="text"
              maxlength="255"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-slate-600">Address Line 2</label>
            <input
              v-model="addressForm.saAddress2"
              type="text"
              maxlength="255"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600">City</label>
            <input
              v-model="addressForm.saCity"
              type="text"
              maxlength="100"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600">State</label>
            <select
              v-if="options.state.length"
              v-model="addressForm.saState"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            >
              <option :value="null">— Select —</option>
              <option v-for="o in options.state" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input
              v-else
              v-model="addressForm.saState"
              type="text"
              maxlength="100"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600">Country</label>
            <select
              v-if="options.country.length"
              v-model="addressForm.saCountry"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            >
              <option :value="null">— Select —</option>
              <option v-for="o in options.country" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
            <input
              v-else
              v-model="addressForm.saCountry"
              type="text"
              maxlength="100"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600">Handphone No</label>
            <input
              v-model="addressForm.stfHandphoneNo"
              type="text"
              inputmode="numeric"
              pattern="\d*"
              maxlength="30"
              class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
              @input="
                (e) => {
                  if (!addressForm) return;
                  addressForm.stfHandphoneNo = (e.target as HTMLInputElement).value.replace(/[^0-9]/g, '');
                }
              "
            />
          </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input v-model="addressClarified" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
          I confirm the information above is correct and up to date.
        </label>

        <div class="flex justify-end">
          <button
            type="button"
            class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
            :disabled="!addressClarified || addressSaving"
            @click="saveAddress"
          >
            <Save class="h-4 w-4" />
            {{ addressForm?.hasAddress ? "Update" : "Save" }}
          </button>
        </div>
      </article>

      <article
        v-if="activeSection === 'spouse'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-900">Spouse</h2>
          <div class="relative w-64">
            <Search class="pointer-events-none absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              :value="spouseQ"
              placeholder="Filter rows…"
              class="w-full rounded-md border border-slate-300 bg-white px-8 py-1.5 text-sm"
              @input="(e) => debouncedSpouseSearch((e.target as HTMLInputElement).value)"
              @keyup.enter="flushSpouseSearch"
            />
            <button
              v-if="spouseQ"
              type="button"
              class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
              @click="clearSpouseSearch"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
        </header>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <th class="px-3 py-2">#</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_name')">Spouse Name</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_tax_no')">Spouse Tax No</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_ic_no')">Spouse IC No</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_marriage_date')">Marriage Date</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_divorce_date')">Divorce Date</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_death_date')">Death Date</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleSpouseSort('spo_spouse_seq')">Sequence</th>
                <th class="px-3 py-2">Action</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="spouseLoading">
                <td colspan="9" class="px-3 py-4 text-center text-sm text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="!spouseRows.length">
                <td colspan="9" class="px-3 py-4 text-center text-sm text-slate-500">No spouse records.</td>
              </tr>
              <tr
                v-for="r in spouseRows"
                v-else
                :key="r.spoSpouseSeq"
                class="border-b border-slate-100"
                :class="selectedSpouseSeq === r.spoSpouseSeq ? 'bg-amber-50' : ''"
              >
                <td class="px-3 py-2 text-slate-500">{{ r.index }}</td>
                <td class="px-3 py-2">{{ r.spoName ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoTaxNo ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoIcNo ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoMarriageDate ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoDivorceDate ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoDeathDate ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.spoSpouseSeq }}</td>
                <td class="px-3 py-2">
                  <button
                    type="button"
                    class="rounded border border-slate-300 px-2 py-0.5 text-xs hover:bg-slate-50"
                    @click="loadSpouseChildren(r.spoSpouseSeq)"
                  >
                    View Children
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between text-xs text-slate-500">
          <span>{{ spouseTotal }} record{{ spouseTotal === 1 ? "" : "s" }}</span>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-50"
              :disabled="spousePage <= 1"
              @click="spousePage = Math.max(1, spousePage - 1)"
            >
              Prev
            </button>
            <span>Page {{ spousePage }} / {{ spouseTotalPages }}</span>
            <button
              type="button"
              class="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-50"
              :disabled="spousePage >= spouseTotalPages"
              @click="spousePage = Math.min(spouseTotalPages, spousePage + 1)"
            >
              Next
            </button>
          </div>
        </div>

        <section v-if="selectedSpouseSeq" class="space-y-2 rounded-md border border-slate-200 bg-slate-50 p-3">
          <header class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800">Children of spouse #{{ selectedSpouseSeq }}</h3>
            <button
              type="button"
              class="rounded border border-slate-300 bg-white px-2 py-0.5 text-xs hover:bg-slate-100"
              @click="clearSpouseSelection"
            >
              Clear
            </button>
          </header>
          <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
              <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                  <th class="px-3 py-2">#</th>
                  <th class="px-3 py-2">Name</th>
                  <th class="px-3 py-2">IC</th>
                  <th class="px-3 py-2">D.O.B</th>
                  <th class="px-3 py-2">Relationship</th>
                  <th class="px-3 py-2">Age</th>
                  <th class="px-3 py-2">Study Level</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="spouseChildrenLoading">
                  <td colspan="7" class="px-3 py-3 text-center text-sm text-slate-500">Loading…</td>
                </tr>
                <tr v-else-if="!spouseChildrenRows.length">
                  <td colspan="7" class="px-3 py-3 text-center text-sm text-slate-500">No children for this spouse.</td>
                </tr>
                <tr v-for="r in spouseChildrenRows" v-else :key="r.stcChildSeq" class="border-b border-slate-100">
                  <td class="px-3 py-2 text-slate-500">{{ r.index }}</td>
                  <td class="px-3 py-2">{{ r.stcName ?? "—" }}</td>
                  <td class="px-3 py-2">{{ r.stcIcRefNo ?? "—" }}</td>
                  <td class="px-3 py-2">{{ r.stcBod ?? "—" }}</td>
                  <td class="px-3 py-2">{{ r.stcRelation ?? "—" }}</td>
                  <td class="px-3 py-2">{{ r.age ?? "—" }}</td>
                  <td class="px-3 py-2">{{ r.stcLevelStudy ?? "—" }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </article>

      <article
        v-if="activeSection === 'children'"
        class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3"
      >
        <header class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-base font-semibold text-slate-900">Children</h2>
          <div class="relative w-64">
            <Search class="pointer-events-none absolute left-2 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input
              type="search"
              :value="childrenQ"
              placeholder="Filter rows…"
              class="w-full rounded-md border border-slate-300 bg-white px-8 py-1.5 text-sm"
              @input="(e) => debouncedChildrenSearch((e.target as HTMLInputElement).value)"
              @keyup.enter="flushChildrenSearch"
            />
            <button
              v-if="childrenQ"
              type="button"
              class="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
              @click="clearChildrenSearch"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </div>
        </header>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <th class="px-3 py-2">#</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_name')">Children Name</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_ic_ref_no')">IC Number</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_bod')">D.O.B</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_relation')">Relationship</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('age')">Age</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_level_study')">Study Level</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_disability_status')">Disability</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_pcb_status')">Taxable</th>
                <th class="cursor-pointer px-3 py-2" @click="toggleChildrenSort('stc_death_date')">Date of Death</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="childrenLoading">
                <td colspan="10" class="px-3 py-4 text-center text-sm text-slate-500">Loading…</td>
              </tr>
              <tr v-else-if="!childrenRows.length">
                <td colspan="10" class="px-3 py-4 text-center text-sm text-slate-500">No children records.</td>
              </tr>
              <tr v-for="r in childrenRows" v-else :key="r.stcChildSeq" class="border-b border-slate-100">
                <td class="px-3 py-2 text-slate-500">{{ r.index }}</td>
                <td class="px-3 py-2">{{ r.stcName ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcIcRefNo ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcBod ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcRelation ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.age ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcLevelStudy ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcDisabilityStatus ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcPcbStatus ?? "—" }}</td>
                <td class="px-3 py-2">{{ r.stcDeathDate ?? "—" }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex items-center justify-between text-xs text-slate-500">
          <span>{{ childrenTotal }} record{{ childrenTotal === 1 ? "" : "s" }}</span>
          <div class="flex items-center gap-1">
            <button
              type="button"
              class="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-50"
              :disabled="childrenPage <= 1"
              @click="childrenPage = Math.max(1, childrenPage - 1)"
            >
              Prev
            </button>
            <span>Page {{ childrenPage }} / {{ childrenTotalPages }}</span>
            <button
              type="button"
              class="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-50"
              :disabled="childrenPage >= childrenTotalPages"
              @click="childrenPage = Math.min(childrenTotalPages, childrenPage + 1)"
            >
              Next
            </button>
          </div>
        </div>
      </article>
    </div>

    <Teleport to="body">
      <div
        v-if="maritalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4"
        role="dialog"
        aria-modal="true"
      >
        <div class="w-full max-w-md rounded-xl bg-white p-5 shadow-xl">
          <h2 class="text-base font-semibold text-slate-900">Update Marital Status</h2>
          <p class="mt-1 text-sm text-slate-500">
            Select your current marital status. The change is logged immediately to your HR record.
          </p>

          <div class="mt-4 space-y-3">
            <div>
              <label class="block text-xs font-medium text-slate-600">Marital Status</label>
              <select
                v-model="maritalValue"
                class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
              >
                <option value="">— Select —</option>
                <option v-for="o in options.maritalStatus" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
              <input v-model="maritalClarified" type="checkbox" class="h-4 w-4 rounded border-slate-300" />
              I confirm the selection above is correct.
            </label>
          </div>

          <div class="mt-5 flex justify-end gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
              :disabled="maritalSaving"
              @click="maritalOpen = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-50"
              :disabled="!maritalValue || !maritalClarified || maritalSaving"
              @click="saveMarital"
            >
              <Save class="h-4 w-4" />
              Save
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
