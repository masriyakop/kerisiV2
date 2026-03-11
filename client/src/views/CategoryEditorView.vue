<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import {
  Tag,
  Save,
  X,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { createCategory, getCategory, updateCategory } from "@/api/cms";
import { useToast } from "@/composables/useToast";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const id = computed(() => Number(route.params.id || 0));
const isEdit = computed(() => id.value > 0);

const name = ref("");
const slug = ref("");
const description = ref("");

async function load() {
  if (!isEdit.value) return;
  const response = await getCategory(id.value);
  const cat = response.data;
  name.value = cat.name;
  slug.value = cat.slug;
  description.value = cat.description || "";
}

async function save() {
  const payload = {
    name: name.value,
    slug: slug.value || undefined,
    description: description.value || undefined,
  };

  try {
    if (isEdit.value) {
      await updateCategory(id.value, payload);
      toast.success("Category updated");
    } else {
      await createCategory(payload);
      toast.success("Category created");
    }
    router.push("/admin/categories");
  } catch (e) {
    toast.error("Save failed", e instanceof Error ? e.message : "Unable to save category.");
  }
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Title ───── -->
      <h1 class="page-title">{{ isEdit ? 'Edit Category' : 'Create Category' }}</h1>

      <!-- ═══════ FORM ═══════ -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Tag class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Category Details</h2>
        </div>
        <div class="space-y-3 p-4">
          <div class="grid gap-3 md:grid-cols-2">
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-slate-700">Name</label>
              <input v-model="name" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="e.g. Technology" />
            </div>
            <div class="space-y-1.5">
              <label class="text-sm font-medium text-slate-700">Slug</label>
              <input v-model="slug" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="auto-generated-from-name" />
            </div>
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Description</label>
            <textarea v-model="description" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Brief description of this category..." />
          </div>
        </div>
      </article>

      <!-- ═══════ ACTIONS ═══════ -->
      <div class="flex items-center gap-3">
        <button
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800"
          @click="save"
        >
          <Save class="h-4 w-4" />
          {{ isEdit ? 'Update Category' : 'Create Category' }}
        </button>
        <button
          class="flex items-center gap-2 rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50"
          @click="router.push('/admin/categories')"
        >
          <X class="h-4 w-4" />
          Cancel
        </button>
      </div>
    </div>
  </AdminLayout>
</template>
