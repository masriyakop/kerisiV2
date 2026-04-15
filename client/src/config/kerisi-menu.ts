import { Folder } from "lucide-vue-next";

import type { MenuItemDef, MenuNode } from "@/config/admin-menu";
import { KERISI_MENU_TREE, type KerisiMigratedMenuNode } from "@/config/kerisi-menu-migrated";

function mapKerisiNode(node: KerisiMigratedMenuNode, depth: number): MenuItemDef | MenuNode {
  const mappedChildren = node.children?.map((child) => mapKerisiNode(child, depth + 1) as MenuNode);
  const baseNode: MenuNode = {
    id: `kerisi-${node.menuId}`,
    label: node.label,
    to: node.to,
    menuId: node.menuId,
    children: mappedChildren,
  };

  if (depth === 0) {
    return {
      ...baseNode,
      icon: Folder,
    };
  }

  return baseNode;
}

export const KERISI_MENU_ITEMS: MenuItemDef[] = KERISI_MENU_TREE.map((node) => mapKerisiNode(node, 0) as MenuItemDef);
