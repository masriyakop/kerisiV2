import { ref, watch } from "vue";
import { defineStore } from "pinia";

import type { ThemeColor } from "@/types";

const COLOR_KEY = "admin.theme.color";

const THEME_COLORS: ThemeColor[] = ["violet", "blue", "green", "red", "black-white", "grey"];

function isThemeColor(value: string | null): value is ThemeColor {
  return !!value && THEME_COLORS.includes(value as ThemeColor);
}

export const useUiThemeStore = defineStore("ui-theme", () => {
  const themeColor = ref<ThemeColor>("violet");

  function applyToDocument() {
    if (typeof document === "undefined") return;
    const root = document.documentElement;
    root.dataset.themeColor = themeColor.value;
    root.classList.remove("dark");
  }

  function persist() {
    if (typeof window === "undefined") return;
    localStorage.setItem(COLOR_KEY, themeColor.value);
  }

  function initFromStorage() {
    if (typeof window === "undefined") return;

    const savedColor = localStorage.getItem(COLOR_KEY);

    if (isThemeColor(savedColor)) themeColor.value = savedColor;

    applyToDocument();
  }

  function setThemeColor(color: ThemeColor) {
    themeColor.value = color;
  }

  watch(themeColor, () => {
    persist();
    applyToDocument();
  }, { immediate: true });

  return {
    themeColor,
    initFromStorage,
    setThemeColor,
  };
});
