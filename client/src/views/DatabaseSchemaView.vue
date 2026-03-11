<script setup lang="ts">
import { Database, KeyRound, Link2 } from "lucide-vue-next";

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
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="page-title">Database Schema</h1>
      </div>

      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Database class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Laravel Eloquent Models</h2>
        </div>
        <div class="p-4">
          <div class="grid gap-4 lg:grid-cols-2">
            <article v-for="model in models" :key="model.name" class="rounded-lg border border-slate-200 bg-white">
              <div class="border-b border-slate-100 px-4 py-2.5">
                <p class="text-sm font-semibold text-slate-900">{{ model.name }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ model.description }}</p>
              </div>
              <div class="divide-y divide-slate-100">
                <div v-for="field in model.fields" :key="field.name" class="flex items-center justify-between gap-3 px-4 py-2">
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-900">{{ field.name }}</p>
                    <p v-if="field.note" class="text-xs text-slate-400">{{ field.note }}</p>
                  </div>
                  <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-mono text-slate-600">{{ field.type }}</span>
                </div>
              </div>
            </article>
          </div>
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
