<script setup lang="ts">
import { onMounted, ref, computed } from "vue";
import {
  BookOpen,
  ChevronDown,
  Pencil,
  Save,
  CheckCircle2,
  RefreshCw,
  FileCheck,
  FileWarning,
  Eye,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import MarkdownEditor from "@/components/MarkdownEditor.vue";
import { markdownToSafeHtml } from "@/utils/markdown";
import { getDevelopersGuide, updateDevelopersGuide } from "@/api/cms";
import { useToast } from "@/composables/useToast";

type SyncFile = {
  filename: string;
  path?: string;
  exists: boolean;
  inSync: boolean;
  readOnly?: boolean;
  role?: "canonical" | "mirror";
};

type Section = {
  title: string;
  body: string;
  html: string;
};

const toast = useToast();

const content = ref("");
const originalContent = ref("");
const syncFiles = ref<SyncFile[]>([]);
const loading = ref(true);
const saving = ref(false);
const saved = ref(false);
const error = ref("");
const mode = ref<"view" | "edit">("view");
const openSections = ref<Set<number>>(new Set([0]));

const hasChanges = computed(() => content.value !== originalContent.value);

const sections = computed<Section[]>(() => {
  const raw = content.value || "";
  const parts = raw.split(/^(?=## )/m);
  const result: Section[] = [];

  for (const part of parts) {
    const trimmed = part.trim();
    if (!trimmed) continue;

    const firstNewline = trimmed.indexOf("\n");
    if (trimmed.startsWith("## ") && firstNewline > 0) {
      const title = trimmed.slice(3, firstNewline).trim();
      const body = trimmed.slice(firstNewline + 1).trim();
      result.push({ title, body, html: markdownToSafeHtml(body) });
    } else if (trimmed.startsWith("# ") || !trimmed.startsWith("## ")) {
      // Preamble (title + intro before first ## section)
      result.push({ title: "Overview", body: trimmed, html: markdownToSafeHtml(trimmed) });
    } else {
      // ## with no body
      const title = trimmed.slice(3).trim();
      result.push({ title, body: "", html: "" });
    }
  }

  return result;
});

function toggleSection(index: number) {
  if (openSections.value.has(index)) {
    openSections.value.delete(index);
  } else {
    openSections.value.add(index);
  }
  // Trigger reactivity
  openSections.value = new Set(openSections.value);
}

function expandAll() {
  openSections.value = new Set(sections.value.map((_, i) => i));
}

function collapseAll() {
  openSections.value = new Set();
}

async function load() {
  loading.value = true;
  error.value = "";
  try {
    const res = await getDevelopersGuide();
    content.value = res.data.content;
    originalContent.value = res.data.content;
    syncFiles.value = res.data.syncFiles ?? [];
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to load developers guide";
  } finally {
    loading.value = false;
  }
}

async function save() {
  saving.value = true;
  error.value = "";
  try {
    const res = await updateDevelopersGuide(content.value);
    originalContent.value = content.value;
    syncFiles.value = res.data.syncFiles ?? [];
    saved.value = true;
    toast.success("Developers guide saved and synced");
    setTimeout(() => { saved.value = false; }, 2000);
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to save developers guide";
    toast.error("Save failed", error.value);
  } finally {
    saving.value = false;
  }
}

function discard() {
  content.value = originalContent.value;
}

function switchToEdit() {
  mode.value = "edit";
}

function switchToView() {
  mode.value = "view";
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <!-- ───── Header ───── -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="page-title">Developers Guide</h1>
          <p class="mt-0.5 text-sm text-slate-500">
            AI coding reference in <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">docs/ai-guidelines/</code> &mdash; edit
            <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">CLAUDE.md</code> and auto-sync to
            <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">.cursorrules</code>,
            <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">AGENTS.md</code>
          </p>
        </div>
        <!-- Mode toggle -->
        <div v-if="!loading" class="flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1">
          <button
            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="mode === 'view' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
            @click="switchToView"
          >
            <Eye class="h-3.5 w-3.5" />
            View
          </button>
          <button
            class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors"
            :class="mode === 'edit' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
            @click="switchToEdit"
          >
            <Pencil class="h-3.5 w-3.5" />
            Edit
          </button>
        </div>
      </div>

      <!-- ───── Loading ───── -->
      <div v-if="loading" class="flex items-center justify-center py-20">
        <RefreshCw class="h-5 w-5 animate-spin text-slate-400" />
        <span class="ml-2 text-sm text-slate-500">Loading...</span>
      </div>

      <template v-else>
        <!-- ───── Sync Status ───── -->
        <article v-if="syncFiles.length > 0" class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <RefreshCw class="h-4 w-4 text-blue-600" />
            <h2 class="text-sm font-semibold text-slate-900">Sync Status</h2>
          </div>
          <div class="flex flex-wrap gap-3 p-4">
            <div
              v-for="file in syncFiles"
              :key="file.path ?? file.filename"
              class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
              :class="file.inSync
                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                : 'border-amber-200 bg-amber-50 text-amber-700'"
            >
              <FileCheck v-if="file.inSync" class="h-4 w-4" />
              <FileWarning v-else class="h-4 w-4" />
              <code class="text-xs font-medium">{{ file.path ?? file.filename }}</code>
              <span
                v-if="file.role === 'canonical'"
                class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
              >
                Canonical
              </span>
              <span
                v-else-if="file.readOnly"
                class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600"
              >
                Read-only
              </span>
              <span class="text-xs">{{ file.inSync ? 'In sync' : 'Out of sync' }}</span>
            </div>
          </div>
        </article>

        <!-- ═══════ VIEW MODE: Accordion ═══════ -->
        <template v-if="mode === 'view'">
          <!-- Expand/Collapse controls -->
          <div class="flex items-center justify-between">
            <span class="text-xs text-slate-400">{{ sections.length }} sections</span>
            <div class="flex items-center gap-2">
              <button class="text-xs font-medium text-slate-500 hover:text-slate-700" @click="expandAll">Expand all</button>
              <span class="text-slate-300">|</span>
              <button class="text-xs font-medium text-slate-500 hover:text-slate-700" @click="collapseAll">Collapse all</button>
            </div>
          </div>

          <div class="space-y-1">
            <article
              v-for="(section, index) in sections"
              :key="index"
              class="rounded-lg border border-slate-200 bg-white shadow-sm overflow-hidden"
            >
              <!-- Accordion header -->
              <button
                class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
                @click="toggleSection(index)"
              >
                <ChevronDown
                  class="h-4 w-4 shrink-0 text-slate-400 transition-transform duration-200"
                  :class="{ '-rotate-90': !openSections.has(index) }"
                />
                <span class="text-sm font-semibold text-slate-900">{{ section.title }}</span>
              </button>

              <!-- Accordion body -->
              <div
                v-show="openSections.has(index)"
                class="border-t border-slate-100 px-4 py-4"
              >
                <div
                  v-if="section.html"
                  class="markdown-preview prose-sm"
                  v-html="section.html"
                />
                <p v-else class="text-sm text-slate-400 italic">No content in this section.</p>
              </div>
            </article>
          </div>
        </template>

        <!-- ═══════ EDIT MODE: Raw Editor ═══════ -->
        <template v-else>
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <BookOpen class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">CLAUDE.md</h2>
              <span v-if="hasChanges" class="ml-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Unsaved changes</span>
            </div>
            <div class="p-4">
              <MarkdownEditor v-model="content" :rows="28" placeholder="Write your AI coding guidelines in markdown..." />
            </div>
          </article>

          <!-- ───── Actions ───── -->
          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <button
                class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800 disabled:opacity-50"
                :disabled="saving || !hasChanges"
                @click="save"
              >
                <Save class="h-4 w-4" />
                {{ saving ? 'Saving...' : 'Save & Sync All' }}
              </button>
              <button
                v-if="hasChanges"
                class="flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
                @click="discard"
              >
                Discard Changes
              </button>
              <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="translate-y-1 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
              >
                <span v-if="saved" class="flex items-center gap-1.5 text-sm font-medium text-emerald-600">
                  <CheckCircle2 class="h-4 w-4" />
                  Saved & synced to all files
                </span>
              </Transition>
            </div>
            <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
          </div>
        </template>
      </template>
    </div>
  </AdminLayout>
</template>

<style scoped>
.markdown-preview :deep(h1),
.markdown-preview :deep(h2),
.markdown-preview :deep(h3) {
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  font-weight: 700;
  color: rgb(15 23 42);
}

.markdown-preview :deep(h3) {
  font-size: 0.9375rem;
}

.markdown-preview :deep(p) {
  margin: 0.5rem 0;
  color: rgb(51 65 85);
  line-height: 1.6;
  font-size: 0.875rem;
}

.markdown-preview :deep(ul),
.markdown-preview :deep(ol) {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
  color: rgb(51 65 85);
  font-size: 0.875rem;
}

.markdown-preview :deep(li) {
  margin: 0.2rem 0;
}

.markdown-preview :deep(code) {
  border-radius: 0.25rem;
  background: rgb(241 245 249);
  padding: 0.1rem 0.3rem;
  font-size: 0.8em;
  color: rgb(51 65 85);
}

.markdown-preview :deep(pre) {
  overflow-x: auto;
  border-radius: 0.5rem;
  background: rgb(15 23 42);
  color: rgb(241 245 249);
  padding: 0.75rem;
  margin: 0.75rem 0;
}

.markdown-preview :deep(pre code) {
  background: transparent;
  padding: 0;
  border-radius: 0;
  color: inherit;
  font-size: 0.8em;
}

.markdown-preview :deep(a) {
  color: rgb(124 58 237);
  text-decoration: underline;
}

.markdown-preview :deep(blockquote) {
  margin: 0.5rem 0;
  border-left: 3px solid rgb(203 213 225);
  padding-left: 0.75rem;
  color: rgb(71 85 105);
  font-size: 0.875rem;
}

.markdown-preview :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
  font-size: 0.8125rem;
}

.markdown-preview :deep(th),
.markdown-preview :deep(td) {
  border: 1px solid rgb(226 232 240);
  padding: 0.4rem 0.625rem;
  text-align: left;
}

.markdown-preview :deep(th) {
  background: rgb(248 250 252);
  font-weight: 600;
  color: rgb(15 23 42);
}

.markdown-preview :deep(td) {
  color: rgb(51 65 85);
}

.markdown-preview :deep(hr) {
  border: none;
  border-top: 1px solid rgb(226 232 240);
  margin: 1rem 0;
}

.markdown-preview :deep(strong) {
  font-weight: 600;
  color: rgb(15 23 42);
}
</style>
