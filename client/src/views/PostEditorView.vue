<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Save,
  X,
  Type,
  Upload,
  ImagePlus,
  Trash2,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import MarkdownEditor from "@/components/MarkdownEditor.vue";
import { createPost, getPost, listMedia, listCategories, updatePost, uploadMedia } from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import { API_BASE_URL } from "@/env";
import type { Category, Media, PublishStatus } from "@/types";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();

const id = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => id.value > 0);

const title = ref("");
const slug = ref("");
const excerpt = ref("");
const content = ref("");
const status = ref<PublishStatus>("draft");
const featuredImageId = ref<number | null>(null);
const mediaItems = ref<Media[]>([]);
const categories = ref<Category[]>([]);
const selectedCategoryIds = ref<number[]>([]);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const showMediaPicker = ref(false);

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

const selectedImage = computed(() =>
  mediaItems.value.find((m) => m.id === featuredImageId.value) ?? null,
);

const imageMediaItems = computed(() =>
  mediaItems.value.filter((m) => m.mimeType.startsWith("image/")),
);

async function loadMedia() {
  const response = await listMedia();
  mediaItems.value = response.data;
}

async function loadCategories() {
  const response = await listCategories("?limit=100");
  categories.value = response.data;
}

async function load() {
  await Promise.all([loadMedia(), loadCategories()]);
  if (!isEdit.value) return;
  const response = await getPost(id.value);
  const post = response.data;
  title.value = post.title;
  slug.value = post.slug;
  excerpt.value = post.excerpt || "";
  content.value = post.content;
  status.value = post.status;
  featuredImageId.value = post.featuredImageId ?? null;
  selectedCategoryIds.value = post.categories?.map((c) => c.id) ?? [];
}

async function handleUpload(event: Event) {
  const input = event.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  uploading.value = true;
  try {
    const response = await uploadMedia(file);
    mediaItems.value.unshift(response.data);
    featuredImageId.value = response.data.id;
    toast.success("Image uploaded");
  } catch (e) {
    toast.error("Upload failed", e instanceof Error ? e.message : "Unable to upload image.");
  } finally {
    uploading.value = false;
    input.value = "";
  }
}

async function save() {
  const payload = {
    title: title.value,
    slug: slug.value || undefined,
    excerpt: excerpt.value || undefined,
    content: content.value,
    status: status.value,
    featuredImageId: featuredImageId.value,
    categoryIds: selectedCategoryIds.value,
  };

  try {
    if (isEdit.value) {
      await updatePost(id.value, payload);
      toast.success("Post updated");
    } else {
      await createPost(payload);
      toast.success("Post created");
    }
    router.push("/admin/posts");
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save post.");
  }
}

async function removeFeaturedImage() {
  if (!featuredImageId.value) return;
  const allowed = await confirmDialog.confirm({
    title: "Remove featured image?",
    message: "This only removes the image from this post.",
    confirmText: "Remove",
    destructive: true,
  });
  if (!allowed) return;
  featuredImageId.value = null;
  toast.info("Featured image removed");
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <!-- ───── Title ───── -->
      <h1 class="page-title">{{ isEdit ? 'Edit Post' : 'Create Post' }}</h1>

      <div class="grid gap-4 lg:grid-cols-[1fr_320px]">
        <!-- ═══════ LEFT COLUMN — Content ═══════ -->
        <div class="space-y-4">
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <Type class="h-4 w-4 text-blue-600" />
              <h2 class="text-sm font-semibold text-slate-900">Content</h2>
            </div>
            <div class="space-y-3 p-4">
              <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Title</label>
                  <input v-model="title" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Enter post title" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Slug</label>
                  <input v-model="slug" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="auto-generated-from-title" />
                </div>
              </div>
              <div class="space-y-1.5">
                <label class="text-sm font-medium text-slate-700">Excerpt</label>
                <textarea v-model="excerpt" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Brief summary of the post..." />
              </div>
              <div class="space-y-1.5">
                <label class="text-sm font-medium text-slate-700">Content</label>
                <MarkdownEditor v-model="content" :rows="16" placeholder="Write your post using Markdown..." />
              </div>
            </div>
          </article>
        </div>

        <!-- ═══════ RIGHT COLUMN — Settings, Categories, Actions ═══════ -->
        <div class="space-y-4">
          <!-- Actions -->
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-2.5">
              <h3 class="text-sm font-semibold text-slate-900">Publish</h3>
            </div>
            <div class="space-y-3 p-3">
              <div class="space-y-1.5">
                <label class="text-sm font-medium text-slate-700">Status</label>
                <select v-model="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                  <option value="archived">Archived</option>
                </select>
              </div>
              <div class="flex items-center gap-2 pt-1">
                <button
                  class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800"
                  @click="save"
                >
                  <Save class="h-4 w-4" />
                  {{ isEdit ? 'Update' : 'Publish' }}
                </button>
                <button
                  class="flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
                  @click="router.push('/admin/posts')"
                >
                  <X class="h-4 w-4" />
                </button>
              </div>
            </div>
          </article>

          <!-- Categories -->
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-2.5">
              <h3 class="text-sm font-semibold text-slate-900">Categories</h3>
            </div>
            <div class="p-3">
              <div v-if="categories.length === 0" class="text-sm text-slate-400">No categories available.</div>
              <div v-else class="max-h-48 space-y-1 overflow-y-auto">
                <label
                  v-for="cat in categories"
                  :key="cat.id"
                  class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors hover:bg-slate-50"
                >
                  <input
                    type="checkbox"
                    :value="cat.id"
                    v-model="selectedCategoryIds"
                    class="h-4 w-4 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                  />
                  <span class="text-slate-700">{{ cat.name }}</span>
                </label>
              </div>
            </div>
          </article>

          <!-- Featured Image -->
          <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-2.5">
              <h3 class="text-sm font-semibold text-slate-900">Featured Image</h3>
            </div>
            <div class="p-3">
              <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="handleUpload" />

              <!-- Thumbnail preview when image is set -->
              <div v-if="selectedImage" class="space-y-3">
                <div class="overflow-hidden rounded-lg border border-slate-200">
                  <img :src="resolveUrl(selectedImage.url)" :alt="selectedImage.originalName" class="w-full object-cover" />
                </div>
                <p class="truncate text-xs text-slate-500">{{ selectedImage.originalName }}</p>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50"
                    :disabled="uploading"
                    @click="fileInput?.click()"
                  >
                    <Upload class="h-3.5 w-3.5" />
                    {{ uploading ? 'Uploading...' : 'Replace' }}
                  </button>
                  <button
                    type="button"
                    class="flex items-center justify-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-rose-600 transition-colors hover:bg-rose-50"
                    @click="removeFeaturedImage"
                  >
                    <Trash2 class="h-3.5 w-3.5" />
                    Remove
                  </button>
                </div>
                <button
                  type="button"
                  class="w-full text-center text-xs font-medium text-violet-600 transition-colors hover:text-violet-700"
                  @click="showMediaPicker = !showMediaPicker"
                >
                  {{ showMediaPicker ? 'Hide media library' : 'Choose from media library' }}
                </button>
              </div>

              <!-- Empty state when no image -->
              <div v-else class="space-y-3">
                <button
                  type="button"
                  class="flex w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-300 px-3 py-4 text-sm transition-colors hover:border-slate-400 hover:bg-slate-50"
                  :disabled="uploading"
                  @click="fileInput?.click()"
                >
                  <ImagePlus class="h-6 w-6 text-slate-400" />
                  <span class="font-medium text-slate-600">{{ uploading ? 'Uploading...' : 'Upload Image' }}</span>
                  <span class="text-xs text-slate-400">Click to select a file</span>
                </button>
                <button
                  type="button"
                  class="w-full text-center text-xs font-medium text-violet-600 transition-colors hover:text-violet-700"
                  @click="showMediaPicker = !showMediaPicker"
                >
                  {{ showMediaPicker ? 'Hide media library' : 'Choose from media library' }}
                </button>
              </div>

              <!-- Media library picker -->
              <div v-if="showMediaPicker" class="mt-3 border-t border-slate-100 pt-3">
                <div v-if="imageMediaItems.length === 0" class="text-center text-xs text-slate-400">No images in library.</div>
                <div v-else class="grid max-h-52 grid-cols-3 gap-1.5 overflow-y-auto">
                  <button
                    v-for="m in imageMediaItems"
                    :key="m.id"
                    type="button"
                    class="group relative aspect-square overflow-hidden rounded-md border-2 transition-all"
                    :class="featuredImageId === m.id ? 'border-violet-500 ring-2 ring-violet-200' : 'border-transparent hover:border-slate-300'"
                    @click="featuredImageId = m.id; showMediaPicker = false"
                  >
                    <img :src="resolveUrl(m.url)" :alt="m.originalName" class="h-full w-full object-cover" />
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
