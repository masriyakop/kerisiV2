<script setup lang="ts">
import { computed, ref } from "vue";

import { markdownToSafeHtml } from "@/utils/markdown";

const props = defineProps<{
  modelValue: string;
  placeholder?: string;
  rows?: number;
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: string): void;
}>();

const textareaRef = ref<HTMLTextAreaElement | null>(null);
const mode = ref<"write" | "preview">("write");

const safeHtml = computed(() => markdownToSafeHtml(props.modelValue || ""));

function updateContent(value: string) {
  emit("update:modelValue", value);
}

function surroundSelection(prefix: string, suffix = prefix) {
  const el = textareaRef.value;
  if (!el) return;

  const start = el.selectionStart;
  const end = el.selectionEnd;
  const value = props.modelValue || "";
  const selected = value.slice(start, end) || "text";
  const nextValue = `${value.slice(0, start)}${prefix}${selected}${suffix}${value.slice(end)}`;
  updateContent(nextValue);

  requestAnimationFrame(() => {
    el.focus();
    el.setSelectionRange(start + prefix.length, start + prefix.length + selected.length);
  });
}

function insertLine(snippet: string) {
  const el = textareaRef.value;
  if (!el) return;

  const start = el.selectionStart;
  const value = props.modelValue || "";
  const nextValue = `${value.slice(0, start)}${snippet}${value.slice(start)}`;
  updateContent(nextValue);

  requestAnimationFrame(() => {
    el.focus();
    const cursor = start + snippet.length;
    el.setSelectionRange(cursor, cursor);
  });
}
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-3 py-2">
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
          :class="mode === 'write' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          @click="mode = 'write'"
        >
          Write
        </button>
        <button
          type="button"
          class="rounded-md px-2 py-1 text-xs font-medium transition-colors"
          :class="mode === 'preview' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
          @click="mode = 'preview'"
        >
          Preview
        </button>
      </div>
      <div v-if="mode === 'write'" class="flex flex-wrap items-center gap-1">
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="surroundSelection('**')">Bold</button>
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="surroundSelection('_')">Italic</button>
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="insertLine('## Heading\\n')">H2</button>
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="insertLine('- List item\\n')">List</button>
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="insertLine('[Link text](https://example.com)\\n')">Link</button>
        <button type="button" class="rounded-md border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="surroundSelection('`')">Code</button>
      </div>
    </div>

    <div v-if="mode === 'write'" class="p-3">
      <textarea
        ref="textareaRef"
        :value="modelValue"
        :rows="rows || 16"
        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
        :placeholder="placeholder || 'Write markdown content...'"
        @input="updateContent(($event.target as HTMLTextAreaElement).value)"
      />
      <p class="mt-2 text-xs text-slate-400">Markdown supported. Preview is sanitized before render.</p>
    </div>

    <div v-else class="markdown-preview p-4">
      <div v-if="!modelValue.trim()" class="text-sm text-slate-400">Nothing to preview yet.</div>
      <div v-else v-html="safeHtml" />
    </div>
  </div>
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

.markdown-preview :deep(p) {
  margin: 0.5rem 0;
  color: rgb(51 65 85);
  line-height: 1.6;
}

.markdown-preview :deep(ul),
.markdown-preview :deep(ol) {
  margin: 0.5rem 0;
  padding-left: 1.25rem;
  color: rgb(51 65 85);
}

.markdown-preview :deep(code) {
  border-radius: 0.25rem;
  background: rgb(241 245 249);
  padding: 0.1rem 0.3rem;
  font-size: 0.85em;
  color: rgb(51 65 85);
}

.markdown-preview :deep(pre) {
  overflow-x: auto;
  border-radius: 0.5rem;
  background: rgb(15 23 42);
  color: rgb(241 245 249);
  padding: 0.75rem;
}

.markdown-preview :deep(pre code) {
  background: transparent;
  padding: 0;
  border-radius: 0;
  color: inherit;
  font-size: 0.85em;
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
}

.markdown-preview :deep(table) {
  width: 100%;
  border-collapse: collapse;
  margin: 0.75rem 0;
  font-size: 0.875rem;
}

.markdown-preview :deep(th),
.markdown-preview :deep(td) {
  border: 1px solid rgb(226 232 240);
  padding: 0.5rem 0.75rem;
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
  margin: 1.5rem 0;
}

.markdown-preview :deep(strong) {
  font-weight: 600;
  color: rgb(15 23 42);
}
</style>
