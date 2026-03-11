<script setup lang="ts">
import { computed, ref } from "vue";
import {
  FileText,
  TextCursorInput,
  FolderOpen,
  Image,
  Save,
  CheckCircle2,
  XCircle,
  Eye,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";

const form = ref({
  title: "",
  slug: "",
  excerpt: "",
  category: "guides",
  status: "draft",
  publishNow: false,
  commentsEnabled: true,
  featuredImage: "",
  seoTitle: "",
  seoDescription: "",
});
const toast = useToast();

const touched = ref<{ title: boolean; slug: boolean }>({
  title: false,
  slug: false,
});

const titleError = computed(() => {
  if (!touched.value.title) return "";
  return form.value.title.trim().length < 3 ? "Title must be at least 3 characters." : "";
});

const slugError = computed(() => {
  if (!touched.value.slug) return "";
  return !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(form.value.slug)
    ? "Slug must be lowercase kebab-case."
    : "";
});

function autoSlug() {
  form.value.slug = form.value.title
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function submit() {
  touched.value.title = true;
  touched.value.slug = true;
  if (titleError.value || slugError.value) return;
  toast.success("Form submitted", "Demo action completed.");
}

const sectionLinks = [
  { id: "basic-fields", label: "Basic Fields", icon: TextCursorInput },
  { id: "categorization", label: "Categorization", icon: FolderOpen },
  { id: "media-seo", label: "Media & SEO", icon: Image },
  { id: "actions", label: "Form Actions", icon: Save },
];
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Kitchen Sink: Forms</h1>
        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ sectionLinks.length }} Sections</span>
      </div>

      <!-- ───── Quick Jump Nav ───── -->
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Jump</p>
        <div class="grid gap-1.5 sm:grid-cols-2 md:grid-cols-4">
          <a
            v-for="link in sectionLinks"
            :key="link.id"
            :href="`#${link.id}`"
            class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900"
          >
            <component :is="link.icon" class="h-4 w-4 text-slate-400 transition-colors group-hover:text-slate-600" />
            {{ link.label }}
          </a>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="space-y-4">
          <!-- ═══════ BASIC FIELDS ═══════ -->
          <article id="basic-fields" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <TextCursorInput class="h-4 w-4 text-amber-600" />
              <h2 class="text-sm font-semibold text-slate-900">Basic Fields</h2>
            </div>
            <div class="p-4">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Title</label>
                  <input
                    v-model="form.title"
                    class="w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-200"
                    :class="titleError ? 'border-2 border-red-300 bg-red-50' : 'border-slate-300 focus:border-slate-400'"
                    placeholder="How to set up CMS"
                    @blur="touched.title = true"
                  />
                  <p v-if="titleError" class="text-xs text-red-500">{{ titleError }}</p>
                </div>

                <div class="space-y-1.5">
                  <div class="flex items-center justify-between">
                    <label class="text-sm font-medium text-slate-700">Slug</label>
                    <button class="text-xs font-medium text-slate-500 underline underline-offset-4 transition-colors hover:text-slate-900" type="button" @click="autoSlug">Auto generate</button>
                  </div>
                  <input
                    v-model="form.slug"
                    class="w-full rounded-lg border px-3 py-2 text-sm shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-slate-200"
                    :class="slugError ? 'border-2 border-red-300 bg-red-50' : 'border-slate-300 focus:border-slate-400'"
                    placeholder="how-to-set-up-cms"
                    @blur="touched.slug = true"
                  />
                  <p v-if="slugError" class="text-xs text-red-500">{{ slugError }}</p>
                </div>

                <div class="space-y-1.5 md:col-span-2">
                  <label class="text-sm font-medium text-slate-700">Excerpt</label>
                  <textarea
                    v-model="form.excerpt"
                    rows="3"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    placeholder="Short summary for list cards"
                  />
                </div>
              </div>
              <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                  <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                  <div class="text-sm text-emerald-900">
                    <p class="mb-1 font-semibold">Do</p>
                    <p>Show validation on blur, not on every keystroke.</p>
                  </div>
                </div>
                <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                  <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                  <div class="text-sm text-amber-900">
                    <p class="mb-1 font-semibold">Don&apos;t</p>
                    <p>Do not block submission silently; always surface field errors.</p>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- ═══════ CATEGORIZATION & PUBLISH ═══════ -->
          <article id="categorization" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <FolderOpen class="h-4 w-4 text-cyan-600" />
              <h2 class="text-sm font-semibold text-slate-900">Categorization & Publish</h2>
            </div>
            <div class="p-4">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Category</label>
                  <select v-model="form.category" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="guides">Guides</option>
                    <option value="announcements">Announcements</option>
                    <option value="release-notes">Release Notes</option>
                  </select>
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Status</label>
                  <select v-model="form.status" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
                <div class="space-y-3 md:col-span-2">
                  <label class="flex items-center gap-2.5 text-sm text-slate-700">
                    <input v-model="form.publishNow" type="checkbox" class="rounded" />
                    Publish immediately
                  </label>
                  <label class="flex items-center gap-2.5 text-sm text-slate-700">
                    <input v-model="form.commentsEnabled" type="checkbox" class="rounded" />
                    Enable comments
                  </label>
                </div>
              </div>
              <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                  <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                  <div class="text-sm text-emerald-900">
                    <p class="mb-1 font-semibold">Do</p>
                    <p>Pre-select sensible defaults for status and category.</p>
                  </div>
                </div>
                <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                  <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                  <div class="text-sm text-amber-900">
                    <p class="mb-1 font-semibold">Don&apos;t</p>
                    <p>Do not auto-publish without explicit user confirmation.</p>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <!-- ═══════ MEDIA & SEO ═══════ -->
          <article id="media-seo" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <Image class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">Media & SEO</h2>
            </div>
            <div class="p-4">
              <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Featured Image URL</label>
                  <input
                    v-model="form.featuredImage"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    placeholder="https://.../cover.png"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Upload file</label>
                  <input type="file" class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm shadow-sm file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-1 file:text-xs file:font-medium file:text-slate-700" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">SEO Title</label>
                  <input
                    v-model="form.seoTitle"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    placeholder="SEO optimized title"
                  />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">SEO Description</label>
                  <textarea
                    v-model="form.seoDescription"
                    rows="2"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                    placeholder="Meta description"
                  />
                </div>
              </div>
            </div>
          </article>

          <!-- ═══════ FORM ACTIONS ═══════ -->
          <article id="actions" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
              <Save class="h-4 w-4 text-blue-600" />
              <h2 class="text-sm font-semibold text-slate-900">Form Actions</h2>
            </div>
            <div class="p-4">
              <div class="flex flex-wrap items-center gap-3">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="submit">
                  Save Changes
                </button>
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50">Save Draft</button>
                <button class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 shadow-sm transition-colors hover:bg-red-50">Delete</button>
              </div>
              <div class="mt-5 grid gap-4 md:grid-cols-2">
                <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                  <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                  <div class="text-sm text-emerald-900">
                    <p class="mb-1 font-semibold">Do</p>
                    <p>Place the primary action first and destructive actions last.</p>
                  </div>
                </div>
                <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                  <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                  <div class="text-sm text-amber-900">
                    <p class="mb-1 font-semibold">Don&apos;t</p>
                    <p>Do not use multiple primary-styled buttons in the same action bar.</p>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </section>

        <!-- ───── Live Preview Sidebar ───── -->
        <aside class="space-y-4">
          <article class="sticky top-6 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-2.5">
              <div class="flex items-center gap-2">
                <Eye class="h-4 w-4 text-slate-400" />
                <h3 class="text-sm font-semibold">Live Preview</h3>
              </div>
            </div>
            <div class="space-y-3 p-3 text-sm">
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Title</p>
                <p class="mt-1 font-medium text-slate-900">{{ form.title || '-' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Slug</p>
                <p class="mt-1 font-mono text-sm text-slate-700">{{ form.slug || '-' }}</p>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status</p>
                <div class="mt-1.5">
                  <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="{
                      'bg-slate-200 text-slate-700': form.status === 'draft',
                      'bg-slate-900 text-white': form.status === 'published',
                      'border border-slate-300 text-slate-700': form.status === 'archived',
                    }"
                  >{{ form.status }}</span>
                </div>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Category</p>
                <p class="mt-1 font-medium text-slate-900">{{ form.category }}</p>
              </div>
            </div>
          </article>
        </aside>
      </div>
    </div>
  </AdminLayout>
</template>
