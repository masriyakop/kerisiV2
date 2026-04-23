<script setup lang="ts">
/**
 * Petty Cash / Petty Cash Recoup Form (PAGEID 1256 / MENUID 1534).
 *
 * Source: FIMS BL `API_PETTYCASH_PETTYCASHRECOUPFORM` — `PettyCashBatchMaster` +
 * `PettyCashRecoupDetailSelected_dt` branches. Mirrors the legacy read-only
 * screen: header Information card ({ Batch No, Total Detail, Status }) plus a
 * "Petty Cash Recoup Detail Selected" table with per-line data.
 *
 * Route entry:
 *   /admin/kerisi/m/1534?pcb_id=X&mode=view
 *   /admin/kerisi/m/1534?pcb_id=X&mode=edit    (treated as view for now)
 *
 * Workflow caveat: submit/endorse/approve/reject + add-more-lines require the
 * FIMS workflow engine which is not ported yet. This page is read-only.
 */
import { computed, onMounted, ref, watch } from "vue";
import { ChevronLeft, Loader2 } from "lucide-vue-next";
import { useRoute, useRouter } from "vue-router";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";
import { getPettyCashRecoup } from "@/api/cms";
import type { PettyCashRecoupDetail, PettyCashRecoupDetailLine } from "@/types";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const detail = ref<PettyCashRecoupDetail | null>(null);
const loading = ref(false);

const pcbId = computed(() => {
  const raw = route.query.pcb_id;
  const n = Array.isArray(raw) ? Number(raw[0]) : Number(raw);
  return Number.isFinite(n) && n > 0 ? n : null;
});

async function loadDetail() {
  if (pcbId.value === null) {
    toast.error("Missing parameter", "pcb_id is required to open this page.");
    return;
  }
  loading.value = true;
  try {
    const res = await getPettyCashRecoup(pcbId.value);
    detail.value = res.data;
  } catch (e) {
    toast.error("Load failed", e instanceof Error ? e.message : "Unable to load recoup batch.");
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push("/admin/kerisi/m/1532");
}

function formatMoney(v: number | null | undefined): string {
  if (v == null || Number.isNaN(Number(v))) return "-";
  return new Intl.NumberFormat("en-MY", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Number(v));
}

function formatDate(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  const dd = String(d.getDate()).padStart(2, "0");
  const mm = String(d.getMonth() + 1).padStart(2, "0");
  const yyyy = d.getFullYear();
  return `${dd}/${mm}/${yyyy}`;
}

const lines = computed<PettyCashRecoupDetailLine[]>(() => detail.value?.lines ?? []);

onMounted(loadDetail);
watch(pcbId, () => {
  void loadDetail();
});
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
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
        <h1 class="text-base font-semibold text-slate-800">
          Petty Cash (PTJ) / Petty Cash Recoup
        </h1>
      </div>

      <div v-if="loading" class="flex items-center gap-2 text-sm text-slate-500">
        <Loader2 class="h-4 w-4 animate-spin" /> Loading recoup batch…
      </div>

      <div
        v-else-if="!detail"
        class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
      >
        No recoup batch found for pcb_id {{ pcbId ?? "(missing)" }}.
      </div>

      <template v-else>
        <!-- Information card -->
        <section class="rounded-md border border-slate-200 bg-white">
          <div class="border-b border-slate-200 bg-slate-50 px-4 py-2">
            <h2 class="text-sm font-semibold text-slate-800">Petty Cash Recoup</h2>
          </div>
          <div class="grid grid-cols-1 gap-x-6 gap-y-3 px-4 py-3">
            <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
              <span class="text-sm text-slate-600">Batch No</span>
              <span class="text-sm text-slate-500">:</span>
              <span class="text-sm font-medium text-slate-800">{{ detail.pcbBatchId || "-" }}</span>
            </div>
            <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
              <span class="text-sm text-slate-600">Total Detail</span>
              <span class="text-sm text-slate-500">:</span>
              <span class="text-sm text-slate-800">{{ detail.pcbTransNo ?? lines.length }}</span>
            </div>
            <div class="grid grid-cols-[140px_10px_1fr] items-center gap-x-2">
              <span class="text-sm text-slate-600">Status</span>
              <span class="text-sm text-slate-500">:</span>
              <span class="text-sm text-slate-800">{{ detail.pcbStatus || "-" }}</span>
            </div>
          </div>
        </section>

        <!-- Petty Cash Recoup Detail Selected -->
        <section class="rounded-md border border-slate-200 bg-white">
          <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-2">
            <h2 class="text-sm font-semibold text-slate-800">Petty Cash Recoup Detail Selected</h2>
            <span class="text-xs text-slate-500">{{ lines.length }} records</span>
          </div>
          <div class="overflow-x-auto">
            <div :class="lines.length > 10 ? 'max-h-[480px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1280px] text-sm">
                <thead class="sticky top-0 bg-indigo-100 text-slate-700">
                  <tr class="text-left">
                    <th class="px-3 py-2 text-xs font-semibold">No</th>
                    <th class="px-3 py-2 text-xs font-semibold">Application No</th>
                    <th class="px-3 py-2 text-xs font-semibold">Application Date</th>
                    <th class="px-3 py-2 text-xs font-semibold">Receipt No</th>
                    <th class="px-3 py-2 text-xs font-semibold">Fund Type</th>
                    <th class="px-3 py-2 text-xs font-semibold">Activity Code</th>
                    <th class="px-3 py-2 text-xs font-semibold">OU</th>
                    <th class="px-3 py-2 text-xs font-semibold">Cost Centre</th>
                    <th class="px-3 py-2 text-xs font-semibold">Account Code</th>
                    <th class="px-3 py-2 text-xs font-semibold">SO Code</th>
                    <th class="px-3 py-2 text-right text-xs font-semibold">Amount (RM)</th>
                    <th class="px-3 py-2 text-xs font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="line in lines"
                    :key="line.pcdId"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2 text-slate-600">{{ line.index }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.pmsApplicationNo || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ formatDate(line.pmsRequestDate) }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.pcdReceiptNo || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.ftyFundType || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.atActivityCode || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.ounCode || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.ccrCostcentre || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.acmAcctCode || "-" }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.soCode || "-" }}</td>
                    <td class="px-3 py-2 text-right tabular-nums text-slate-800">{{ formatMoney(line.pcdTransAmt) }}</td>
                    <td class="px-3 py-2 text-slate-800">{{ line.pcdBatchStatus || "-" }}</td>
                  </tr>
                  <tr v-if="lines.length === 0">
                    <td colspan="12" class="px-3 py-8 text-center text-sm text-slate-500">
                      No detail lines attached to this batch.
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </template>
    </div>
  </AdminLayout>
</template>
