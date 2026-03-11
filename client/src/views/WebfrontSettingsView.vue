<script setup lang="ts">
import { onMounted, ref } from "vue";
import { CheckCircle2, Globe, Image, Save, Upload } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { getSettings, updateSettings, uploadMedia } from "@/api/cms";
import { useToast } from "@/composables/useToast";
import { API_BASE_URL } from "@/env";
import type { SettingsPayload } from "@/types";

const form = ref<SettingsPayload>({
  siteTitle: "",
  tagline: "",
  webfrontTitle: "",
  webfrontTagline: "",
  titleFormat: "%page% | %site%",
  metaDescription: "",
  siteIconUrl: "",
  webfrontLogoUrl: "",
  sidebarLogoUrl: "",
  faviconUrl: "",
  language: "en",
  timezone: "UTC",
  footerText: "",
  frontPageId: null,
});
const toast = useToast();

const saving = ref(false);
const saved = ref(false);
const error = ref("");
const uploadingLogo = ref(false);

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

async function load() {
  try {
    const response = await getSettings();
    form.value = response.data;
    if (!form.value.webfrontTitle) form.value.webfrontTitle = form.value.siteTitle;
    if (!form.value.webfrontTagline) form.value.webfrontTagline = form.value.tagline;
    if (!form.value.webfrontLogoUrl) form.value.webfrontLogoUrl = form.value.siteIconUrl;
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to load Webfront settings";
  }
}

async function onLogoUpload(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploadingLogo.value = true;
  error.value = "";
  try {
    const response = await uploadMedia(file);
    form.value.webfrontLogoUrl = response.data.url;
    toast.success("Logo uploaded");
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to upload logo";
    toast.error("Upload failed", error.value);
  } finally {
    uploadingLogo.value = false;
    (event.target as HTMLInputElement).value = "";
  }
}

async function save() {
  saving.value = true;
  error.value = "";
  try {
    await updateSettings(form.value);
    saved.value = true;
    toast.success("Webfront settings saved");
    setTimeout(() => { saved.value = false; }, 2000);
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : "Failed to save Webfront settings";
    toast.error("Save failed", error.value);
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Settings</h1>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Globe class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Page Identity</h2>
        </div>
        <div class="grid gap-3 p-4 md:grid-cols-2">
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Page Title</label>
            <input v-model="form.webfrontTitle" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Tagline</label>
            <input v-model="form.webfrontTagline" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" />
          </div>
        </div>
      </article>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Image class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Logo</h2>
        </div>
        <div class="space-y-3 p-4">
          <div class="flex items-center gap-4">
            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-slate-300 bg-slate-50">
              <img v-if="form.webfrontLogoUrl" :src="resolveUrl(form.webfrontLogoUrl)" alt="Webfront logo" class="h-full w-full object-contain" />
              <Image v-else class="h-8 w-8 text-slate-300" />
            </div>
            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50">
              <Upload class="h-4 w-4" />
              {{ uploadingLogo ? "Uploading..." : "Upload Logo" }}
              <input type="file" accept="image/*" class="hidden" :disabled="uploadingLogo" @change="onLogoUpload" />
            </label>
          </div>
          <div class="space-y-1.5">
            <label class="text-sm font-medium text-slate-700">Logo URL</label>
            <input v-model="form.webfrontLogoUrl" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="/uploads/your-logo.png" />
          </div>
        </div>
      </article>

      <div class="flex items-center gap-3">
        <button
          class="flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-slate-800 disabled:opacity-50"
          :disabled="saving"
          @click="save"
        >
          <Save class="h-4 w-4" />
          {{ saving ? "Saving..." : "Save Settings" }}
        </button>
        <span v-if="saved" class="flex items-center gap-1.5 text-sm font-medium text-emerald-600">
          <CheckCircle2 class="h-4 w-4" />
          Saved
        </span>
      </div>
      <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
    </div>
  </AdminLayout>
</template>
