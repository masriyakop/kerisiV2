<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import {
  Image,
  Upload,
  Trash2,
  AlertCircle,
  FolderOpen,
  Save,
} from "lucide-vue-next";

import { API_BASE_URL } from "@/env";
import AdminLayout from "@/layouts/AdminLayout.vue";
import { listMedia, removeMedia, updateMediaMetadata, uploadMedia } from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type { Media, MediaMetadataInput } from "@/types";

const rows = ref<Media[]>([]);
const error = ref("");
const uploading = ref(false);
const deleting = ref(false);
const selectedIds = ref<number[]>([]);
const isDragOver = ref(false);
const activeMediaId = ref<number | null>(null);
const savingMetadata = ref(false);
const confirmDialog = useConfirmDialog();
const toast = useToast();
const metadataForm = ref<MediaMetadataInput>({
  title: "",
  altText: "",
  caption: "",
  description: "",
});

async function load() {
  const response = await listMedia();
  rows.value = response.data;
  selectedIds.value = selectedIds.value.filter((id) => rows.value.some((item) => item.id === id));

  if (activeMediaId.value && !rows.value.some((item) => item.id === activeMediaId.value)) {
    activeMediaId.value = rows.value[0]?.id ?? null;
  }
  if (!activeMediaId.value && rows.value.length > 0) {
    activeMediaId.value = rows.value[0].id;
  }
}

async function uploadFiles(files: File[]) {
  if (!files.length) return;
  error.value = "";
  uploading.value = true;
  try {
    for (const file of files) {
      await uploadMedia(file);
    }
    await load();
    toast.success("Upload complete", `${files.length} file(s) uploaded.`);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Upload failed";
    toast.error("Upload failed", error.value);
  } finally {
    uploading.value = false;
  }
}

async function onFileChange(event: Event) {
  const target = event.target as HTMLInputElement;
  const files = target.files ? Array.from(target.files) : [];
  await uploadFiles(files);
  target.value = "";
}

function onDragOver(event: DragEvent) {
  event.preventDefault();
  isDragOver.value = true;
}

function onDragLeave(event: DragEvent) {
  event.preventDefault();
  isDragOver.value = false;
}

async function onDrop(event: DragEvent) {
  event.preventDefault();
  isDragOver.value = false;
  const files = event.dataTransfer?.files ? Array.from(event.dataTransfer.files) : [];
  await uploadFiles(files);
}

async function remove(id: number) {
  const allowed = await confirmDialog.confirm({
    title: "Delete media file?",
    message: "This action cannot be undone.",
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  error.value = "";
  try {
    await removeMedia(id);
    selectedIds.value = selectedIds.value.filter((value) => value !== id);
    await load();
    toast.success("Media deleted");
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Delete failed";
    toast.error("Delete failed", error.value);
  }
}

function toggleSelect(id: number) {
  if (selectedIds.value.includes(id)) {
    selectedIds.value = selectedIds.value.filter((value) => value !== id);
    return;
  }
  selectedIds.value = [...selectedIds.value, id];
}

const allSelected = computed(() => rows.value.length > 0 && selectedIds.value.length === rows.value.length);

function toggleSelectAll() {
  if (allSelected.value) {
    selectedIds.value = [];
    return;
  }
  selectedIds.value = rows.value.map((item) => item.id);
}

async function removeSelected() {
  if (selectedIds.value.length === 0) return;
  const allowed = await confirmDialog.confirm({
    title: "Delete selected files?",
    message: `Delete ${selectedIds.value.length} selected file(s). This action cannot be undone.`,
    confirmText: "Delete all",
    destructive: true,
  });
  if (!allowed) return;

  error.value = "";
  deleting.value = true;
  try {
    const targets = [...selectedIds.value];
    const results = await Promise.allSettled(targets.map((id) => removeMedia(id)));
    const failed = results.filter((result) => result.status === "rejected").length;

    if (failed > 0) {
      error.value = `${failed} file(s) failed to delete.`;
      toast.error("Partial delete", error.value);
    } else {
      toast.success("Selected media deleted");
    }
    await load();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Bulk delete failed";
    toast.error("Delete failed", error.value);
  } finally {
    deleting.value = false;
  }
}

const withBase = computed(() => (path: string) => `${API_BASE_URL}${path}`);

const activeMedia = computed(() => rows.value.find((item) => item.id === activeMediaId.value) ?? null);

watch(activeMedia, (item) => {
  metadataForm.value = {
    title: item?.title ?? "",
    altText: item?.altText ?? "",
    caption: item?.caption ?? "",
    description: item?.description ?? "",
  };
});

function selectItem(id: number) {
  activeMediaId.value = id;
}

async function saveMetadata() {
  const media = activeMedia.value;
  if (!media) return;

  error.value = "";
  savingMetadata.value = true;
  try {
    const response = await updateMediaMetadata(media.id, metadataForm.value);
    const updated = response.data;
    rows.value = rows.value.map((item) => (item.id === updated.id ? updated : item));
    toast.success("Metadata saved");
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to save metadata";
    toast.error("Save failed", error.value);
  } finally {
    savingMetadata.value = false;
  }
}

function formatFileSize(size: number) {
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(iso: string) {
  return new Date(iso).toLocaleString();
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Media Library</h1>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Upload class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Upload</h2>
        </div>
        <div class="p-4">
          <label
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-5 transition-colors"
            :class="isDragOver ? 'border-violet-500 bg-violet-50/50' : 'border-slate-300 bg-slate-50 hover:border-violet-400 hover:bg-violet-50/30'"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
          >
            <Upload class="mb-2 h-6 w-6 text-slate-400" />
            <p class="text-sm font-medium text-slate-700">Click or drag files to upload</p>
            <p class="mt-1 text-xs text-slate-400">PNG, JPG, GIF, WEBP, AVIF, HEIC, SVG, ICO up to 10MB each</p>
            <input type="file" class="hidden" multiple @change="onFileChange" />
          </label>
          <p v-if="uploading" class="mt-3 text-sm text-violet-600">Uploading...</p>
          <div v-if="error" class="mt-3 flex items-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700">
            <AlertCircle class="h-4 w-4 shrink-0" />
            {{ error }}
          </div>
        </div>
      </article>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5">
          <div class="flex items-center gap-2">
            <FolderOpen class="h-4 w-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-900">Library</h2>
          </div>
          <div class="flex items-center gap-2">
            <button
              v-if="rows.length > 0"
              class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
              @click="toggleSelectAll"
            >
              {{ allSelected ? "Clear" : "Select all" }}
            </button>
            <button
              class="inline-flex items-center gap-1.5 rounded-md border border-rose-200 px-2.5 py-1.5 text-xs font-medium text-rose-700 transition-colors hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="selectedIds.length === 0 || deleting"
              @click="removeSelected"
            >
              <Trash2 class="h-3.5 w-3.5" />
              {{ deleting ? "Deleting..." : `Delete selected (${selectedIds.length})` }}
            </button>
          </div>
        </div>
        <div class="p-4">
          <div v-if="rows.length > 0" class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="grid auto-rows-min grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-5">
              <button
                v-for="item in rows"
                :key="item.id"
                class="group self-start overflow-hidden rounded-lg border bg-white text-left shadow-sm transition-all hover:shadow-md"
                :class="activeMediaId === item.id ? 'border-violet-400 ring-1 ring-violet-200' : 'border-slate-200'"
                @click="selectItem(item.id)"
              >
                <div class="relative aspect-square bg-slate-100">
                  <label class="absolute left-2 top-2 z-10 inline-flex items-center rounded-md bg-white/95 px-1.5 py-1 shadow-sm backdrop-blur-sm" @click.stop>
                    <input
                      type="checkbox"
                      class="h-3.5 w-3.5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                      :checked="selectedIds.includes(item.id)"
                      @change="toggleSelect(item.id)"
                    />
                  </label>
                  <img
                    :src="withBase(item.url)"
                    :alt="item.altText || item.originalName"
                    class="absolute inset-0 h-full w-full object-cover"
                  />
                  <button
                    class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-lg bg-white/90 text-slate-400 opacity-0 shadow-sm backdrop-blur-sm transition-all hover:bg-rose-50 hover:text-rose-600 group-hover:opacity-100"
                    title="Delete"
                    @click.stop="remove(item.id)"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                  </button>
                </div>
                <div class="px-2.5 py-2">
                  <p class="truncate text-sm font-medium text-slate-900">{{ item.title || item.originalName }}</p>
                  <p class="text-xs text-slate-400">{{ item.mimeType }}</p>
                </div>
              </button>
            </div>

            <aside class="rounded-lg border border-slate-200 bg-slate-50/60 p-3">
              <div v-if="activeMedia" class="space-y-3">
                <img :src="withBase(activeMedia.url)" :alt="activeMedia.altText || activeMedia.originalName" class="h-40 w-full rounded-md border border-slate-200 bg-white object-contain" />
                <div class="space-y-1 text-xs text-slate-500">
                  <p><span class="font-medium text-slate-700">File:</span> {{ activeMedia.originalName }}</p>
                  <p><span class="font-medium text-slate-700">Type:</span> {{ activeMedia.mimeType }}</p>
                  <p><span class="font-medium text-slate-700">Size:</span> {{ formatFileSize(activeMedia.size) }}</p>
                  <p><span class="font-medium text-slate-700">Uploaded:</span> {{ formatDate(activeMedia.createdAt) }}</p>
                  <p class="truncate"><span class="font-medium text-slate-700">URL:</span> {{ withBase(activeMedia.url) }}</p>
                </div>

                <div class="space-y-2 border-t border-slate-200 pt-3">
                  <label class="block text-xs font-medium text-slate-700">Title</label>
                  <input v-model="metadataForm.title" class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100" />

                  <label class="block text-xs font-medium text-slate-700">Alt Text</label>
                  <input v-model="metadataForm.altText" class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100" />

                  <label class="block text-xs font-medium text-slate-700">Caption</label>
                  <textarea v-model="metadataForm.caption" rows="2" class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"></textarea>

                  <label class="block text-xs font-medium text-slate-700">Description</label>
                  <textarea v-model="metadataForm.description" rows="3" class="w-full rounded-md border border-slate-300 bg-white px-2.5 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-100"></textarea>

                  <button
                    class="inline-flex items-center gap-1.5 rounded-md bg-slate-900 px-3 py-2 text-xs font-medium text-white transition-colors hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="savingMetadata"
                    @click="saveMetadata"
                  >
                    <Save class="h-3.5 w-3.5" />
                    {{ savingMetadata ? "Saving..." : "Save Metadata" }}
                  </button>
                </div>
              </div>
              <div v-else class="py-10 text-center text-sm text-slate-400">
                Select an item to edit metadata.
              </div>
            </aside>
          </div>
          <div v-else class="flex flex-col items-center justify-center py-8 text-center">
            <Image class="mb-3 h-10 w-10 text-slate-300" />
            <p class="text-sm font-medium text-slate-500">No media files yet</p>
            <p class="mt-1 text-xs text-slate-400">Upload your first file to get started.</p>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
