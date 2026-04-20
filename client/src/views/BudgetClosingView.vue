<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { AlertTriangle, Loader2, Play, Undo2 } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  budgetClosingProcess,
  budgetClosingReverse,
  getBudgetClosingOptions,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type {
  BudgetClosingOptions,
  BudgetClosingPayload,
} from "@/types";

// Labels mirror legacy PAGEID 1953 (docs/migration/fims-budget/PAGE_1953.json).
const PAGE_NAME = "Budget Closing";
const PAGE_BREADCRUMB = "Budget / Closing";

const toast = useToast();
const options = ref<BudgetClosingOptions>({
  filter: {
    year: [],
    fund: [],
    activityGroup: [],
    activitySubgroup: [],
    activityCode: [],
  },
});
const form = ref<BudgetClosingPayload>({
  closingYear: String(new Date().getFullYear()),
  fundBudgetClosing: "",
  activityGroup: "",
  activitySubgroup: "",
  atActivityCodeTop: "",
});
const submitting = ref<null | "process" | "reverse">(null);
const resultMsg = ref<string | null>(null);
const resultKind = ref<"info" | "error">("info");

async function loadOptions() {
  try {
    const res = await getBudgetClosingOptions();
    options.value = res.data;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Could not load filter options.");
  }
}

// Filter activity subgroup / activity code dropdowns on the selected parent —
// mirrors the legacy autoSuggest chain (subgroup depends on activity_group,
// activity depends on both year and activity_group).
const subgroupsFiltered = computed(() => {
  if (!form.value.activityGroup) return options.value.filter.activitySubgroup;
  return options.value.filter.activitySubgroup.filter(
    (s) => !s.activityGroupCode || s.activityGroupCode === form.value.activityGroup,
  );
});
const activityCodesFiltered = computed(() => {
  return options.value.filter.activityCode.filter((a) => {
    if (form.value.activityGroup && a.activityGroupCode && a.activityGroupCode !== form.value.activityGroup) {
      return false;
    }
    if (form.value.activitySubgroup && a.activitySubgroupCode && a.activitySubgroupCode !== form.value.activitySubgroup) {
      return false;
    }
    return true;
  });
});

function validate(): string | null {
  if (!form.value.closingYear) return "Year is required.";
  if (!form.value.fundBudgetClosing) return "Fund is required.";
  return null;
}

async function onProcess() {
  const err = validate();
  if (err) {
    toast.error("Validation", err);
    return;
  }
  submitting.value = "process";
  resultMsg.value = null;
  try {
    const res = await budgetClosingProcess({ ...form.value });
    resultKind.value = "info";
    resultMsg.value = JSON.stringify(res.data, null, 2);
    toast.success("Process started");
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Process request failed.";
    resultKind.value = "error";
    resultMsg.value = msg;
    toast.info(
      "Process not available",
      "Budget Closing process is stubbed until the legacy BL NAD_API_BUDGET_BUDGETCLOSING is migrated.",
    );
  } finally {
    submitting.value = null;
  }
}

async function onReverse() {
  const err = validate();
  if (err) {
    toast.error("Validation", err);
    return;
  }
  submitting.value = "reverse";
  resultMsg.value = null;
  try {
    const res = await budgetClosingReverse({ ...form.value });
    resultKind.value = "info";
    resultMsg.value = JSON.stringify(res.data, null, 2);
    toast.success("Reverse started");
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Reverse request failed.";
    resultKind.value = "error";
    resultMsg.value = msg;
    toast.info(
      "Reverse not available",
      "Budget Closing reverse is stubbed until the legacy BL NAD_API_BUDGET_BUDGETCLOSING is migrated.",
    );
  } finally {
    submitting.value = null;
  }
}

function reset() {
  form.value = {
    closingYear: String(new Date().getFullYear()),
    fundBudgetClosing: "",
    activityGroup: "",
    activitySubgroup: "",
    atActivityCodeTop: "",
  };
  resultMsg.value = null;
}

onMounted(() => {
  void loadOptions();
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-4xl space-y-4">
      <p class="text-base font-semibold text-slate-500">{{ PAGE_BREADCRUMB }}</p>

      <div class="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
        <div>
          <p class="font-semibold">Process / Reverse endpoints are stubbed</p>
          <p class="mt-1">
            Legacy BL <code>NAD_API_BUDGET_BUDGETCLOSING</code> (process &amp; reverse) was not included in the migration export.
            The buttons currently return a <code>501 NOT_IMPLEMENTED</code> response so the UI stays honest until the server-side
            logic is ported.
          </p>
        </div>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h1 class="text-base font-semibold text-slate-900">{{ PAGE_NAME }}</h1>
        </div>

        <form
          class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2"
          @submit.prevent="onProcess"
        >
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">
              Year <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.closingYear"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              required
            >
              <option value="">Select year</option>
              <option v-for="opt in options.filter.year" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">
              Fund <span class="text-red-500">*</span>
            </label>
            <select
              v-model="form.fundBudgetClosing"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              required
            >
              <option value="">Select fund</option>
              <option v-for="opt in options.filter.fund" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Activity Group</label>
            <select
              v-model="form.activityGroup"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              @change="
                form.activitySubgroup = '';
                form.atActivityCodeTop = '';
              "
            >
              <option value="">Any</option>
              <option v-for="opt in options.filter.activityGroup" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>

          <div>
            <label class="mb-1 block text-xs font-medium text-slate-700">Activity Subgroup</label>
            <select
              v-model="form.activitySubgroup"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              @change="form.atActivityCodeTop = ''"
            >
              <option value="">Any</option>
              <option v-for="opt in subgroupsFiltered" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>

          <div class="md:col-span-2">
            <label class="mb-1 block text-xs font-medium text-slate-700">Activity Code</label>
            <select
              v-model="form.atActivityCodeTop"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
            >
              <option value="">Any</option>
              <option v-for="opt in activityCodesFiltered" :key="opt.id" :value="opt.id">{{ opt.label }}</option>
            </select>
          </div>

          <div class="md:col-span-2 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
              :disabled="submitting !== null"
              @click="reset"
            >
              Reset
            </button>
            <button
              type="button"
              class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40"
              :disabled="submitting !== null"
              @click="onReverse"
            >
              <Loader2 v-if="submitting === 'reverse'" class="h-4 w-4 animate-spin" />
              <Undo2 v-else class="h-4 w-4" />
              Reverse Process
            </button>
            <button
              type="submit"
              class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="submitting !== null"
            >
              <Loader2 v-if="submitting === 'process'" class="h-4 w-4 animate-spin" />
              <Play v-else class="h-4 w-4" />
              Start Process
            </button>
          </div>
        </form>

        <div v-if="resultMsg" class="border-t border-slate-100 px-4 py-3">
          <p
            class="text-xs font-medium uppercase"
            :class="resultKind === 'error' ? 'text-red-600' : 'text-slate-500'"
          >
            Response
          </p>
          <pre
            class="mt-2 max-h-60 overflow-auto rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs"
            :class="resultKind === 'error' ? 'text-red-700' : 'text-slate-700'"
          >{{ resultMsg }}</pre>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
