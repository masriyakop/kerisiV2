<script setup lang="ts">
import { Construction } from "lucide-vue-next";
import { useRoute } from "vue-router";

import AdminLayout from "@/layouts/AdminLayout.vue";

const route = useRoute();

const title = (route.meta.title as string) || "Coming Soon";
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl">
      <div class="flex items-center justify-between">
        <h1 class="page-title">{{ title }}</h1>
      </div>

      <article class="mt-4 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col items-center justify-center px-4 py-16 text-center">
          <Construction class="h-12 w-12 text-slate-300" />
          <h2 class="mt-4 text-lg font-semibold text-slate-700">Under Development</h2>
          <p class="mt-1 max-w-md text-sm text-slate-400">
            This module is part of the Core Platform roadmap and will be available in a future release.
          </p>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
