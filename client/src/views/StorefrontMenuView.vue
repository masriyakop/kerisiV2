<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Check, ChevronDown, ChevronUp, Link2, Plus, Save, Trash2 } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { getStorefrontMenu, listPages, saveStorefrontMenu } from "@/api/cms";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import type { Page, StorefrontMenuItem } from "@/types";

const saving = ref(false);
const saved = ref(false);
const error = ref("");
const items = ref<StorefrontMenuItem[]>([]);
const pages = ref<Page[]>([]);
const confirmDialog = useConfirmDialog();
const toast = useToast();

function createId() {
  return `menu_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
}

onMounted(async () => {
  const [menuResult, pagesResult] = await Promise.allSettled([
    getStorefrontMenu(),
    listPages("?status=published&page=1&limit=200&sortBy=title&sortDir=asc"),
  ]);
  items.value = menuResult.status === "fulfilled" ? menuResult.value.data : [];
  pages.value = pagesResult.status === "fulfilled" ? pagesResult.value.data : [];
});

function addItem() {
  items.value.push({ id: createId(), label: "", href: "/", parentId: null, openInNewTab: false });
}

async function removeItem(index: number) {
  const allowed = await confirmDialog.confirm({
    title: "Remove menu item?",
    message: "This removes the item from the current draft list.",
    confirmText: "Remove",
    destructive: true,
  });
  if (!allowed) return;
  const id = items.value[index]?.id;
  if (!id) return;
  items.value.splice(index, 1);
  for (const item of items.value) {
    if (item.parentId === id) item.parentId = null;
  }
  toast.info("Menu item removed");
}

function moveItem(index: number, direction: "up" | "down") {
  const swapIndex = direction === "up" ? index - 1 : index + 1;
  if (swapIndex < 0 || swapIndex >= items.value.length) return;
  [items.value[index], items.value[swapIndex]] = [items.value[swapIndex], items.value[index]];
}

function pageSelectValue(item: StorefrontMenuItem) {
  if (item.href === "/") return "__home__";
  const match = item.href.match(/^\/([^/]+)$/);
  return match ? match[1] : "";
}

function setItemPage(item: StorefrontMenuItem, value: string) {
  if (!value) {
    item.href = "";
    return;
  }
  if (value === "__home__") {
    item.href = "/";
    if (!item.label.trim()) item.label = "Home";
    return;
  }
  const page = pages.value.find((p) => p.slug === value);
  if (!page) return;
  item.href = `/${page.slug}`;
  if (!item.label.trim()) item.label = page.title;
}

const parentOptions = computed(() => items.value.map((item) => ({ id: item.id, label: item.label || item.href || item.id })));

function normalize(itemsInput: StorefrontMenuItem[]) {
  const prepared = itemsInput
    .map((item) => ({
      id: item.id?.trim() || createId(),
      label: item.label.trim(),
      href: item.href.trim(),
      parentId: item.parentId || null,
      openInNewTab: Boolean(item.openInNewTab),
    }))
    .filter((item) => item.label.length > 0 && item.href.length > 0);

  const idSet = new Set(prepared.map((item) => item.id));
  return prepared.map((item) => ({
    ...item,
    parentId: item.parentId && idSet.has(item.parentId) && item.parentId !== item.id ? item.parentId : null,
  }));
}

async function save() {
  saving.value = true;
  saved.value = false;
  error.value = "";
  try {
    const response = await saveStorefrontMenu(normalize(items.value));
    items.value = response.data;
    saved.value = true;
    toast.success("Storefront menu saved");
    setTimeout(() => { saved.value = false; }, 2000);
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Failed to save menus";
    toast.error("Save failed", error.value);
  } finally {
    saving.value = false;
  }
}
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Menus</h1>
        <div class="flex items-center gap-2">
          <button
            class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            @click="addItem"
          >
            <Plus class="h-4 w-4" />
            Add Item
          </button>
          <button
            class="flex items-center gap-1.5 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white transition-colors hover:bg-slate-800 disabled:opacity-50"
            :disabled="saving"
            @click="save"
          >
            <Check v-if="saved" class="h-4 w-4" />
            <Save v-else class="h-4 w-4" />
            {{ saved ? "Saved" : saving ? "Saving..." : "Save Menus" }}
          </button>
        </div>
      </div>

      <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Link2 class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Header Menu Structure</h2>
        </div>
        <div class="space-y-2 p-3">
          <p v-if="items.length === 0" class="text-sm text-slate-400">No menu items configured yet.</p>
          <div
            v-for="(item, index) in items"
            :key="item.id"
            class="grid gap-2 rounded-lg border border-slate-200 p-3 md:grid-cols-[1.2fr_1.5fr_1fr_auto_auto]"
          >
            <input
              v-model="item.label"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
              placeholder="Label (optional)"
            />
            <select
              :value="pageSelectValue(item)"
              @change="setItemPage(item, ($event.target as HTMLSelectElement).value)"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
            >
              <option value="">Select page</option>
              <option value="__home__">Home (/)</option>
              <option v-for="page in pages" :key="page.id" :value="page.slug">
                {{ page.title }} (/{{ page.slug }})
              </option>
            </select>
            <select
              v-model="item.parentId"
              class="rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200"
            >
              <option :value="null">Top Level</option>
              <option
                v-for="parent in parentOptions.filter((p) => p.id !== item.id)"
                :key="parent.id"
                :value="parent.id"
              >
                Child of: {{ parent.label }}
              </option>
            </select>
            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-600">
              <input v-model="item.openInNewTab" type="checkbox" class="h-3.5 w-3.5 rounded border-slate-300" />
              New Tab
            </label>
            <div class="flex items-center gap-1">
              <button
                class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="index === 0"
                @click="moveItem(index, 'up')"
              >
                <ChevronUp class="h-4 w-4" />
              </button>
              <button
                class="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="index === items.length - 1"
                @click="moveItem(index, 'down')"
              >
                <ChevronDown class="h-4 w-4" />
              </button>
              <button
                class="flex h-8 w-8 items-center justify-center rounded-md text-rose-500 transition-colors hover:bg-rose-50"
                @click="removeItem(index)"
              >
                <Trash2 class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
