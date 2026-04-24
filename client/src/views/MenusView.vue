<script setup lang="ts">
import { onMounted, ref } from "vue";
import {
  Menu,
  ChevronUp,
  ChevronDown,
  Save,
  RotateCcw,
  Check,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { DEFAULT_MENU, type AdminMenuPrefs } from "@/config/admin-menu";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useToast } from "@/composables/useToast";
import { useMenuStore } from "@/stores/menu";

const menuStore = useMenuStore();
const confirmDialog = useConfirmDialog();
const toast = useToast();
const saving = ref(false);
const saved = ref(false);
const error = ref("");

function buildDefaultPrefs(): AdminMenuPrefs {
  return {
    groupOrder: DEFAULT_MENU.map((group) => group.id),
    itemOrder: Object.fromEntries(DEFAULT_MENU.map((group) => [group.id, group.items.map((item) => item.id)])),
    childOrder: Object.fromEntries(
      DEFAULT_MENU.flatMap((group) =>
        group.items
          .filter((item) => item.children && item.children.length > 0)
          .map((item) => [item.id, (item.children || []).map((child) => child.id)]),
      ),
    ),
    grandchildOrder: Object.fromEntries(
      DEFAULT_MENU.flatMap((group) =>
        group.items.flatMap((item) =>
          (item.children || [])
            .filter((child) => child.children && child.children.length > 0)
            .map((child) => [child.id, (child.children || []).map((grandchild) => grandchild.id)]),
        ),
      ),
    ),
    hidden: [],
    hiddenChildren: [],
    hiddenGrandchildren: [],
    hiddenGroups: [],
  };
}

const localPrefs = ref<AdminMenuPrefs>(buildDefaultPrefs());

function mergeWithDefaults(prefs: AdminMenuPrefs): AdminMenuPrefs {
  const defaults = buildDefaultPrefs();

  const groupOrder = [...prefs.groupOrder];
  for (const groupId of defaults.groupOrder) {
    if (!groupOrder.includes(groupId)) groupOrder.push(groupId);
  }

  const itemOrder: Record<string, string[]> = {};
  for (const group of DEFAULT_MENU) {
    const saved = prefs.itemOrder[group.id] || [];
    const merged = [...saved];
    for (const item of group.items) {
      if (!merged.includes(item.id)) merged.push(item.id);
    }
    itemOrder[group.id] = merged.filter((id) => group.items.some((item) => item.id === id));
  }

  const childOrder: Record<string, string[]> = {};
  for (const group of DEFAULT_MENU) {
    for (const item of group.items) {
      if (!item.children || item.children.length === 0) continue;
      const saved = prefs.childOrder[item.id] || [];
      const merged = [...saved];
      for (const child of item.children) {
        if (!merged.includes(child.id)) merged.push(child.id);
      }
      childOrder[item.id] = merged.filter((id) => item.children?.some((child) => child.id === id));
    }
  }

  const grandchildOrder: Record<string, string[]> = {};
  for (const group of DEFAULT_MENU) {
    for (const item of group.items) {
      for (const child of item.children || []) {
        if (!child.children || child.children.length === 0) continue;
        const saved = prefs.grandchildOrder[child.id] || [];
        const merged = [...saved];
        for (const grandchild of child.children) {
          if (!merged.includes(grandchild.id)) merged.push(grandchild.id);
        }
        grandchildOrder[child.id] = merged.filter((id) => child.children?.some((node) => node.id === id));
      }
    }
  }

  const allItemIds = new Set(DEFAULT_MENU.flatMap((group) => group.items.map((item) => item.id)));
  const allChildIds = new Set(DEFAULT_MENU.flatMap((group) => group.items.flatMap((item) => (item.children || []).map((child) => child.id))));
  const allGrandchildIds = new Set(
    DEFAULT_MENU.flatMap((group) => group.items.flatMap((item) => (item.children || []).flatMap((child) => (child.children || []).map((node) => node.id)))),
  );

  return {
    groupOrder: groupOrder.filter((groupId) => defaults.groupOrder.includes(groupId)),
    itemOrder,
    childOrder,
    grandchildOrder,
    hidden: prefs.hidden.filter((id) => allItemIds.has(id)),
    hiddenChildren: (prefs.hiddenChildren || []).filter((id) => allChildIds.has(id)),
    hiddenGrandchildren: (prefs.hiddenGrandchildren || []).filter((id) => allGrandchildIds.has(id)),
    hiddenGroups: (prefs.hiddenGroups || []).filter((groupId) => defaults.groupOrder.includes(groupId)),
  };
}

onMounted(async () => {
  await menuStore.load();
  if (menuStore.prefs) {
    localPrefs.value = mergeWithDefaults(menuStore.prefs);
  }
});

function getGroupLabel(groupId: string): string {
  return DEFAULT_MENU.find((group) => group.id === groupId)?.label ?? groupId;
}

function getItemDef(groupId: string, itemId: string) {
  return DEFAULT_MENU.find((group) => group.id === groupId)?.items.find((item) => item.id === itemId);
}

function getChildDef(groupId: string, itemId: string, childId: string) {
  return getItemDef(groupId, itemId)?.children?.find((child) => child.id === childId);
}

function getGrandchildDef(groupId: string, itemId: string, childId: string, grandchildId: string) {
  return getChildDef(groupId, itemId, childId)?.children?.find((child) => child.id === grandchildId);
}

function isGroupHidden(groupId: string): boolean {
  return localPrefs.value.hiddenGroups.includes(groupId);
}

function isItemHidden(itemId: string): boolean {
  return localPrefs.value.hidden.includes(itemId);
}

function isChildHidden(childId: string): boolean {
  return localPrefs.value.hiddenChildren.includes(childId);
}

function isGrandchildHidden(grandchildId: string): boolean {
  return localPrefs.value.hiddenGrandchildren.includes(grandchildId);
}

function moveInArray(arr: string[] | undefined, id: string, direction: "up" | "down") {
  if (!arr) return;
  const idx = arr.indexOf(id);
  const swapIdx = direction === "up" ? idx - 1 : idx + 1;
  if (idx < 0 || swapIdx < 0 || swapIdx >= arr.length) return;
  [arr[idx], arr[swapIdx]] = [arr[swapIdx], arr[idx]];
}

function moveGroup(groupId: string, direction: "up" | "down") {
  moveInArray(localPrefs.value.groupOrder, groupId, direction);
}

function moveItem(groupId: string, itemId: string, direction: "up" | "down") {
  moveInArray(localPrefs.value.itemOrder[groupId], itemId, direction);
}

function moveChild(itemId: string, childId: string, direction: "up" | "down") {
  moveInArray(localPrefs.value.childOrder[itemId], childId, direction);
}

function moveGrandchild(childId: string, grandchildId: string, direction: "up" | "down") {
  moveInArray(localPrefs.value.grandchildOrder[childId], grandchildId, direction);
}

async function toggleGroupVisibility(groupId: string) {
  const hiding = !isGroupHidden(groupId);
  const allowed = await confirmDialog.confirm({
    title: hiding ? "Hide group?" : "Show group?",
    message: hiding
      ? `This group will be hidden in the sidebar until enabled again.`
      : `This group will be visible in the sidebar again.`,
    confirmText: hiding ? "Hide" : "Show",
    destructive: hiding,
  });
  if (!allowed) return;

  const idx = localPrefs.value.hiddenGroups.indexOf(groupId);
  if (idx >= 0) localPrefs.value.hiddenGroups.splice(idx, 1);
  else localPrefs.value.hiddenGroups.push(groupId);

  toast.info(hiding ? "Group hidden" : "Group shown");
}

async function toggleItemVisibility(itemId: string) {
  const hiding = !isItemHidden(itemId);
  const allowed = await confirmDialog.confirm({
    title: hiding ? "Hide menu item?" : "Show menu item?",
    message: hiding ? "This item will disappear from the sidebar." : "This item will be visible again.",
    confirmText: hiding ? "Hide" : "Show",
    destructive: hiding,
  });
  if (!allowed) return;

  const idx = localPrefs.value.hidden.indexOf(itemId);
  if (idx >= 0) localPrefs.value.hidden.splice(idx, 1);
  else localPrefs.value.hidden.push(itemId);

  toast.info(hiding ? "Item hidden" : "Item shown");
}

async function toggleChildVisibility(childId: string) {
  const hiding = !isChildHidden(childId);
  const allowed = await confirmDialog.confirm({
    title: hiding ? "Hide submenu item?" : "Show submenu item?",
    message: hiding ? "This submenu item will be hidden." : "This submenu item will be visible again.",
    confirmText: hiding ? "Hide" : "Show",
    destructive: hiding,
  });
  if (!allowed) return;

  const idx = localPrefs.value.hiddenChildren.indexOf(childId);
  if (idx >= 0) localPrefs.value.hiddenChildren.splice(idx, 1);
  else localPrefs.value.hiddenChildren.push(childId);

  toast.info(hiding ? "Submenu hidden" : "Submenu shown");
}

async function toggleGrandchildVisibility(grandchildId: string) {
  const hiding = !isGrandchildHidden(grandchildId);
  const allowed = await confirmDialog.confirm({
    title: hiding ? "Hide second-level submenu?" : "Show second-level submenu?",
    message: hiding ? "This nested submenu item will be hidden." : "This nested submenu item will be visible again.",
    confirmText: hiding ? "Hide" : "Show",
    destructive: hiding,
  });
  if (!allowed) return;

  const idx = localPrefs.value.hiddenGrandchildren.indexOf(grandchildId);
  if (idx >= 0) localPrefs.value.hiddenGrandchildren.splice(idx, 1);
  else localPrefs.value.hiddenGrandchildren.push(grandchildId);

  toast.info(hiding ? "Nested submenu hidden" : "Nested submenu shown");
}

async function save() {
  saving.value = true;
  saved.value = false;
  error.value = "";
  try {
    await menuStore.save(localPrefs.value);
    saved.value = true;
    toast.success("Menu configuration saved");
    setTimeout(() => {
      saved.value = false;
    }, 2000);
  } catch (e) {
    const message = e instanceof Error ? e.message : "Failed to save";
    error.value = message;
    toast.error("Save failed", message);
  } finally {
    saving.value = false;
  }
}

async function resetToDefaults() {
  const allowed = await confirmDialog.confirm({
    title: "Reset menu configuration?",
    message: "This resets all ordering and visibility changes to defaults.",
    confirmText: "Reset",
    destructive: true,
  });
  if (!allowed) return;

  localPrefs.value = buildDefaultPrefs();
  toast.info("Menu configuration reset");
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Menu Configuration</h1>
        <div class="flex items-center gap-2">
          <button
            class="flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50"
            @click="resetToDefaults"
          >
            <RotateCcw class="h-4 w-4" />
            Reset
          </button>
          <button
            class="flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800 disabled:opacity-50"
            :disabled="saving"
            @click="save"
          >
            <Check v-if="saved" class="h-4 w-4" />
            <Save v-else class="h-4 w-4" />
            {{ saved ? 'Saved!' : saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
      <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>

      <div class="space-y-4">
        <div
          v-for="(groupId, groupIndex) in localPrefs.groupOrder"
          :key="groupId"
          class="rounded-lg border border-slate-200 bg-white shadow-sm transition-opacity"
          :class="{ 'opacity-50': isGroupHidden(groupId) }"
        >
          <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5">
            <div class="flex items-center gap-2">
              <Menu class="h-4 w-4 text-violet-600" />
              <h2 class="text-sm font-semibold text-slate-900">{{ getGroupLabel(groupId) }}</h2>
            </div>
            <div class="flex items-center gap-2">
              <button
                class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors"
                :class="isGroupHidden(groupId) ? 'bg-slate-400' : 'bg-violet-600'"
                @click="toggleGroupVisibility(groupId)"
              >
                <span
                  class="inline-block h-4 w-4 rounded-full bg-white shadow-sm transition-transform"
                  :class="isGroupHidden(groupId) ? 'translate-x-[2px]' : 'translate-x-[18px]'"
                />
              </button>
              <button
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="groupIndex === 0"
                @click="moveGroup(groupId, 'up')"
              >
                <ChevronUp class="h-4 w-4" />
              </button>
              <button
                class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                :disabled="groupIndex === localPrefs.groupOrder.length - 1"
                @click="moveGroup(groupId, 'down')"
              >
                <ChevronDown class="h-4 w-4" />
              </button>
            </div>
          </div>

          <div class="space-y-1 p-2">
            <div
              v-for="(itemId, itemIndex) in (localPrefs.itemOrder[groupId] || [])"
              :key="itemId"
              class="rounded-md border border-slate-100"
              :class="{ 'opacity-40': isItemHidden(itemId) }"
            >
              <div class="flex items-center gap-3 px-3 py-2 transition-colors hover:bg-slate-50">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100">
                  <component :is="getItemDef(groupId, itemId)?.icon" class="h-3.5 w-3.5 text-slate-500" />
                </div>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-medium text-slate-900">{{ getItemDef(groupId, itemId)?.label }}</p>
                  <p class="truncate text-xs text-slate-400">{{ getItemDef(groupId, itemId)?.to }}</p>
                </div>
                <button
                  class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer items-center rounded-full transition-colors"
                  :class="isItemHidden(itemId) ? 'bg-slate-400' : 'bg-violet-600'"
                  @click="toggleItemVisibility(itemId)"
                >
                  <span
                    class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                    :class="isItemHidden(itemId) ? 'translate-x-[2px]' : 'translate-x-[14px]'"
                  />
                </button>
                <button
                  class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                  :disabled="itemIndex === 0"
                  @click="moveItem(groupId, itemId, 'up')"
                >
                  <ChevronUp class="h-3.5 w-3.5" />
                </button>
                <button
                  class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                  :disabled="itemIndex === (localPrefs.itemOrder[groupId] || []).length - 1"
                  @click="moveItem(groupId, itemId, 'down')"
                >
                  <ChevronDown class="h-3.5 w-3.5" />
                </button>
              </div>

              <div
                v-if="(localPrefs.childOrder[itemId] || []).length > 0"
                class="space-y-1 border-t border-slate-100 bg-slate-50/50 p-2"
              >
                <div
                  v-for="(childId, childIndex) in localPrefs.childOrder[itemId] || []"
                  :key="childId"
                  class="rounded-md border border-slate-100 bg-white"
                  :class="{ 'opacity-40': isChildHidden(childId) }"
                >
                  <div class="flex items-center gap-3 px-3 py-1.5">
                    <div class="h-2 w-2 rounded-full bg-slate-300" />
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-medium text-slate-800">{{ getChildDef(groupId, itemId, childId)?.label }}</p>
                      <p class="truncate text-xs text-slate-400">{{ getChildDef(groupId, itemId, childId)?.to }}</p>
                    </div>
                    <button
                      class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer items-center rounded-full transition-colors"
                      :class="isChildHidden(childId) ? 'bg-slate-400' : 'bg-violet-600'"
                      @click="toggleChildVisibility(childId)"
                    >
                      <span
                        class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                        :class="isChildHidden(childId) ? 'translate-x-[2px]' : 'translate-x-[14px]'"
                      />
                    </button>
                    <button
                      class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                      :disabled="childIndex === 0"
                      @click="moveChild(itemId, childId, 'up')"
                    >
                      <ChevronUp class="h-3.5 w-3.5" />
                    </button>
                    <button
                      class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                      :disabled="childIndex === (localPrefs.childOrder[itemId] || []).length - 1"
                      @click="moveChild(itemId, childId, 'down')"
                    >
                      <ChevronDown class="h-3.5 w-3.5" />
                    </button>
                  </div>

                  <div
                    v-if="(localPrefs.grandchildOrder[childId] || []).length > 0"
                    class="space-y-1 border-t border-slate-100 bg-slate-50 px-2 py-1.5"
                  >
                    <div
                      v-for="(grandchildId, grandchildIndex) in localPrefs.grandchildOrder[childId] || []"
                      :key="grandchildId"
                      class="flex items-center gap-3 rounded-md border border-slate-100 bg-white px-3 py-1"
                      :class="{ 'opacity-40': isGrandchildHidden(grandchildId) }"
                    >
                      <div class="h-1.5 w-1.5 rounded-full bg-slate-300" />
                      <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-medium text-slate-800">{{ getGrandchildDef(groupId, itemId, childId, grandchildId)?.label }}</p>
                        <p class="truncate text-[11px] text-slate-400">{{ getGrandchildDef(groupId, itemId, childId, grandchildId)?.to }}</p>
                      </div>
                      <button
                        class="relative inline-flex h-4 w-7 shrink-0 cursor-pointer items-center rounded-full transition-colors"
                        :class="isGrandchildHidden(grandchildId) ? 'bg-slate-400' : 'bg-violet-600'"
                        @click="toggleGrandchildVisibility(grandchildId)"
                      >
                        <span
                          class="inline-block h-3 w-3 rounded-full bg-white shadow-sm transition-transform"
                          :class="isGrandchildHidden(grandchildId) ? 'translate-x-[2px]' : 'translate-x-[14px]'"
                        />
                      </button>
                      <button
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                        :disabled="grandchildIndex === 0"
                        @click="moveGrandchild(childId, grandchildId, 'up')"
                      >
                        <ChevronUp class="h-3.5 w-3.5" />
                      </button>
                      <button
                        class="flex h-7 w-7 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700 disabled:opacity-30"
                        :disabled="grandchildIndex === (localPrefs.grandchildOrder[childId] || []).length - 1"
                        @click="moveGrandchild(childId, grandchildId, 'down')"
                      >
                        <ChevronDown class="h-3.5 w-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
