<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import {
  FileText,
  Image,
  ArrowRight,
  TrendingUp,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { fetchDashboardSummary } from "@/api/cms";
import type { Page, Post } from "@/types";

const router = useRouter();
const counts = ref({ posts: 0, pages: 0, media: 0 });
const recentPosts = ref<Post[]>([]);
const recentPages = ref<Page[]>([]);

onMounted(async () => {
  const response = await fetchDashboardSummary();
  counts.value = response.data.counts;
  recentPosts.value = response.data.recent.posts;
  recentPages.value = response.data.recent.pages;
});

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
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Dashboard</h1>
      </div>

      <!-- ───── Stat Cards ───── -->
      <div class="grid gap-3 sm:grid-cols-3">
        <div class="group rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-blue-100">
              <FileText class="h-3.5 w-3.5 text-blue-600" />
            </div>
            <TrendingUp class="h-3.5 w-3.5 text-emerald-500" />
          </div>
          <p class="mt-2 text-xl font-bold text-slate-900">{{ counts.posts }}</p>
          <p class="mt-0.5 text-xs text-slate-500">Total Posts</p>
        </div>
        <div class="group rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-emerald-100">
              <FileText class="h-3.5 w-3.5 text-emerald-600" />
            </div>
            <TrendingUp class="h-3.5 w-3.5 text-emerald-500" />
          </div>
          <p class="mt-2 text-xl font-bold text-slate-900">{{ counts.pages }}</p>
          <p class="mt-0.5 text-xs text-slate-500">Total Pages</p>
        </div>
        <div class="group rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition-all hover:shadow-md">
          <div class="flex items-center justify-between">
            <div class="flex h-7 w-7 items-center justify-center rounded-md bg-amber-100">
              <Image class="h-3.5 w-3.5 text-amber-600" />
            </div>
            <TrendingUp class="h-3.5 w-3.5 text-emerald-500" />
          </div>
          <p class="mt-2 text-xl font-bold text-slate-900">{{ counts.media }}</p>
          <p class="mt-0.5 text-xs text-slate-500">Media Files</p>
        </div>
      </div>

      <!-- ───── Recent Content ───── -->
      <div class="grid gap-4 md:grid-cols-2">
        <!-- Recent Posts -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <FileText class="h-4 w-4 text-blue-600" />
              <h2 class="text-sm font-semibold text-slate-900">Recent Posts</h2>
            </div>
            <button class="flex items-center gap-1 text-sm font-medium text-slate-500 transition-colors hover:text-slate-900" @click="router.push('/admin/posts')">
              View all
              <ArrowRight class="h-3.5 w-3.5" />
            </button>
          </div>
          <div class="divide-y divide-slate-100">
            <div v-for="post in recentPosts" :key="post.id" class="flex items-center justify-between px-4 py-2 transition-colors hover:bg-slate-50">
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ post.title }}</p>
                <p class="text-xs text-slate-400">{{ post.slug }}</p>
              </div>
              <span class="ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusColor(post.status)">
                {{ post.status }}
              </span>
            </div>
            <div v-if="recentPosts.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
              No posts yet.
            </div>
          </div>
        </article>

        <!-- Recent Pages -->
        <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <FileText class="h-4 w-4 text-emerald-600" />
              <h2 class="text-sm font-semibold text-slate-900">Recent Pages</h2>
            </div>
            <button class="flex items-center gap-1 text-sm font-medium text-slate-500 transition-colors hover:text-slate-900" @click="router.push('/admin/pages')">
              View all
              <ArrowRight class="h-3.5 w-3.5" />
            </button>
          </div>
          <div class="divide-y divide-slate-100">
            <div v-for="page in recentPages" :key="page.id" class="flex items-center justify-between px-4 py-2 transition-colors hover:bg-slate-50">
              <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900">{{ page.title }}</p>
                <p class="text-xs text-slate-400">{{ page.slug }}</p>
              </div>
              <span class="ml-3 shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusColor(page.status)">
                {{ page.status }}
              </span>
            </div>
            <div v-if="recentPages.length === 0" class="px-4 py-6 text-center text-sm text-slate-400">
              No pages yet.
            </div>
          </div>
        </article>
      </div>
    </div>
  </AdminLayout>
</template>
