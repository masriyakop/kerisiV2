import { defineStore } from "pinia";
import { DEFAULT_MENU, type AdminMenuPrefs, type MenuGroupDef, type MenuNode } from "@/config/admin-menu";
import { getAdminMenuPrefs, saveAdminMenuPrefs } from "@/api/cms";

type LegacyPrefs = {
  groupOrder: string[];
  itemOrder: Record<string, string[]>;
  hidden: string[];
  hiddenGroups?: string[];
};

function normalizePrefs(raw: AdminMenuPrefs | LegacyPrefs | null): AdminMenuPrefs | null {
  if (!raw) return null;

  const prefs = raw as Partial<AdminMenuPrefs> & LegacyPrefs;
  return {
    groupOrder: prefs.groupOrder || [],
    itemOrder: prefs.itemOrder || {},
    childOrder: prefs.childOrder || {},
    grandchildOrder: prefs.grandchildOrder || {},
    hidden: prefs.hidden || [],
    hiddenChildren: prefs.hiddenChildren || [],
    hiddenGrandchildren: prefs.hiddenGrandchildren || [],
    hiddenGroups: prefs.hiddenGroups || [],
  };
}

function orderByIds<T extends { id: string }>(items: T[], order: string[]): T[] {
  const map = new Map(items.map((item) => [item.id, item]));
  const ordered: T[] = [];

  for (const id of order) {
    const entry = map.get(id);
    if (entry) {
      ordered.push(entry);
      map.delete(id);
    }
  }

  for (const entry of map.values()) ordered.push(entry);
  return ordered;
}

function resolveChildren(children: MenuNode[] | undefined, prefs: AdminMenuPrefs, parentId: string): MenuNode[] {
  if (!children || children.length === 0) return [];

  const childOrder = prefs.childOrder[parentId] || [];
  const orderedChildren = orderByIds(children, childOrder)
    .filter((child) => !prefs.hiddenChildren.includes(child.id))
    .map((child) => {
      const orderedGrandchildren = orderByIds(child.children || [], prefs.grandchildOrder[child.id] || [])
        .filter((grandchild) => !prefs.hiddenGrandchildren.includes(grandchild.id));

      return {
        ...child,
        children: orderedGrandchildren,
      };
    });

  return orderedChildren;
}

function resolveMenu(prefsRaw: AdminMenuPrefs | null): MenuGroupDef[] {
  if (!prefsRaw) return DEFAULT_MENU;

  const prefs = normalizePrefs(prefsRaw);
  if (!prefs) return DEFAULT_MENU;

  const groupMap = new Map(DEFAULT_MENU.map((g) => [g.id, g]));
  const orderedGroups: MenuGroupDef[] = [];

  for (const groupId of prefs.groupOrder) {
    const group = groupMap.get(groupId);
    if (group) {
      orderedGroups.push(group);
      groupMap.delete(groupId);
    }
  }

  for (const group of groupMap.values()) {
    const defaultIdx = DEFAULT_MENU.findIndex((g) => g.id === group.id);
    let insertAt = 0;
    for (let i = 0; i < orderedGroups.length; i++) {
      const orderedIdx = DEFAULT_MENU.findIndex((g) => g.id === orderedGroups[i].id);
      if (orderedIdx < defaultIdx) insertAt = i + 1;
    }
    orderedGroups.splice(insertAt, 0, group);
  }

  return orderedGroups
    .filter((group) => !prefs.hiddenGroups.includes(group.id))
    .map((group) => {
      const orderedItems = orderByIds(group.items, prefs.itemOrder[group.id] || [])
        .filter((item) => !prefs.hidden.includes(item.id))
        .map((item) => ({
          ...item,
          children: resolveChildren(item.children, prefs, item.id),
        }));

      return {
        ...group,
        items: orderedItems,
      };
    })
    .filter((group) => group.items.length > 0);
}

export const useMenuStore = defineStore("menu", {
  state: () => ({
    prefs: null as AdminMenuPrefs | null,
    initialized: false,
  }),
  getters: {
    resolvedMenu(): MenuGroupDef[] {
      return resolveMenu(this.prefs);
    },
  },
  actions: {
    async load() {
      if (this.initialized) return;
      try {
        const res = await getAdminMenuPrefs();
        this.prefs = normalizePrefs(res.data);
      } catch {
        // use defaults
      }
      this.initialized = true;
    },
    async save(prefs: AdminMenuPrefs) {
      await saveAdminMenuPrefs(prefs);
      this.prefs = normalizePrefs(prefs);
    },
  },
});
