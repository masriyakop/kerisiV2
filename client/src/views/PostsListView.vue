<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  FileText,
  Plus,
  Search,
  Pencil,
  Trash2,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { deletePost, listPosts } from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type { Post } from "@/types";

const router = useRouter();
const toast = useToast();
const confirmDialog = useConfirmDialog();
const rows = ref<Post[]>([]);
const q = ref("");

async function load() {
  const params = new URLSearchParams({ page: "1", limit: "20", ...(q.value ? { q: q.value } : {}) });
  const response = await listPosts(`?${params.toString()}`);
  rows.value = response.data;
}

async function remove(id: number) {
  const allowed = await confirmDialog.confirm({
    title: "Delete post?",
    message: "This action cannot be undone.",
    confirmText: "Delete",
    destructive: true,
  });
  if (!allowed) return;
  try {
    await deletePost(id);
    await load();
    toast.success("Post deleted");
  } catch (e) {
    toast.error("Delete failed", e instanceof Error ? e.message : "Unable to delete post.");
  }
}

function statusColor(status: string) {
  switch (status) {
    case "published":
      return "bg-emerald-100 text-emerald-700";
    case "draft":
      return "bg-amber-100 text-amber-700";
    case "archived":
      return "bg-slate-100 text-slate-600";
    default:
      return "bg-slate-100 text-slate-600";
  }
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Posts</h1>
        <button
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800"
          @click="router.push('/admin/posts/new')"
        >
          <Plus class="h-4 w-4" />
          Add Post
        </button>
      </div>

      <!-- ───── Table Card ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-2.5">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <FileText class="h-4 w-4 text-blue-600" />
              <h2 class="text-sm font-semibold text-slate-900">All Posts</h2>
              <span class="text-xs text-slate-500 ml-1">{{ rows.length }} post{{ rows.length !== 1 ? 's' : '' }}</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  v-model="q"
                  placeholder="Search posts..."
                  class="w-56 rounded-lg border border-slate-300 py-1.5 pl-9 pr-3 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
                  @keyup.enter="load"
                />
              </div>
              <button class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm font-medium text-slate-600 shadow-sm transition-colors hover:bg-slate-50" @click="load">Filter</button>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-slate-100 text-left">
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Title</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Categories</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                <th class="px-4 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">Slug</th>
                <th class="px-4 py-2 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="item in rows" :key="item.id" class="transition-colors hover:bg-slate-50">
                <td class="px-4 py-2 font-medium text-slate-900">{{ item.title }}</td>
                <td class="px-4 py-2">
                  <div class="flex flex-wrap gap-1">
                    <span
                      v-for="cat in (item.categories || [])"
                      :key="cat.id"
                      class="rounded-full bg-violet-100 px-2 py-0.5 text-[11px] font-medium text-violet-700"
                    >{{ cat.name }}</span>
                    <span v-if="!item.categories?.length" class="text-xs text-slate-300">—</span>
                  </div>
                </td>
                <td class="px-4 py-2">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusColor(item.status)">{{ item.status }}</span>
                </td>
                <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ item.slug }}</td>
                <td class="px-4 py-2 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      class="group relative flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                      @click="router.push(`/admin/posts/${item.id}`)"
                    >
                      <Pencil class="h-3.5 w-3.5" />
                      <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Edit</span>
                    </button>
                    <button
                      class="group relative flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600"
                      @click="remove(item.id)"
                    >
                      <Trash2 class="h-3.5 w-3.5" />
                      <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Delete</span>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="rows.length === 0">
                <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-400">No posts found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
