<script setup lang="ts">
import { computed } from "vue";
import { API_BASE_URL } from "@/env";
import type { Page, PublicSiteSettings, StorefrontMenuItem } from "@/types";
import { markdownToSafeHtml } from "@/utils/markdown";

const props = defineProps<{
  site: PublicSiteSettings | null;
  page: Page | null;
  loading: boolean;
  error: string;
}>();

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

const renderedContent = computed(() => markdownToSafeHtml(props.page?.content || ""));

function isExternal(href: string) {
  return href.startsWith("http://") || href.startsWith("https://");
}

const menuTree = computed(() => {
  const list = props.site?.storefrontMenu || [];
  const map = new Map<string, StorefrontMenuItem[]>();
  for (const item of list) {
    const key = item.parentId ?? "__root__";
    const bucket = map.get(key) || [];
    bucket.push(item);
    map.set(key, bucket);
  }
  return {
    roots: map.get("__root__") || [],
    childrenByParent: map,
  };
});
</script>

<template>
  <div class="min-h-screen bg-slate-100 text-slate-900">
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
      <div class="mx-auto flex w-full max-w-4xl items-center justify-between px-4 py-4">
        <div class="flex items-center gap-3">
          <div v-if="site?.webfrontLogoUrl || site?.siteIconUrl" class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white">
            <img :src="resolveUrl(site?.webfrontLogoUrl || site?.siteIconUrl || '')" alt="Webfront logo" class="h-full w-full object-contain" />
          </div>
          <div>
            <p class="text-lg font-semibold">{{ site?.webfrontTitle || site?.siteTitle || "Webfront" }}</p>
            <p v-if="site?.webfrontTagline || site?.tagline" class="text-sm text-slate-500">{{ site?.webfrontTagline || site?.tagline }}</p>
          </div>
        </div>
      </div>
      <div v-if="menuTree.roots.length" class="border-t border-slate-100 bg-white">
        <nav class="mx-auto flex w-full max-w-4xl flex-wrap items-center gap-1 px-4 py-2">
          <template v-for="item in menuTree.roots" :key="item.id">
            <div class="group relative">
              <a
                v-if="isExternal(item.href)"
                :href="item.href"
                class="block rounded-md px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                :target="item.openInNewTab ? '_blank' : '_self'"
                :rel="item.openInNewTab ? 'noopener noreferrer' : undefined"
              >
                {{ item.label }}
              </a>
              <router-link
                v-else
                :to="item.href"
                class="block rounded-md px-3 py-1.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                :target="item.openInNewTab ? '_blank' : '_self'"
              >
                {{ item.label }}
              </router-link>

              <div
                v-if="(menuTree.childrenByParent.get(item.id) || []).length"
                class="invisible absolute left-0 top-full z-20 min-w-[200px] rounded-lg border border-slate-200 bg-white p-1 opacity-0 shadow-lg transition-all group-hover:visible group-hover:opacity-100"
              >
                <template v-for="child in menuTree.childrenByParent.get(item.id) || []" :key="child.id">
                  <a
                    v-if="isExternal(child.href)"
                    :href="child.href"
                    class="block rounded-md px-3 py-2 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    :target="child.openInNewTab ? '_blank' : '_self'"
                    :rel="child.openInNewTab ? 'noopener noreferrer' : undefined"
                  >
                    {{ child.label }}
                  </a>
                  <router-link
                    v-else
                    :to="child.href"
                    class="block rounded-md px-3 py-2 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                    :target="child.openInNewTab ? '_blank' : '_self'"
                  >
                    {{ child.label }}
                  </router-link>
                </template>
              </div>
            </div>
          </template>
        </nav>
      </div>
    </header>

    <main class="mx-auto w-full max-w-4xl px-4 py-8">
      <div v-if="loading" class="rounded-lg border border-slate-200 bg-white p-6 text-slate-500">Loading page...</div>
      <div v-else-if="error" class="rounded-lg border border-rose-200 bg-rose-50 p-6 text-rose-700">
        <p>{{ error }}</p>
        <p v-if="error === 'No published page yet'" class="mt-3 text-sm text-slate-600">
          <router-link to="/admin/login" class="font-medium text-violet-700 underline hover:text-violet-900">Open admin login</router-link>
          to create and publish a page, or run
          <code class="rounded bg-white/80 px-1.5 py-0.5 text-xs text-slate-800">php artisan db:seed --class=PageSeeder</code>
          for a sample home page.
        </p>
      </div>
      <article v-else-if="page" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <img
          v-if="page.featuredImage?.url"
          :src="resolveUrl(page.featuredImage.url)"
          :alt="page.featuredImage.altText || page.featuredImage.originalName"
          class="h-56 w-full object-cover"
        />
        <div class="space-y-5 p-6">
          <h1 class="text-3xl font-bold tracking-tight">{{ page.title }}</h1>
          <div class="storefront-markdown" v-html="renderedContent" />
        </div>
      </article>
    </main>

    <footer class="border-t border-slate-200 bg-white">
      <div class="mx-auto w-full max-w-4xl px-4 py-4 text-sm text-slate-500">
        {{ site?.footerText || "Powered by CORRAD+ CMS" }}
      </div>
    </footer>
  </div>
</template>

<style scoped>
.storefront-markdown :deep(h1),
.storefront-markdown :deep(h2),
.storefront-markdown :deep(h3) {
  margin-top: 1rem;
  margin-bottom: 0.5rem;
  font-weight: 700;
  color: rgb(15 23 42);
}

.storefront-markdown :deep(p) {
  margin: 0.6rem 0;
  color: rgb(51 65 85);
  line-height: 1.7;
}

.storefront-markdown :deep(ul),
.storefront-markdown :deep(ol) {
  margin: 0.6rem 0;
  padding-left: 1.25rem;
  color: rgb(51 65 85);
}

.storefront-markdown :deep(code) {
  border-radius: 0.25rem;
  background: rgb(241 245 249);
  padding: 0.1rem 0.3rem;
  font-size: 0.9em;
}

.storefront-markdown :deep(pre) {
  overflow-x: auto;
  border-radius: 0.5rem;
  background: rgb(15 23 42);
  color: rgb(241 245 249);
  padding: 0.75rem;
}

.storefront-markdown :deep(a) {
  color: rgb(124 58 237);
  text-decoration: underline;
}

.storefront-markdown :deep(blockquote) {
  margin: 0.7rem 0;
  border-left: 3px solid rgb(203 213 225);
  padding-left: 0.75rem;
  color: rgb(71 85 105);
}
</style>
