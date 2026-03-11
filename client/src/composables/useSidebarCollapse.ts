import { ref, watch } from "vue";

const COLLAPSED_KEY = "admin-sidebar-collapsed";
const COMPACT_KEY = "admin-sidebar-compact";

const isCollapsed = ref<boolean>(localStorage.getItem(COLLAPSED_KEY) === "true");
const isCompact = ref<boolean>(localStorage.getItem(COMPACT_KEY) === "true");

watch(isCollapsed, (val) => {
  localStorage.setItem(COLLAPSED_KEY, String(val));
});

watch(isCompact, (val) => {
  localStorage.setItem(COMPACT_KEY, String(val));
});

export function useSidebarCollapse() {
  function toggle() {
    isCollapsed.value = !isCollapsed.value;
  }

  function toggleCompact() {
    isCompact.value = !isCompact.value;
  }

  return { isCollapsed, isCompact, toggle, toggleCompact };
}
