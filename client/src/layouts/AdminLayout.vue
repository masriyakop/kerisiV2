<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { Bell, Check, ChevronDown, LogOut, Settings, Shield } from "lucide-vue-next";

import type { ThemeColor } from "@/types";
import type { MenuItemDef, MenuNode } from "@/config/admin-menu";
import { useSidebarCollapse } from "@/composables/useSidebarCollapse";
import { useToast } from "@/composables/useToast";
import AppToastRegion from "@/components/AppToastRegion.vue";

import { useAuthStore } from "@/stores/auth";
import { useMenuStore } from "@/stores/menu";
import { useSiteStore } from "@/stores/site";
import { useUiThemeStore } from "@/stores/uiTheme";
import { API_BASE_URL } from "@/env";

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const menuStore = useMenuStore();
const site = useSiteStore();
const uiTheme = useUiThemeStore();
const toast = useToast();
const { isCollapsed, isCompact, toggle: toggleSidebar, toggleCompact } = useSidebarCollapse();

const settingsOpen = ref(false);
const settingsDropdownRef = ref<HTMLElement | null>(null);

const themeChoices: Array<{ label: string; value: ThemeColor }> = [
  { label: "Violet", value: "violet" },
  { label: "Blue", value: "blue" },
  { label: "Green", value: "green" },
  { label: "Red", value: "red" },
  { label: "B&W", value: "black-white" },
  { label: "Grey", value: "grey" },
];

const handleDocumentClick = (event: MouseEvent) => {
  if (!settingsOpen.value) return;
  if (!settingsDropdownRef.value) return;
  if (settingsDropdownRef.value.contains(event.target as Node)) return;
  settingsOpen.value = false;
};

const handleEscape = (event: KeyboardEvent) => {
  if (event.key === "Escape") settingsOpen.value = false;
};

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

onMounted(() => {
  site.load();
  menuStore.load();
  document.addEventListener("click", handleDocumentClick);
  document.addEventListener("keydown", handleEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleDocumentClick);
  document.removeEventListener("keydown", handleEscape);
  if (showTitleTimer !== null) {
    window.clearTimeout(showTitleTimer);
    showTitleTimer = null;
  }
});

const openMenus = reactive<Record<string, boolean>>({});

const userInitials = computed(() => {
  if (!auth.user?.name) return "A";
  return auth.user.name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase()
    .slice(0, 2);
});

const userRoleLabel = computed(() => auth.user?.role || "Administrator");
const HEADER_TEXT_MAX = 20;

function truncateHeaderText(value: string, max = HEADER_TEXT_MAX) {
  if (!value) return "";
  return value.length > max ? `${value.slice(0, max)}...` : value;
}

/** Full site title from settings; layout uses CSS truncation so long names are not cut to 20 chars. */
const headerSiteTitle = computed(() => (site.siteTitle || "").trim());
const headerUserName = computed(() => truncateHeaderText(auth.user?.name || "Admin"));
const headerUserRole = computed(() => truncateHeaderText(userRoleLabel.value));
const hasActiveToast = computed(() => toast.toasts.value.length > 0);
const showSiteTitle = ref(true);
const TOAST_EXIT_MS = 1500;
let showTitleTimer: number | null = null;

const rowBaseClass = computed(() =>
  isCompact.value
    ? "gap-2.5 px-3 py-1 text-[13px] leading-tight"
    : "gap-2.5 px-3 py-1.5 text-sm",
);

const collapsedRowBaseClass = computed(() =>
  isCompact.value
    ? "md:justify-center md:px-0 md:py-1.5 md:rounded-none gap-2.5 px-3 py-1"
    : "md:justify-center md:px-0 md:py-2.5 md:rounded-none gap-2.5 px-3 py-1.5",
);

const childRowClass = computed(() =>
  isCompact.value
    ? "block rounded-md px-3 py-0.5 text-[13px] leading-tight transition-all hover:bg-[var(--accent-50)]"
    : "block rounded-md px-3 py-1 text-sm transition-all hover:bg-[var(--accent-50)]",
);

async function signOut() {
  try {
    await auth.signOut();
    toast.success("Signed out", "You have been logged out.");
    router.push("/admin/login");
  } catch (e) {
    toast.error("Sign out failed", e instanceof Error ? e.message : "Please try again.");
  }
}

function isActive(path: string): boolean {
  if (path === "/") return route.path === "/";
  if (path.startsWith("/admin/kerisi/m/")) return route.path === path;
  return route.path.startsWith(path);
}

function itemClass(path: string) {
  if (isActive(path)) {
    return "border border-[var(--accent-200)] bg-[var(--accent-50)] font-medium text-[var(--accent-700)]";
  }
  return "border border-transparent text-slate-900";
}

function childClass(path: string) {
  if (route.path === path) {
    return "border border-[var(--accent-200)] bg-[var(--accent-50)] font-medium text-[var(--accent-700)]";
  }
  return "border border-transparent text-slate-600";
}

function toggleMenu(id: string) {
  openMenus[id] = !openMenus[id];
}

function isNodeActive(node: { to: string; children?: MenuNode[] }): boolean {
  if (isActive(node.to)) return true;
  if (!node.children || node.children.length === 0) return false;
  return node.children.some((child) => isNodeActive(child));
}

function syncOpenMenus() {
  const syncNode = (node: MenuNode | MenuItemDef) => {
    if (node.children && node.children.length > 0 && isNodeActive(node)) {
      openMenus[node.id] = true;
      for (const child of node.children) syncNode(child);
    }
  };

  for (const group of menuStore.resolvedMenu) {
    for (const item of group.items) {
      syncNode(item);
    }
  }
}

watch(() => route.path, syncOpenMenus, { immediate: true });
watch(() => menuStore.resolvedMenu, syncOpenMenus, { deep: true });
watch(
  hasActiveToast,
  (active) => {
    if (showTitleTimer !== null) {
      window.clearTimeout(showTitleTimer);
      showTitleTimer = null;
    }
    if (active) {
      showSiteTitle.value = false;
      return;
    }
    showTitleTimer = window.setTimeout(() => {
      showSiteTitle.value = true;
      showTitleTimer = null;
    }, TOAST_EXIT_MS);
  },
  { immediate: true },
);
</script>

<template>
  <div class="min-h-screen bg-[#f8f9fb]">
    <header class="sticky top-0 z-40 flex h-10 items-center justify-between border-b border-slate-200 bg-white px-5">
      <div class="flex items-center gap-1">
        <div v-if="site.siteIconUrl" class="flex h-[18px] shrink-0 items-center justify-center overflow-hidden">
          <img :src="resolveUrl(site.siteIconUrl)" alt="Site icon" class="h-full w-auto object-contain" />
        </div>
        <div
          v-else
          class="flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-md bg-gradient-to-br from-[var(--accent-600)] to-[var(--accent-500)]"
        >
          <Shield class="h-[11px] w-[11px] text-white" />
        </div>
      </div>

      <div class="flex items-center self-stretch">
        <div
          v-if="headerSiteTitle && showSiteTitle"
          class="flex h-full min-w-0 max-w-[min(36rem,calc(100vw-14rem))] items-center overflow-hidden"
        >
          <span
            class="truncate px-4 text-sm font-light text-slate-900"
            :title="headerSiteTitle"
          >{{ headerSiteTitle }}</span>
          <span class="h-full w-px shrink-0 bg-slate-200" />
        </div>
        <AppToastRegion />

        <router-link
          :to="'/admin/settings/users/' + auth.user?.id"
          class="group relative flex h-full items-center gap-2 px-4 transition-colors hover:bg-[var(--accent-600)]"
        >
          <div
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[var(--accent-600)] to-[var(--accent-500)] text-[10px] font-semibold text-white"
          >
            {{ userInitials }}
          </div>
          <div class="leading-tight">
            <p class="text-sm font-medium text-slate-700 group-hover:text-white">{{ headerUserName }}</p>
            <p class="text-[11px] text-slate-500 group-hover:text-white/80">{{ headerUserRole }}</p>
          </div>
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Profile</span>
        </router-link>

        <span class="h-full w-px bg-slate-200" />

        <div ref="settingsDropdownRef" class="relative flex h-full items-stretch">
          <button
            class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
            @click.stop="settingsOpen = !settingsOpen"
          >
            <Settings class="h-4 w-4" />
            <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Theme settings</span>
          </button>

          <div
            v-if="settingsOpen"
            class="absolute right-0 top-full z-50 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-3 shadow-lg"
          >
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Theme color</p>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="theme in themeChoices"
                :key="theme.value"
                class="flex items-center justify-between rounded-md border px-2.5 py-2 text-xs font-medium transition-colors"
                :class="uiTheme.themeColor === theme.value
                  ? 'border-[var(--accent-500)] bg-[var(--accent-50)] text-[var(--accent-700)]'
                  : 'border-slate-200 text-slate-600 hover:border-[var(--accent-ring)] hover:text-slate-900'"
                @click="uiTheme.setThemeColor(theme.value)"
              >
                <span class="flex items-center gap-2">
                  <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="theme.value === 'violet'
                      ? 'bg-violet-500'
                      : theme.value === 'blue'
                        ? 'bg-blue-500'
                        : theme.value === 'green'
                          ? 'bg-emerald-500'
                          : theme.value === 'red'
                            ? 'bg-rose-500'
                            : theme.value === 'black-white'
                              ? 'bg-slate-900'
                              : 'bg-neutral-500'"
                  />
                  {{ theme.label }}
                </span>
                <Check v-if="uiTheme.themeColor === theme.value" class="h-3.5 w-3.5" />
              </button>
            </div>

            <div class="mt-3 border-t border-slate-200 pt-3">
              <button
                class="flex w-full items-center justify-between rounded-md border border-slate-200 px-2.5 py-2 text-xs font-medium text-slate-700 transition-colors hover:border-[var(--accent-ring)]"
                @click="toggleCompact"
              >
                <span>Compact sidebar</span>
                <span
                  class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors"
                  :class="isCompact ? 'bg-[var(--accent-600)]' : 'bg-slate-300'"
                >
                  <span
                    class="inline-block h-3 w-3 transform rounded-full bg-white transition"
                    :class="isCompact ? 'translate-x-3.5' : 'translate-x-0.5'"
                  />
                </span>
              </button>
            </div>
          </div>
        </div>

        <span class="h-full w-px bg-slate-200" />

        <button
          class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
        >
          <Bell class="h-4 w-4" />
          <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white" />
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Notifications</span>
        </button>

        <span class="h-full w-px bg-slate-200" />

        <button
          class="group relative flex h-full items-center px-4 text-slate-500 transition-colors hover:bg-[var(--accent-600)] hover:text-white"
          @click="signOut"
        >
          <LogOut class="h-4 w-4" />
          <span class="pointer-events-none absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100">Logout</span>
        </button>
      </div>
    </header>

    <div class="flex flex-col md:flex-row">
      <aside
        class="relative flex flex-col border-r border-slate-200 bg-slate-50/50 transition-[width] duration-300 ease-in-out md:min-h-[calc(100vh-40px)]"
        :class="isCollapsed ? 'w-full md:w-14' : 'w-full md:w-64'"
      >
        <button
          class="absolute -right-3.5 top-10 z-40 hidden h-7 w-7 items-center justify-center rounded-full border border-slate-200 bg-[var(--accent-600)] text-white shadow-md transition-all hover:bg-[var(--accent-700)] hover:shadow-lg md:flex"
          :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
          @click="toggleSidebar"
        >
          <ChevronDown
            class="h-4 w-4 transition-transform duration-200"
            :class="isCollapsed ? '-rotate-90' : 'rotate-90'"
          />
        </button>

        <div
          v-if="site.sidebarLogoUrl"
          class="border-b border-slate-200 bg-white px-3 py-3"
          :class="isCollapsed ? 'md:hidden' : ''"
        >
          <div class="flex h-12 items-center justify-center overflow-hidden">
            <img :src="resolveUrl(site.sidebarLogoUrl)" alt="Sidebar logo" class="h-full w-full object-contain" />
          </div>
        </div>

        <nav class="flex-1 p-3" :class="isCollapsed ? 'md:overflow-visible md:px-0 md:py-2' : ''">
          <div v-for="(group, gi) in menuStore.resolvedMenu" :key="group.id">
            <p
              v-if="group.label"
              class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400"
              :class="[gi === 0 ? 'mb-1' : 'mb-1 mt-4', isCollapsed ? 'md:hidden' : '']"
            >
              {{ group.label }}
            </p>

            <div v-for="item in group.items" :key="item.id" class="mb-0.5">
              <button
                v-if="item.children && item.children.length > 0"
                type="button"
                class="group relative flex w-full items-center rounded-lg text-left font-medium transition-all hover:bg-[var(--accent-50)]"
                :class="[
                  isCollapsed ? collapsedRowBaseClass : rowBaseClass,
                  isCollapsed && isNodeActive(item) ? 'md:border md:border-[var(--accent-200)] md:bg-[var(--accent-50)] md:text-[var(--accent-700)] md:font-medium text-slate-900' : 'text-slate-900',
                  isCollapsed ? '' : itemClass(isNodeActive(item) ? route.path : item.to)
                ]"
                @click="isCollapsed ? toggleSidebar() : toggleMenu(item.id)"
              >
                <component
                  :is="item.icon"
                  class="shrink-0 transition-colors"
                  :class="[
                    isCollapsed ? 'md:h-5 md:w-5 h-4 w-4' : 'h-4 w-4',
                    isCollapsed && isNodeActive(item) ? 'md:text-[var(--accent-700)] text-slate-700' : isNodeActive(item) ? 'text-slate-900' : 'text-slate-400 group-hover:text-[var(--accent-600)]'
                  ]"
                />
                <span class="flex-1" :class="isCollapsed ? 'md:hidden' : ''">{{ item.label }}</span>
                <ChevronDown
                  class="h-4 w-4 text-slate-400 transition-transform duration-200"
                  :class="[{ '-rotate-90': !openMenus[item.id] }, isCollapsed ? 'md:hidden' : '']"
                />
                <span
                  v-if="isCollapsed"
                  class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 md:block"
                >
                  {{ item.label }}
                </span>
              </button>

              <router-link
                v-else
                :to="item.to"
                class="group relative flex items-center rounded-lg font-medium transition-all hover:bg-[var(--accent-50)]"
                :class="[
                  isCollapsed ? collapsedRowBaseClass : rowBaseClass,
                  isCollapsed && isActive(item.to) ? 'md:border md:border-[var(--accent-200)] md:bg-[var(--accent-50)] md:text-[var(--accent-700)] md:font-medium text-slate-900' : 'text-slate-900',
                  isCollapsed ? '' : itemClass(item.to)
                ]"
              >
                <component
                  :is="item.icon"
                  class="shrink-0 transition-colors"
                  :class="[
                    isCollapsed ? 'md:h-5 md:w-5 h-4 w-4' : 'h-4 w-4',
                    isCollapsed && isActive(item.to) ? 'md:text-[var(--accent-700)] text-slate-700' : isActive(item.to) ? 'text-slate-900' : 'text-slate-400 group-hover:text-[var(--accent-600)]'
                  ]"
                />
                <span class="flex-1" :class="isCollapsed ? 'md:hidden' : ''">{{ item.label }}</span>
                <span
                  v-if="isCollapsed"
                  class="pointer-events-none absolute left-full z-50 ml-2 hidden whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 md:block"
                >
                  {{ item.label }}
                </span>
              </router-link>

              <div
                v-if="item.children && item.children.length > 0 && openMenus[item.id] && !isCollapsed"
                class="ml-5 mt-1 space-y-0.5 border-l-2 border-slate-200 pl-4"
              >
                <template v-for="child in item.children" :key="child.id">
                  <button
                    v-if="child.children && child.children.length > 0"
                    type="button"
                    class="flex w-full items-center rounded-md text-left transition-all hover:bg-[var(--accent-50)]"
                    :class="[childRowClass, childClass(isNodeActive(child) ? route.path : child.to)]"
                    @click="toggleMenu(child.id)"
                  >
                    <span class="flex-1">{{ child.label }}</span>
                    <ChevronDown
                      class="h-3.5 w-3.5 text-slate-400 transition-transform duration-200"
                      :class="{ '-rotate-90': !openMenus[child.id] }"
                    />
                  </button>

                  <router-link
                    v-else
                    :to="child.to"
                    :class="[childRowClass, childClass(child.to)]"
                  >
                    {{ child.label }}
                  </router-link>

                  <div
                    v-if="child.children && child.children.length > 0 && openMenus[child.id]"
                    class="ml-4 mt-1 space-y-0.5 border-l border-slate-200 pl-3"
                  >
                    <router-link
                      v-for="grandchild in child.children"
                      :key="grandchild.id"
                      :to="grandchild.to"
                      :class="[childRowClass, childClass(grandchild.to)]"
                    >
                      {{ grandchild.label }}
                    </router-link>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </nav>

        <div
          v-if="site.footerText"
          class="border-t border-slate-200 px-3 py-2.5 transition-opacity duration-300"
          :class="isCollapsed ? 'md:hidden' : ''"
        >
          <p class="text-[11px] leading-relaxed text-slate-400">{{ site.footerText }}</p>
        </div>
      </aside>

      <main class="w-full min-w-0 flex-1 bg-white p-3 transition-all duration-300 ease-in-out md:p-4">
        <slot />
      </main>
    </div>
  </div>
</template>
