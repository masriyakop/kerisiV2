<script setup lang="ts">
/**
 * Project Monitoring / Updated Balance (MENUID 2065).
 *
 * Mirrors the legacy form (FLC_TRIGGER_PAGE PAGEID 1707 onload BL
 * `SNA_JS_UPDATEDBALANCE_PM` + server BL `SNA_API_UPDATEDBALANCE_PM`):
 *
 *   - Project ID — single autosuggest dropdown.
 *     On select, populates the Information + Cash Balance cards with
 *     the joined autosuggest payload (capital_project ⨝ fund_type ⨝
 *     costcentre ⨝ activity_type ⨝ structure_budget ⨝
 *     organization_unit ⨝ budget).
 *
 *   - Information card (read-only):
 *       Fund            — `_fund`            (`fty_fund_type - fty_fund_desc`)
 *       Activity        — `_aktiviti`        (`lat_activity_code - at_activity_description_bm`)
 *       PTJ             — `_ptj`             (`oun_code - oun_desc`)
 *       Cost Centre     — `_costcenter`      (`ccr_costcentre - ccr_costcentre_desc`)
 *       SO Code         — `_kodSo`           (`cp.so_code`)
 *       Project Desc    — `_desc`            (`cp.cpa_project_desc`)
 *
 *   - Cash Balance card:
 *       Balance Amount       (read-only `_balAmt` = `cpa_ytd_balance_amt`)
 *       Budget ID            (read-only `_budgetID` = `lbc_budget_code`)
 *       Budget Available     (read-only `_budgetAmt` = `bdg_balance_amt`)
 *       Sequence Start Budget(read-only `_seqStrbudget` = `sb.sbg_budget_id`)
 *       Sequence Budget      (read-only `_seqbudget` = `bdg.bdg_budget_id`)
 *       Current Balance Cash (EDITABLE → posted as `bal.currBalCashBal`,
 *                             saved into both `cpa_ytd_balance_amt` and
 *                             `bdg_topup_amt`)
 *       Current Budget       (EDITABLE → posted for legacy fidelity but
 *                             never persisted by the legacy BL)
 *
 *   - Save button (right-aligned inside the Cash Balance card).
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import { Info, Loader2, Save, Search, Wallet, X } from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  getProjectMonitoringBalance,
  saveProjectMonitoringBalance,
  searchProjectMonitoringProjects,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import type { ProjectMonitoringBalance } from "@/types";

const toast = useToast();

const myr = new Intl.NumberFormat("en-MY", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

const search = ref("");
const suggestOpen = ref(false);
const suggestLoading = ref(false);
const suggestions = ref<ProjectMonitoringBalance[]>([]);
const selected = ref<ProjectMonitoringBalance | null>(null);
const loadingDetail = ref(false);
const saving = ref(false);

const editForm = ref({
  currBalCashBal: "" as string,
  currBudgetBal: "" as string,
});

const projectLabel = computed(() => {
  const p = selected.value;
  if (!p) return "";
  const no = p.cpaProjectNo ?? "";
  const desc = p.cpaProjectDesc ?? "";
  return desc ? `${no} - ${desc}` : no;
});

let timer: ReturnType<typeof setTimeout> | null = null;

watch(search, (val) => {
  if (timer) clearTimeout(timer);
  if (selected.value && val !== projectLabel.value) {
    selected.value = null;
    resetEditForm();
  }
  if (!val.trim()) {
    suggestions.value = [];
    suggestOpen.value = false;
    return;
  }
  timer = setTimeout(() => {
    timer = null;
    void runSearch(val);
  }, 350);
});

async function runSearch(needle: string) {
  suggestLoading.value = true;
  suggestOpen.value = true;
  try {
    const params = new URLSearchParams({ q: needle.trim(), limit: "20" });
    const res = await searchProjectMonitoringProjects(`?${params.toString()}`);
    suggestions.value = res.data ?? [];
  } catch (e) {
    suggestions.value = [];
    toast.error(
      "Search failed",
      e instanceof Error ? e.message : "Unable to load projects.",
    );
  } finally {
    suggestLoading.value = false;
  }
}

async function selectProject(p: ProjectMonitoringBalance) {
  selected.value = p;
  search.value = p.cpaProjectDesc
    ? `${p.cpaProjectNo ?? ""} - ${p.cpaProjectDesc}`
    : (p.cpaProjectNo ?? "");
  suggestOpen.value = false;
  suggestions.value = [];
  resetEditForm();
  primeEditForm(p);

  if (!p.cpaProjectNo) return;
  loadingDetail.value = true;
  try {
    const res = await getProjectMonitoringBalance(p.cpaProjectNo);
    selected.value = res.data;
    primeEditForm(res.data);
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load project balance.",
    );
  } finally {
    loadingDetail.value = false;
  }
}

function primeEditForm(p: ProjectMonitoringBalance) {
  editForm.value.currBalCashBal =
    p.balAmt != null ? myr.format(p.balAmt) : "";
  editForm.value.currBudgetBal =
    p.budgetAmt != null ? myr.format(p.budgetAmt) : "";
}

function resetEditForm() {
  editForm.value = { currBalCashBal: "", currBudgetBal: "" };
}

function clearSelection() {
  search.value = "";
  selected.value = null;
  suggestions.value = [];
  suggestOpen.value = false;
  resetEditForm();
}

function parseAmount(v: string): number | null {
  const t = v.trim();
  if (t === "") return null;
  const n = Number(t.replace(/,/g, ""));
  return Number.isFinite(n) ? n : null;
}

const canSave = computed(() => {
  if (!selected.value || saving.value) return false;
  if (!selected.value.seqBudget) return false;
  return parseAmount(editForm.value.currBalCashBal) != null;
});

async function save() {
  if (!selected.value?.cpaProjectNo) {
    toast.error("Select a project", "Pick a Project ID before saving.");
    return;
  }
  if (!selected.value.seqBudget) {
    toast.error(
      "Missing budget row",
      "This project has no matching budget sequence; nothing to update.",
    );
    return;
  }
  const newBal = parseAmount(editForm.value.currBalCashBal);
  if (newBal == null) {
    toast.error("Invalid amount", "Enter a numeric Current Balance Cash.");
    return;
  }
  saving.value = true;
  try {
    await saveProjectMonitoringBalance({
      info: { cpaProjectNo: selected.value.cpaProjectNo },
      bal: {
        currBalCashBal: String(newBal),
        currBudgetBal: editForm.value.currBudgetBal,
        seqBudgetBal: selected.value.seqBudget,
      },
    });
    toast.success("Saved", "Updated balance saved.");
    if (selected.value.cpaProjectNo) {
      const res = await getProjectMonitoringBalance(selected.value.cpaProjectNo);
      selected.value = res.data;
      primeEditForm(res.data);
    }
  } catch (e) {
    const msg = e instanceof Error ? e.message : "Unable to save balance.";
    toast.error("Save failed", msg);
  } finally {
    saving.value = false;
  }
}

function fmtAmount(v: number | null | undefined): string {
  return v == null ? "" : myr.format(v);
}

onMounted(() => {});

onUnmounted(() => {
  if (timer) clearTimeout(timer);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">Project Monitoring / Updated Balance</h1>

      <!-- Project ID picker -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <h2 class="text-base font-semibold text-slate-900">Project</h2>
        </div>
        <div class="p-4">
          <label class="mb-1.5 block text-sm font-medium text-slate-700">
            Project ID <span class="text-red-500">*</span>
          </label>
          <div class="relative">
            <Search
              class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
            />
            <input
              v-model="search"
              type="search"
              autocomplete="off"
              placeholder="Type to search project number or description…"
              class="w-full rounded-lg border border-slate-300 bg-white py-2 pl-10 pr-9 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              @focus="suggestOpen = suggestions.length > 0"
            />
            <button
              v-if="search"
              type="button"
              aria-label="Clear"
              class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:bg-slate-100"
              @click="clearSelection"
            >
              <X class="h-4 w-4" />
            </button>

            <div
              v-if="suggestOpen"
              class="absolute z-30 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg"
            >
              <div
                v-if="suggestLoading"
                class="flex items-center gap-2 p-3 text-sm text-slate-500"
              >
                <Loader2 class="h-4 w-4 animate-spin" />
                Loading…
              </div>
              <div
                v-else-if="!suggestions.length"
                class="p-3 text-sm text-slate-500"
              >
                No matching projects.
              </div>
              <ul v-else class="max-h-72 overflow-y-auto py-1">
                <li
                  v-for="row in suggestions"
                  :key="row.cpaProjectId"
                  class="cursor-pointer px-3 py-2 text-sm hover:bg-slate-50"
                  @mousedown.prevent="selectProject(row)"
                >
                  <p class="font-medium text-slate-900">
                    {{ row.cpaProjectNo ?? "—" }}
                  </p>
                  <p class="truncate text-xs text-slate-500">
                    {{ row.cpaProjectDesc ?? "" }}
                  </p>
                </li>
              </ul>
            </div>
          </div>
          <p class="mt-1.5 text-xs text-slate-500">
            Pick the project whose cash balance you want to update.
          </p>
        </div>
      </article>

      <!-- Information (read-only) -->
      <article id="balanceInfo" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
          <Info class="h-4 w-4 text-cyan-600" />
          <h2 class="text-base font-semibold text-slate-900">Information</h2>
        </div>
        <div class="p-4">
          <div v-if="loadingDetail" class="flex items-center gap-2 text-sm text-slate-500">
            <Loader2 class="h-4 w-4 animate-spin" />
            Loading project info…
          </div>
          <div v-else-if="!selected" class="text-sm text-slate-500">
            Select a project above to view its details.
          </div>
          <div v-else class="grid gap-4 md:grid-cols-2">
            <div class="space-y-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Fund
              </label>
              <input
                :value="selected.ftyFundLabel ?? selected.ftyFundType ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Activity
              </label>
              <input
                :value="selected.latActivityLabel ?? selected.latActivityCode ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                PTJ
              </label>
              <input
                :value="selected.ounLabel ?? selected.ounCode ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Cost Centre
              </label>
              <input
                :value="selected.ccrCostcentreLabel ?? selected.ccrCostcentre ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
            <div class="space-y-1.5">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                SO Code
              </label>
              <input
                :value="selected.soCode ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
            <div class="space-y-1.5 md:col-span-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Project Description
              </label>
              <input
                :value="selected.cpaProjectDesc ?? ''"
                readonly
                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
              />
            </div>
          </div>
        </div>
      </article>

      <!-- Cash Balance -->
      <article id="balanceCash" class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
          <Wallet class="h-4 w-4 text-amber-600" />
          <h2 class="text-base font-semibold text-slate-900">Cash Balance</h2>
        </div>
        <div class="p-4">
          <div v-if="!selected" class="text-sm text-slate-500">
            Select a project to view balance details.
          </div>
          <template v-else>
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
              Current values
            </p>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Balance Amount (RM)
                </label>
                <input
                  :value="fmtAmount(selected.balAmt)"
                  readonly
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm tabular-nums text-slate-700"
                />
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Budget ID
                </label>
                <input
                  :value="selected.budgetId ?? ''"
                  readonly
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                />
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Budget Available (RM)
                </label>
                <input
                  :value="fmtAmount(selected.budgetAmt)"
                  readonly
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm tabular-nums text-slate-700"
                />
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Sequence Start Budget
                </label>
                <input
                  :value="selected.seqStrtBudget ?? ''"
                  readonly
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                />
              </div>
              <div class="space-y-1.5 md:col-span-2">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Sequence Budget
                </label>
                <input
                  :value="selected.seqBudget ?? ''"
                  readonly
                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700"
                />
              </div>
            </div>

            <hr class="my-5 border-slate-200" />

            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
              New values to save
            </p>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Current Balance Cash (RM) <span class="text-red-500">*</span>
                </label>
                <input
                  v-model="editForm.currBalCashBal"
                  type="text"
                  inputmode="decimal"
                  placeholder="0.00"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <p class="text-xs text-slate-400">
                  Saved into <code>capital_project.cpa_ytd_balance_amt</code> and
                  <code>budget.bdg_topup_amt</code>.
                </p>
              </div>
              <div class="space-y-1.5">
                <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                  Current Budget (RM)
                </label>
                <input
                  v-model="editForm.currBudgetBal"
                  type="text"
                  inputmode="decimal"
                  placeholder="0.00"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm tabular-nums shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                />
                <p class="text-xs text-slate-400">
                  Accepted for legacy fidelity; the legacy BL does not persist
                  this value.
                </p>
              </div>
            </div>
          </template>

          <div class="mt-5 flex justify-end">
            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="!canSave"
              @click="save"
            >
              <Loader2 v-if="saving" class="h-4 w-4 animate-spin" />
              <Save v-else class="h-4 w-4" />
              Save
            </button>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
