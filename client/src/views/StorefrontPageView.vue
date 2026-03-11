<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";

import { getPublicPageBySlug, getPublicSiteSettings } from "@/api/cms";
import StorefrontLayout from "@/layouts/StorefrontLayout.vue";
import type { Page, PublicSiteSettings } from "@/types";

const route = useRoute();
const loading = ref(true);
const error = ref("");
const site = ref<PublicSiteSettings | null>(null);
const page = ref<Page | null>(null);

async function load(slugValue: string) {
  loading.value = true;
  error.value = "";
  page.value = null;
  try {
    const [siteResponse, pageResponse] = await Promise.all([
      getPublicSiteSettings(),
      getPublicPageBySlug(slugValue),
    ]);
    site.value = siteResponse.data;
    page.value = pageResponse.data;
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to load page";
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  load(String(route.params.slug || ""));
});

watch(
  () => route.params.slug,
  (nextSlug) => {
    load(String(nextSlug || ""));
  },
);
</script>

<template>
  <StorefrontLayout :site="site" :page="page" :loading="loading" :error="error" />
</template>
