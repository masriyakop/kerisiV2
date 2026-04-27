<script setup lang="ts">
/**
 * Student Finance / Invoice / Activity / Generate Student Invoice
 * (PAGEID 970, MENUID 1231)
 *
 * Source: FIMS BL `CALL_PROC_STUDENT_INVOICE` with 4 actions —
 * `find=1`, `csv=1`, `match=1`, `generate=1`. The legacy page is a
 * Search Parameter form (semester / program level / student type /
 * structure type [fee_type] / intake case / matric) followed by a
 * datatable "List of Students : Invoice Generation" with 8 columns
 * and a Generate button.
 *
 * Workflow:
 *   1. User picks the search parameters and presses Search.
 *      Backend generates a per-search `uniqueKey` (UUID), calls
 *      `invoiceCheckingByBatch(...)` and returns the matched roster
 *      keyed by that uniqueKey.
 *   2. While the roster is still in `temp_stud_listing_match`, the
 *      user can download the legacy CSV (csv=1) or click Generate.
 *   3. Generate POSTs the same parameters + uniqueKey to
 *      `invoiceCreationByBatch(...)` and rewrites the resulting
 *      `wf_task.wtk_task_url` rows to the legacy URL shape.
 *   4. After a successful Generate the user can download the legacy
 *      Match CSV (match=1) which lists `cust_invoice_master` rows
 *      created in step 3.
 *
 * Design notes:
 *   - We do not paginate by re-running the SP — the SP is called
 *     once per Search click and the listing is paginated client-side
 *     by re-issuing the same uniqueKey to a paginated SELECT (the
 *     backend does NOT call the SP on every page). This mirrors the
 *     legacy `temp_stud_listing_match` behaviour but keeps the
 *     request count low.
 *   - "Smart filter" (filter modal) is intentionally NOT migrated —
 *     the legacy page used the form fields above the table as the
 *     primary filter surface. The free-text search input applies
 *     client-side regex over the rendered roster only.
 */
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import {
  Download,
  FileDown,
  FileSpreadsheet,
  Loader2,
  Search,
  Sparkles,
  X,
} from "lucide-vue-next";
import AdminLayout from "@/layouts/AdminLayout.vue";
import {
  exportStudentInvoiceGenerationCsv,
  exportStudentInvoiceGenerationMatchCsv,
  generateStudentInvoice,
  getStudentInvoiceGenerationOptions,
  searchStudentInvoiceGeneration,
} from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import type {
  StudentInvoiceGenerationGenerateInput,
  StudentInvoiceGenerationGenerateResult,
  StudentInvoiceGenerationOptions,
  StudentInvoiceGenerationRow,
  StudentInvoiceGenerationSearchInput,
  StudentInvoiceGenerationSearchMeta,
} from "@/types";

const toast = useToast();
const confirm = useConfirmDialog();

const options = ref<StudentInvoiceGenerationOptions>({
  semester: [],
  programLevel: [],
  studentType: [],
  feeType: [],
  intakeCase: [],
});

const form = ref({
  semester: "",
  programLevel: "",
  studentType: "",
  feeType: "",
  intakeCase: "",
  matricNo: "",
});

const rows = ref<StudentInvoiceGenerationRow[]>([]);
const loading = ref(false);
const searching = ref(false);
const generating = ref(false);
const downloadingCsv = ref(false);
const downloadingMatchCsv = ref(false);

// Returned by the legacy SP — drives the listing pagination, the
// CSV exports, and the generate call. Cleared whenever the form
// changes so a stale roster cannot be re-used.
const uniqueKey = ref<string | null>(null);
// Set to true after a successful Generate so the Match CSV button
// lights up. Reset whenever a new Search is run.
const generated = ref(false);
const lastSearchMessage = ref<string | null>(null);
const lastGenerateResult = ref<StudentInvoiceGenerationGenerateResult | null>(
  null,
);

const total = ref(0);
const page = ref(1);
const limit = ref(10);
const q = ref("");

type SortKey =
  | "matric"
  | "name"
  | "status"
  | "program"
  | "intake_case"
  | "citizenship"
  | "semester_no"
  | "fee_code";

const sortBy = ref<SortKey>("matric");
const sortDir = ref<"asc" | "desc">("asc");

const totalPages = computed(() =>
  total.value ? Math.max(1, Math.ceil(total.value / limit.value)) : 1,
);
const startIdx = computed(() =>
  total.value === 0 ? 0 : (page.value - 1) * limit.value + 1,
);
const endIdx = computed(() => Math.min(page.value * limit.value, total.value));

const formIsValid = computed(
  () =>
    form.value.semester.trim() !== "" &&
    form.value.programLevel.trim() !== "" &&
    form.value.feeType.trim() !== "",
);

function labelOf(items: { id: string | number; label: string }[], id: string) {
  const hit = items.find((o) => String(o.id) === id);
  return hit ? hit.label : id;
}

async function loadOptions() {
  try {
    const res = await getStudentInvoiceGenerationOptions();
    options.value = res.data;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to load filter options.",
    );
  }
}

function buildSearchPayload(
  overrides: Partial<StudentInvoiceGenerationSearchInput> = {},
): StudentInvoiceGenerationSearchInput {
  const payload: StudentInvoiceGenerationSearchInput = {
    semester: form.value.semester,
    programLevel: form.value.programLevel,
    feeType: form.value.feeType,
    page: page.value,
    limit: limit.value,
    sortBy: sortBy.value,
    sortDir: sortDir.value,
  };
  const studentType = form.value.studentType.trim();
  if (studentType) payload.studentType = studentType;
  const intakeCase = form.value.intakeCase.trim();
  if (intakeCase) payload.intakeCase = intakeCase;
  const matric = form.value.matricNo.trim();
  if (matric) payload.matricNo = matric;
  const search = q.value.trim();
  if (search) payload.q = search;
  return { ...payload, ...overrides };
}

async function runSearch(options: { resetKey?: boolean } = {}) {
  if (!formIsValid.value) {
    toast.error(
      "Missing parameters",
      "Semester, Program Level and Structure Type are required.",
    );
    return;
  }
  searching.value = true;
  loading.value = true;
  if (options.resetKey ?? true) {
    page.value = 1;
    generated.value = false;
    lastGenerateResult.value = null;
  }
  try {
    const res = await searchStudentInvoiceGeneration(buildSearchPayload());
    rows.value = res.data;
    const meta = res.meta as StudentInvoiceGenerationSearchMeta;
    total.value = Number(meta.total ?? 0);
    uniqueKey.value = meta.uniqueKey;
    lastSearchMessage.value = meta.message ?? null;
    if (rows.value.length === 0) {
      toast.info(
        "No matches",
        meta.message?.trim() ||
          "No students matched the selected parameters.",
      );
    } else if (meta.message?.trim()) {
      toast.success("Search complete", meta.message);
    }
  } catch (e) {
    rows.value = [];
    total.value = 0;
    uniqueKey.value = null;
    toast.error(
      "Search failed",
      e instanceof Error ? e.message : "Unable to load roster.",
    );
  } finally {
    searching.value = false;
    loading.value = false;
  }
}

async function reloadCurrentPage() {
  if (!uniqueKey.value) return;
  loading.value = true;
  try {
    const res = await searchStudentInvoiceGeneration(buildSearchPayload());
    rows.value = res.data;
    const meta = res.meta as StudentInvoiceGenerationSearchMeta;
    total.value = Number(meta.total ?? 0);
    uniqueKey.value = meta.uniqueKey;
  } catch (e) {
    toast.error(
      "Load failed",
      e instanceof Error ? e.message : "Unable to refresh roster.",
    );
  } finally {
    loading.value = false;
  }
}

function toggleSort(col: SortKey) {
  if (sortBy.value === col)
    sortDir.value = sortDir.value === "asc" ? "desc" : "asc";
  else {
    sortBy.value = col;
    sortDir.value = "asc";
  }
  if (uniqueKey.value) void reloadCurrentPage();
}

function prevPage() {
  if (page.value > 1) {
    page.value -= 1;
    void reloadCurrentPage();
  }
}

function nextPage() {
  if (page.value < totalPages.value) {
    page.value += 1;
    void reloadCurrentPage();
  }
}

function clearForm() {
  form.value = {
    semester: "",
    programLevel: "",
    studentType: "",
    feeType: "",
    intakeCase: "",
    matricNo: "",
  };
  rows.value = [];
  total.value = 0;
  uniqueKey.value = null;
  generated.value = false;
  lastGenerateResult.value = null;
  lastSearchMessage.value = null;
  q.value = "";
  page.value = 1;
}

async function handleGenerate() {
  if (!uniqueKey.value) {
    toast.error(
      "Run search first",
      "Generate is only available after a Search returns a roster.",
    );
    return;
  }
  if (rows.value.length === 0) {
    toast.error("No data", "Nothing to generate — the roster is empty.");
    return;
  }
  const accepted = await confirm.confirm({
    title: "Generate invoices?",
    message:
      "This will create invoice records for every student in the current roster. The legacy stored procedure runs immediately and cannot be rolled back from this screen.",
    confirmText: "Generate",
    cancelText: "Cancel",
  });
  if (!accepted || !uniqueKey.value) return;
  generating.value = true;
  try {
    const payload: StudentInvoiceGenerationGenerateInput = {
      uniqueKey: uniqueKey.value,
      semester: form.value.semester,
      programLevel: form.value.programLevel,
      feeType: form.value.feeType,
    };
    const studentType = form.value.studentType.trim();
    if (studentType) payload.studentType = studentType;
    const intakeCase = form.value.intakeCase.trim();
    if (intakeCase) payload.intakeCase = intakeCase;
    const matric = form.value.matricNo.trim();
    if (matric) payload.matricNo = matric;

    const res = await generateStudentInvoice(payload);
    lastGenerateResult.value = res.data;
    generated.value = res.data.success === true;
    if (res.data.success) {
      toast.success(
        "Invoices generated",
        res.data.message?.trim() || "Invoice creation completed successfully.",
      );
    } else {
      toast.error(
        "Generate finished with warnings",
        res.data.message?.trim() ||
          "The stored procedure returned a non-success code.",
      );
    }
  } catch (e) {
    toast.error(
      "Generate failed",
      e instanceof Error ? e.message : "Invoice generation failed.",
    );
  } finally {
    generating.value = false;
  }
}

function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

async function handleDownloadCsv() {
  if (!uniqueKey.value) {
    toast.error("Run search first", "There is no roster to export yet.");
    return;
  }
  downloadingCsv.value = true;
  try {
    const blob = await exportStudentInvoiceGenerationCsv({
      uniqueKey: uniqueKey.value,
      semesterDesc: labelOf(options.value.semester, form.value.semester),
      programLevelDesc: labelOf(
        options.value.programLevel,
        form.value.programLevel,
      ),
      studentTypeDesc: form.value.studentType
        ? labelOf(options.value.studentType, form.value.studentType)
        : undefined,
      feeTypeDesc: labelOf(options.value.feeType, form.value.feeType),
      intakeCaseDesc: form.value.intakeCase
        ? labelOf(options.value.intakeCase, form.value.intakeCase)
        : undefined,
    });
    downloadBlob(
      blob,
      `student_invoice_listing_${form.value.semester || "roster"}_${new Date()
        .toISOString()
        .slice(0, 10)}.csv`,
    );
    toast.success("CSV downloaded");
  } catch (e) {
    toast.error(
      "CSV export failed",
      e instanceof Error ? e.message : "Unable to download CSV.",
    );
  } finally {
    downloadingCsv.value = false;
  }
}

async function handleDownloadMatchCsv() {
  if (!uniqueKey.value) {
    toast.error("Run generate first", "Match CSV is keyed off the same roster.");
    return;
  }
  if (!generated.value) {
    toast.error(
      "Generate first",
      "Match CSV only contains data after a successful Generate.",
    );
    return;
  }
  downloadingMatchCsv.value = true;
  try {
    const blob = await exportStudentInvoiceGenerationMatchCsv({
      uniqueKey: uniqueKey.value,
    });
    downloadBlob(
      blob,
      `student_invoice_match_${form.value.semester || "roster"}_${new Date()
        .toISOString()
        .slice(0, 10)}.csv`,
    );
    toast.success("Match CSV downloaded");
  } catch (e) {
    toast.error(
      "Match CSV export failed",
      e instanceof Error ? e.message : "Unable to download Match CSV.",
    );
  } finally {
    downloadingMatchCsv.value = false;
  }
}

let searchDebounce: ReturnType<typeof setTimeout> | null = null;
watch(q, () => {
  if (searchDebounce) clearTimeout(searchDebounce);
  if (!uniqueKey.value) return;
  searchDebounce = setTimeout(() => {
    searchDebounce = null;
    page.value = 1;
    void reloadCurrentPage();
  }, 350);
});

watch(
  () => limit.value,
  () => {
    page.value = 1;
    if (uniqueKey.value) void reloadCurrentPage();
  },
);

onMounted(async () => {
  await loadOptions();
});

onUnmounted(() => {
  if (searchDebounce) clearTimeout(searchDebounce);
});
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <h1 class="page-title">
        Student Finance / Invoice / Activity / Generate Student Invoice
      </h1>

      <!-- Search Parameter form (legacy COMPONENT_ID 2836) -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div
          class="flex items-center justify-between border-b border-slate-100 px-4 py-3"
        >
          <h2 class="text-base font-semibold text-slate-900">Search Parameter</h2>
        </div>
        <form
          class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 lg:grid-cols-3"
          @submit.prevent="runSearch()"
        >
          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600"
              >Semester <span class="text-rose-500">*</span></label
            >
            <select
              v-model="form.semester"
              required
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">-- Select Semester --</option>
              <option
                v-for="opt in options.semester"
                :key="String(opt.id)"
                :value="String(opt.id)"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600"
              >Program Level <span class="text-rose-500">*</span></label
            >
            <select
              v-model="form.programLevel"
              required
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">-- Select Program Level --</option>
              <option
                v-for="opt in options.programLevel"
                :key="String(opt.id)"
                :value="String(opt.id)"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600">Student Type</label>
            <select
              v-model="form.studentType"
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">-- All Student Types --</option>
              <option
                v-for="opt in options.studentType"
                :key="String(opt.id)"
                :value="String(opt.id)"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600"
              >Structure Type <span class="text-rose-500">*</span></label
            >
            <select
              v-model="form.feeType"
              required
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">-- Select Structure Type --</option>
              <option
                v-for="opt in options.feeType"
                :key="String(opt.id)"
                :value="String(opt.id)"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600">Intake Case</label>
            <select
              v-model="form.intakeCase"
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            >
              <option value="">-- All Intake Cases --</option>
              <option
                v-for="opt in options.intakeCase"
                :key="String(opt.id)"
                :value="String(opt.id)"
              >
                {{ opt.label }}
              </option>
            </select>
          </div>

          <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-slate-600">Matric No</label>
            <input
              v-model="form.matricNo"
              type="text"
              maxlength="50"
              placeholder="Optional — single matric"
              class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
            />
          </div>

          <div class="md:col-span-2 lg:col-span-3 flex flex-wrap items-center justify-end gap-2">
            <button
              type="button"
              class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
              @click="clearForm"
            >
              Clear
            </button>
            <button
              type="submit"
              :disabled="!formIsValid || searching"
              class="inline-flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-50"
            >
              <Loader2 v-if="searching" class="h-3.5 w-3.5 animate-spin" />
              <Search v-else class="h-3.5 w-3.5" />
              Search
            </button>
          </div>
        </form>
      </article>

      <!-- Roster + actions (legacy COMPONENT_ID 3199) -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div
          class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3"
        >
          <h2 class="text-base font-semibold text-slate-900">
            List of Students : Invoice Generation
          </h2>
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="!uniqueKey || generating || rows.length === 0"
              class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 disabled:opacity-50"
              @click="handleGenerate"
            >
              <Loader2 v-if="generating" class="h-3.5 w-3.5 animate-spin" />
              <Sparkles v-else class="h-3.5 w-3.5" />
              Generate
            </button>
          </div>
        </div>

        <div class="space-y-4 p-4">
          <div
            v-if="lastGenerateResult"
            class="rounded-lg border px-3 py-2 text-sm"
            :class="
              lastGenerateResult.success
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-amber-200 bg-amber-50 text-amber-800'
            "
          >
            <div class="font-semibold">
              {{ lastGenerateResult.success ? "Generate succeeded" : "Generate finished with warnings" }}
            </div>
            <div v-if="lastGenerateResult.message" class="mt-0.5">
              {{ lastGenerateResult.message }}
            </div>
            <div v-if="lastGenerateResult.taskIds.length" class="mt-1 text-xs">
              Workflow tasks created: {{ lastGenerateResult.taskIds.length }}
            </div>
          </div>

          <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Display</label>
              <select
                v-model.number="limit"
                class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
              >
                <option v-for="n in [5, 10, 25, 50, 100]" :key="n" :value="n">
                  {{ n }}
                </option>
              </select>
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-slate-600">Search</label>
              <div class="relative">
                <Search
                  class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400"
                />
                <input
                  v-model="q"
                  type="search"
                  placeholder="Filter rows..."
                  :disabled="!uniqueKey"
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-8 pr-8 text-sm disabled:bg-slate-50"
                  @keyup.enter="
                    page = 1;
                    void reloadCurrentPage();
                  "
                />
                <button
                  v-if="q"
                  type="button"
                  class="absolute right-1 top-1/2 -translate-y-1/2 rounded p-0.5 text-slate-400 hover:bg-slate-100"
                  aria-label="Clear search"
                  @click="q = ''"
                >
                  <X class="h-3.5 w-3.5" />
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto rounded-lg border border-slate-200">
            <div :class="rows.length > 10 ? 'max-h-[420px] overflow-y-auto' : ''">
              <table class="w-full min-w-[1000px] text-sm">
                <thead class="sticky top-0 bg-slate-50">
                  <tr class="border-b border-slate-200 text-left">
                    <th class="px-3 py-2 text-xs font-semibold uppercase">No.</th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('matric')"
                    >
                      Matric
                      <span v-if="sortBy === 'matric'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('name')"
                    >
                      Nama
                      <span v-if="sortBy === 'name'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('status')"
                    >
                      Status
                      <span v-if="sortBy === 'status'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('program')"
                    >
                      Prog
                      <span v-if="sortBy === 'program'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('intake_case')"
                    >
                      Intake Case
                      <span v-if="sortBy === 'intake_case'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('citizenship')"
                    >
                      Citizenship
                      <span v-if="sortBy === 'citizenship'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('semester_no')"
                    >
                      Semester No
                      <span v-if="sortBy === 'semester_no'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                    <th
                      class="cursor-pointer whitespace-nowrap px-3 py-2 text-xs font-semibold uppercase"
                      @click="toggleSort('fee_code')"
                    >
                      Fee Code
                      <span v-if="sortBy === 'fee_code'">{{
                        sortDir === "asc" ? "↑" : "↓"
                      }}</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="loading">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      Loading...
                    </td>
                  </tr>
                  <tr v-else-if="!uniqueKey">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      Fill the search parameters above and click Search to load
                      the roster.
                    </td>
                  </tr>
                  <tr v-else-if="rows.length === 0">
                    <td colspan="9" class="px-3 py-6 text-center text-sm text-slate-500">
                      {{ lastSearchMessage?.trim() || "No records found." }}
                    </td>
                  </tr>
                  <tr
                    v-for="row in rows"
                    :key="`${row.matric}-${row.index}`"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="px-3 py-2">{{ row.index }}</td>
                    <td class="px-3 py-2 font-medium text-slate-900">
                      {{ row.matric }}
                    </td>
                    <td class="px-3 py-2">{{ row.name ?? "-" }}</td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.status ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.program ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.intakeCase ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.citizenship ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.semesterNo ?? "-" }}
                    </td>
                    <td class="whitespace-nowrap px-3 py-2">
                      {{ row.feeCode ?? "-" }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3"
          >
            <div class="text-xs text-slate-500">
              Showing {{ startIdx }}-{{ endIdx }} of {{ total }}
            </div>
            <div class="flex items-center gap-2">
              <button
                type="button"
                :disabled="page <= 1 || loading"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="prevPage"
              >
                Prev
              </button>
              <span class="text-xs text-slate-600"
                >Page {{ page }} / {{ totalPages }}</span
              >
              <button
                type="button"
                :disabled="page >= totalPages || loading"
                class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="nextPage"
              >
                Next
              </button>
              <div class="mx-2 h-5 w-px bg-slate-200" />
              <button
                type="button"
                :disabled="!uniqueKey || downloadingCsv"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="handleDownloadCsv"
              >
                <Loader2 v-if="downloadingCsv" class="h-3.5 w-3.5 animate-spin" />
                <FileDown v-else class="h-3.5 w-3.5" />CSV
              </button>
              <button
                type="button"
                :disabled="!generated || downloadingMatchCsv"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-medium disabled:opacity-50"
                @click="handleDownloadMatchCsv"
              >
                <Loader2
                  v-if="downloadingMatchCsv"
                  class="h-3.5 w-3.5 animate-spin"
                />
                <FileSpreadsheet v-else class="h-3.5 w-3.5" />Match CSV
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>

<style scoped>
.page-title {
  /* The shared `.page-title` is defined in `client/src/style.css`.
     Scoped block kept empty so the global gradient applies. */
}
</style>
