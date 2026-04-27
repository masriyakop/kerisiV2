<script setup lang="ts">
import { onMounted, ref } from "vue";
import AdminLayout from "@/layouts/AdminLayout.vue";
import CheckErrorSection, { type CheckErrorColumn } from "@/components/fims/CheckErrorSection.vue";
import {
  getBillsSetup,
  getBudgetStructureSearchForms,
  getBudgetStructureSearchOptions,
  getJenisCarian,
  listBillsSetup,
  listJenisCarian,
  saveBillsCustomWf,
  saveSemiStrict,
  updateBillsSetup,
  updateJenisCarian,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  BillsSetupDetail,
  BillsSetupInput,
  BudgetStructureSearchOptions,
  JenisCarianDetail,
  JenisCarianInput,
  SemiStrictInput,
  BillsCustomWfInput,
} from "@/types";

// Breadcrumb/title mirror legacy PAGEBREADCRUMBS / PAGETITLE for PAGEID 2664.
const PAGE_BREADCRUMB = "Setup and Maintenance / Setup Carian Structure Budget";
const PAGE_TITLE = "Setup Carian Structure Budget";

const toast = useToast();

type SectionRef = { reload: () => Promise<void> | void } | null;

const jenisCarianSection = ref<SectionRef>(null);
const billsSetupSection = ref<SectionRef>(null);

const options = ref<BudgetStructureSearchOptions | null>(null);
const optionsLoaded = ref(false);

// Jenis Carian modal
const showJenisModal = ref(false);
const jenisSaving = ref(false);
const jenisForm = ref<JenisCarianDetail>({ sbssId: 0, sbssType: "", sbssStatus: "ACTIVE" });

// Bill Setup modal
const showBillModal = ref(false);
const billSaving = ref(false);
const billForm = ref<BillsSetupDetail>({ bisId: 0, bisType: "", bisStatus: "ACTIVE" });

// Semi-Strict form (inline)
const semiForm = ref<SemiStrictInput>({ sbssColumnSelection: "ACCOUNT", sbssLevelSelection: "1" });
const semiSaving = ref(false);

// Custom WF form (inline)
const customWfForm = ref<BillsCustomWfInput>({ bisSequenceLevel: "" });
const customWfSaving = ref(false);

const jenisCarianColumns: CheckErrorColumn[] = [
  { key: "sbssType", label: "Jenis Carian", sortable: true, hideable: true },
  { key: "sbssStatus", label: "Status", sortable: true, hideable: true },
];

const billsSetupColumns: CheckErrorColumn[] = [
  { key: "bisType", label: "Type", sortable: true, hideable: true },
  { key: "bisStatus", label: "Status", sortable: true, hideable: true },
];

async function loadOptionsAndForms() {
  try {
    const [opts, forms] = await Promise.all([getBudgetStructureSearchOptions(), getBudgetStructureSearchForms()]);
    options.value = opts.data;
    optionsLoaded.value = true;

    if (forms.data.semiStrict.sbssColumnSelection) {
      const val = forms.data.semiStrict.sbssColumnSelection;
      if (val === "ACCOUNT" || val === "ACTIVITY") semiForm.value.sbssColumnSelection = val;
    }
    if (forms.data.semiStrict.sbssLevelSelection != null) {
      semiForm.value.sbssLevelSelection = String(forms.data.semiStrict.sbssLevelSelection);
    }
    if (forms.data.billsCustomWf.bisSequenceLevel != null) {
      customWfForm.value.bisSequenceLevel = String(forms.data.billsCustomWf.bisSequenceLevel);
    }
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load configuration.");
  }
}

async function openJenisEdit(id: string | number) {
  try {
    const res = await getJenisCarian(Number(id));
    jenisForm.value = { ...res.data };
    if (!jenisForm.value.sbssStatus) jenisForm.value.sbssStatus = "ACTIVE";
    showJenisModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to fetch Jenis Carian.");
  }
}

async function saveJenisCarian() {
  if (!jenisForm.value.sbssId) return;
  jenisSaving.value = true;
  try {
    const status = jenisForm.value.sbssStatus === "INACTIVE" ? "INACTIVE" : "ACTIVE";
    await updateJenisCarian(jenisForm.value.sbssId, { sbssStatus: status } as JenisCarianInput);
    toast.success("Update successful");
    showJenisModal.value = false;
    await jenisCarianSection.value?.reload();
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    jenisSaving.value = false;
  }
}

async function openBillEdit(id: string | number) {
  try {
    const res = await getBillsSetup(Number(id));
    billForm.value = { ...res.data };
    if (!billForm.value.bisStatus) billForm.value.bisStatus = "ACTIVE";
    showBillModal.value = true;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to fetch Bill Setup.");
  }
}

async function saveBillSetup() {
  if (!billForm.value.bisId) return;
  billSaving.value = true;
  try {
    const status = billForm.value.bisStatus === "ACTIVE" ? "ACTIVE" : "INACTIVE";
    await updateBillsSetup(billForm.value.bisId, { bisStatus: status } as BillsSetupInput);
    toast.success("Update successful");
    showBillModal.value = false;
    await billsSetupSection.value?.reload();
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    billSaving.value = false;
  }
}

async function submitSemiStrict() {
  if (!semiForm.value.sbssColumnSelection || !semiForm.value.sbssLevelSelection) {
    toast.error("Validation failed", "Select column and level.");
    return;
  }
  semiSaving.value = true;
  try {
    await saveSemiStrict({ ...semiForm.value });
    toast.success("Update successful");
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    semiSaving.value = false;
  }
}

async function submitCustomWf() {
  if (!customWfForm.value.bisSequenceLevel) {
    toast.error("Validation failed", "Select a sequence.");
    return;
  }
  customWfSaving.value = true;
  try {
    await saveBillsCustomWf({ ...customWfForm.value });
    toast.success("Update successful");
  } catch (e) {
    toast.error("Process error", e instanceof Error ? e.message : "Unable to save.");
  } finally {
    customWfSaving.value = false;
  }
}

onMounted(() => {
  void loadOptionsAndForms();
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">{{ PAGE_BREADCRUMB }}</h1>
      <h1 class="sr-only">{{ PAGE_TITLE }}</h1>

      <!-- Component 8376: Jenis Carian datatable -->
      <CheckErrorSection
        ref="jenisCarianSection"
        title="List Jenis Carian Structure Budget"
        export-name="Jenis Carian"
        :columns="jenisCarianColumns"
        :fetcher="listJenisCarian"
        default-sort-by="sbss_type"
        edit-key="sbssId"
        @edit="openJenisEdit"
      />

      <!-- Component 8412: Semi Strict Setup inline form -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-sm font-semibold text-slate-900">Semi Strict Setup</h2>
        </header>
        <div class="p-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Column Selected *</label>
              <select
                v-model="semiForm.sbssColumnSelection"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"
                :disabled="semiSaving || !optionsLoaded"
              >
                <option v-for="opt in options?.semiStrict.column ?? []" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Level Selected *</label>
              <select
                v-model="semiForm.sbssLevelSelection"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"
                :disabled="semiSaving || !optionsLoaded"
              >
                <option v-for="opt in options?.semiStrict.level ?? []" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 disabled:opacity-50"
              :disabled="semiSaving || !optionsLoaded"
              @click="submitSemiStrict"
            >
              Save
            </button>
          </div>
        </div>
      </article>

      <!-- Component 8468: Bill Setup datatable -->
      <CheckErrorSection
        ref="billsSetupSection"
        title="Bill Setup"
        export-name="Bill Setup"
        :columns="billsSetupColumns"
        :fetcher="listBillsSetup"
        default-sort-by="bis_type"
        edit-key="bisId"
        @edit="openBillEdit"
      />

      <!-- Component 8492: Bill Setup Custom WF Posting inline form -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <header class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-sm font-semibold text-slate-900">Bill Setup Custom WF Posting</h2>
        </header>
        <div class="p-4">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Sequence Selected *</label>
              <select
                v-model="customWfForm.bisSequenceLevel"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"
                :disabled="customWfSaving || !optionsLoaded"
              >
                <option value="">-- Select --</option>
                <option v-for="opt in options?.billsCustomWf.sequence ?? []" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
            <button
              type="button"
              class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-slate-800 disabled:opacity-50"
              :disabled="customWfSaving || !optionsLoaded"
              @click="submitCustomWf"
            >
              Save
            </button>
          </div>
        </div>
      </article>
    </div>

    <!-- Component 8377: Jenis Carian popup modal -->
    <Teleport to="body">
      <div v-if="showJenisModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showJenisModal = false">
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Jenis Carian</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Jenis Carian</label>
              <input v-model="jenisForm.sbssType" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" disabled />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
              <select v-model="jenisForm.sbssStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="jenisSaving">
                <option v-for="opt in options?.jenisCarianModal.status ?? []" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="jenisSaving" @click="showJenisModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="jenisSaving" @click="saveJenisCarian">Save</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Component 8470: Bill Setup popup modal -->
    <Teleport to="body">
      <div v-if="showBillModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="showBillModal = false">
        <div class="w-full max-w-lg rounded-lg border border-slate-200 bg-white shadow-2xl">
          <div class="border-b border-slate-100 px-4 py-3">
            <h3 class="text-base font-semibold text-slate-900">Bill Setup</h3>
          </div>
          <div class="grid grid-cols-1 gap-4 p-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Type</label>
              <input v-model="billForm.bisType" type="text" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" disabled />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
              <select v-model="billForm.bisStatus" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" :disabled="billSaving">
                <option v-for="opt in options?.billSetupModal.status ?? []" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-slate-100 px-4 py-3">
            <button type="button" class="rounded-lg border border-red-300 px-4 py-2 text-sm text-red-600" :disabled="billSaving" @click="showBillModal = false">Cancel</button>
            <button type="button" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" :disabled="billSaving" @click="saveBillSetup">Save</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>
