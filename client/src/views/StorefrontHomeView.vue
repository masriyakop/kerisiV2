<script setup lang="ts">
import { onMounted, ref } from "vue";

import { getPublicFrontPage, getPublicSiteSettings } from "@/api/cms";
import StorefrontLayout from "@/layouts/StorefrontLayout.vue";
import type { Page, PublicSiteSettings } from "@/types";

const loading = ref(true);
const error = ref("");
const site = ref<PublicSiteSettings | null>(null);
const page = ref<Page | null>(null);

onMounted(async () => {
  loading.value = true;
  error.value = "";
  try {
    const [siteResponse, pageResponse] = await Promise.all([getPublicSiteSettings(), getPublicFrontPage()]);
    site.value = siteResponse.data;
    page.value = pageResponse.data;
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to load Webfront";
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <StorefrontLayout :site="site" :page="page" :loading="loading" :error="error" />
</template>
