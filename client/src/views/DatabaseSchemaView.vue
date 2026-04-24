<script setup lang="ts">
import { computed, ref } from "vue";
import { Database, KeyRound, Link2, Table2 } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";

type Field = { name: string; type: string; note?: string };
type ModelDef = { name: string; description: string; fields: Field[] };

const models: ModelDef[] = [
  {
    name: "Role",
    description: "User roles with JSON-based permissions for RBAC.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "name", type: "String", note: "Unique" },
      { name: "description", type: "Text" },
      { name: "permissions", type: "JSON" },
      { name: "createdAt", type: "DateTime" },
      { name: "updatedAt", type: "DateTime" },
    ],
  },
  {
    name: "User",
    description: "Admin users with authentication, profile data, and RBAC.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "name", type: "String" },
      { name: "email", type: "String", note: "Unique" },
      { name: "emailVerifiedAt", type: "DateTime?" },
      { name: "password", type: "String" },
      { name: "photoUrl", type: "String?" },
      { name: "role", type: "String" },
      { name: "roleId", type: "BigInt?", note: "FK → roles.id" },
      { name: "isActive", type: "Boolean" },
      { name: "rememberToken", type: "String?" },
      { name: "createdAt", type: "DateTime" },
      { name: "updatedAt", type: "DateTime" },
    ],
  },
  {
    name: "Session",
    description: "Laravel session store for Sanctum SPA authentication.",
    fields: [
      { name: "id", type: "String", note: "PK" },
      { name: "userId", type: "BigInt?", note: "FK → users.id" },
      { name: "ipAddress", type: "String(45)?" },
      { name: "userAgent", type: "Text?" },
      { name: "payload", type: "LongText" },
      { name: "lastActivity", type: "Integer", note: "Indexed" },
    ],
  },
  {
    name: "Post",
    description: "Content posts with statuses, excerpts, and featured media.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "title", type: "String" },
      { name: "slug", type: "String", note: "Unique" },
      { name: "excerpt", type: "Text?" },
      { name: "content", type: "LongText" },
      { name: "status", type: "String", note: "draft | published" },
      { name: "featuredImageId", type: "BigInt?", note: "FK → media.id" },
      { name: "publishedAt", type: "DateTime?" },
      { name: "createdAt", type: "DateTime" },
      { name: "updatedAt", type: "DateTime" },
    ],
  },
  {
    name: "Category",
    description: "Post categories with slugs. Many-to-many with posts.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "name", type: "String" },
      { name: "slug", type: "String", note: "Unique" },
      { name: "description", type: "Text?" },
      { name: "createdAt", type: "DateTime" },
      { name: "updatedAt", type: "DateTime" },
    ],
  },
  {
    name: "CategoryPost",
    description: "Pivot table linking categories and posts (many-to-many).",
    fields: [
      { name: "categoryId", type: "BigInt", note: "FK → categories.id, PK" },
      { name: "postId", type: "BigInt", note: "FK → posts.id, PK" },
    ],
  },
  {
    name: "Page",
    description: "Static pages with publish workflow and featured media.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "title", type: "String" },
      { name: "slug", type: "String", note: "Unique" },
      { name: "content", type: "LongText" },
      { name: "status", type: "String", note: "draft | published" },
      { name: "featuredImageId", type: "BigInt?", note: "FK → media.id" },
      { name: "publishedAt", type: "DateTime?" },
      { name: "createdAt", type: "DateTime" },
      { name: "updatedAt", type: "DateTime" },
    ],
  },
  {
    name: "Media",
    description: "Uploaded assets with metadata, dimensions, and paths.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "filename", type: "String" },
      { name: "originalName", type: "String" },
      { name: "title", type: "String?" },
      { name: "caption", type: "Text?" },
      { name: "description", type: "Text?" },
      { name: "mimeType", type: "String" },
      { name: "size", type: "UnsignedInt" },
      { name: "width", type: "UnsignedInt?" },
      { name: "height", type: "UnsignedInt?" },
      { name: "altText", type: "String?" },
      { name: "path", type: "String" },
      { name: "url", type: "String" },
      { name: "createdAt", type: "DateTime" },
    ],
  },
  {
    name: "Setting",
    description: "Key-value settings used by CMS and admin UI.",
    fields: [
      { name: "key", type: "String", note: "PK" },
      { name: "value", type: "LongText" },
      { name: "updatedAt", type: "DateTime?" },
    ],
  },
  {
    name: "AuditLog",
    description: "Full activity log for tracking all model changes and auth events.",
    fields: [
      { name: "id", type: "BigInt", note: "PK" },
      { name: "userId", type: "BigInt?", note: "FK → users.id" },
      { name: "action", type: "String", note: "created | updated | deleted | login | logout" },
      { name: "auditableType", type: "String?", note: "Polymorphic model class" },
      { name: "auditableId", type: "BigInt?", note: "Polymorphic model ID" },
      { name: "oldValues", type: "JSON?" },
      { name: "newValues", type: "JSON?" },
      { name: "ipAddress", type: "String(45)?" },
      { name: "userAgent", type: "Text?" },
      { name: "createdAt", type: "DateTime" },
    ],
  },
];

type ModelGroup = { id: string; label: string; tables: string[] };

const modelGroups: ModelGroup[] = [
  { id: "identity", label: "Identity & Access", tables: ["Role", "User", "Session"] },
  { id: "content", label: "Content Management", tables: ["Post", "Category", "CategoryPost", "Page", "Media"] },
  { id: "platform", label: "Platform & Settings", tables: ["Setting", "AuditLog"] },
];

const selectedTableName = ref(models[0]?.name ?? "");

const selectedModel = computed<ModelDef | null>(() => {
  return models.find((model) => model.name === selectedTableName.value) ?? null;
});

const totalColumns = computed(() => selectedModel.value?.fields.length ?? 0);

function selectTable(tableName: string) {
  selectedTableName.value = tableName;
}
</script>

<template>
  <AdminLayout>
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Database Schema</h1>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Database class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Schema Explorer</h2>
        </div>
        <div class="grid min-h-[560px] grid-cols-1 lg:grid-cols-[320px_1fr]">
          <aside class="border-b border-slate-100 p-4 lg:border-b-0 lg:border-r">
            <div class="mb-3 flex items-center gap-2">
              <Table2 class="h-4 w-4 text-slate-500" />
              <h3 class="text-sm font-semibold text-slate-900">Tables</h3>
            </div>
            <div class="space-y-4">
              <section v-for="group in modelGroups" :key="group.id" class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ group.label }}</p>
                <div class="space-y-1">
                  <button
                    v-for="tableName in group.tables"
                    :key="tableName"
                    type="button"
                    class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-left text-sm transition"
                    :class="
                      selectedTableName === tableName
                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                    "
                    @click="selectTable(tableName)"
                  >
                    <span class="font-medium">{{ tableName }}</span>
                    <span
                      class="rounded-full px-2 py-0.5 text-xs"
                      :class="
                        selectedTableName === tableName ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'
                      "
                    >
                      {{ models.find((model) => model.name === tableName)?.fields.length ?? 0 }}
                    </span>
                  </button>
                </div>
              </section>
            </div>
          </aside>

          <section class="p-4">
            <template v-if="selectedModel">
              <div class="rounded-lg border border-slate-200 bg-white">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                  <div>
                    <p class="text-base font-semibold text-slate-900">{{ selectedModel.name }}</p>
                    <p class="mt-0.5 text-sm text-slate-500">{{ selectedModel.description }}</p>
                  </div>
                  <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                    {{ totalColumns }} columns
                  </span>
                </div>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                      <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Column</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Type</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Notes</th>
                      </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                      <tr v-for="field in selectedModel.fields" :key="field.name">
                        <td class="px-4 py-2.5 text-sm font-medium text-slate-900">{{ field.name }}</td>
                        <td class="px-4 py-2.5">
                          <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-mono text-xs text-slate-700">{{ field.type }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-slate-600">{{ field.note ?? "—" }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
            <div v-else class="rounded-lg border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
              Select a table from the left panel to view its columns.
            </div>
          </section>
        </div>
      </article>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Link2 class="h-4 w-4 text-blue-600" />
          <h2 class="text-sm font-semibold text-slate-900">Relationship Notes</h2>
        </div>
        <div class="space-y-2 p-4 text-sm text-slate-600">
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">users.role_id</code> references <code class="text-xs">roles.id</code> (nullable, set null on delete).</p>
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">posts.featured_image_id</code> and <code class="text-xs">pages.featured_image_id</code> reference <code class="text-xs">media.id</code> (set null on delete).</p>
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">category_post</code> is a pivot table with composite PK and cascade deletes on both FKs.</p>
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">audit_logs</code> uses polymorphic <code class="text-xs">auditable_type</code> + <code class="text-xs">auditable_id</code> (both nullable for auth events).</p>
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">sessions.user_id</code> references <code class="text-xs">users.id</code> (Laravel session store for Sanctum SPA auth).</p>
          <p><KeyRound class="mr-1 inline h-3.5 w-3.5 text-amber-600" /> <code class="text-xs">settings</code> uses <code class="text-xs">key</code> as string primary key for fast config lookup.</p>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
