<script setup lang="ts">
import {
  Cpu,
  Database,
  Globe,
  Layers,
  Monitor,
  Palette,
  Server,
  Shield,
  Package,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";

type StackItem = { name: string; version: string; description?: string };
type StackGroup = { label: string; icon: unknown; color: { text: string }; items: StackItem[] };

const stack: StackGroup[] = [
  {
    label: "Frontend",
    icon: Monitor,
    color: { text: "text-violet-600" },
    items: [
      { name: "Vue", version: "3.5.30", description: "Progressive JavaScript framework" },
      { name: "TypeScript", version: "5.9.3", description: "Typed superset of JavaScript" },
      { name: "Vite", version: "7.3.1", description: "Frontend build tool and dev server (port 5180)" },
      { name: "Vue Router", version: "4.6.4", description: "Official router for Vue.js" },
      { name: "Pinia", version: "3.0.4", description: "State management for Vue" },
    ],
  },
  {
    label: "Styling & UI",
    icon: Palette,
    color: { text: "text-pink-600" },
    items: [
      { name: "Tailwind CSS", version: "3.4.19", description: "Utility-first CSS framework" },
      { name: "PostCSS", version: "8.5.8", description: "CSS post-processor for build pipeline" },
      { name: "Lucide Icons", version: "0.542", description: "Beautiful consistent icons" },
    ],
  },
  {
    label: "Backend",
    icon: Server,
    color: { text: "text-blue-600" },
    items: [
      { name: "Laravel", version: "12.54.1", description: "PHP web framework and REST API" },
      { name: "PHP", version: "^8.2", description: "Required runtime version from composer" },
      { name: "Eloquent ORM", version: "12.x", description: "Laravel ORM for model/database access" },
      { name: "Sanctum", version: "4.3.1", description: "Token/session authentication for SPA" },
    ],
  },
  {
    label: "Database & ORM",
    icon: Database,
    color: { text: "text-emerald-600" },
    items: [
      { name: "SQLite", version: "3", description: "Configured local database driver" },
      { name: "Migrations", version: "Laravel 12", description: "Schema versioning with artisan" },
      { name: "Seeders/Factories", version: "Laravel 12", description: "Deterministic test and fixture data" },
    ],
  },
  {
    label: "Authentication & Security",
    icon: Shield,
    color: { text: "text-amber-600" },
    items: [
      { name: "Laravel Sanctum", version: "4.3.1", description: "SPA auth guard and token support" },
      { name: "CSRF Middleware", version: "Laravel 12", description: "Built-in CSRF protection" },
      { name: "Throttle Middleware", version: "Laravel 12", description: "Rate limiting for auth/API routes" },
      { name: "Password Hashing", version: "PHP bcrypt", description: "Secure password hashing primitives" },
    ],
  },
  {
    label: "Infrastructure",
    icon: Layers,
    color: { text: "text-teal-600" },
    items: [
      { name: "Composer", version: "2.x", description: "PHP dependency management" },
      { name: "npm", version: "Scripts", description: "Runs dev/build pipeline for admin client" },
      { name: "Laravel Vite Plugin", version: "2.0", description: "Vite integration for Laravel apps" },
      { name: "Laravel Pint", version: "1.28.0", description: "Code style tooling for PHP codebase" },
    ],
  },
];
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">System Information</h1>
      </div>

      <!-- ───── Architecture Overview ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Cpu class="h-4 w-4 text-slate-600" />
          <h2 class="text-sm font-semibold text-slate-900">Architecture</h2>
        </div>
        <div class="p-4">
          <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border border-slate-200 p-3">
              <div class="flex items-center gap-2">
                <Globe class="h-4 w-4 text-violet-500" />
                <p class="text-sm font-semibold text-slate-900">Frontend SPA</p>
              </div>
              <p class="mt-1.5 text-xs text-slate-500">Vue 3 single-page application (TypeScript) built with Vite and served on port 5180.</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
              <div class="flex items-center gap-2">
                <Server class="h-4 w-4 text-blue-500" />
                <p class="text-sm font-semibold text-slate-900">Laravel API</p>
              </div>
              <p class="mt-1.5 text-xs text-slate-500">Laravel 12 backend with Eloquent ORM, Sanctum auth, middleware-based security, and JSON REST endpoints.</p>
            </div>
            <div class="rounded-lg border border-slate-200 p-3">
              <div class="flex items-center gap-2">
                <Package class="h-4 w-4 text-teal-500" />
                <p class="text-sm font-semibold text-slate-900">Hybrid Repository</p>
              </div>
              <p class="mt-1.5 text-xs text-slate-500">Laravel application root with a dedicated `client/` Vue admin frontend and API controllers in `app/Http/Controllers/Api`.</p>
            </div>
          </div>
        </div>
      </article>

      <!-- ───── Stack Groups ───── -->
      <div class="grid gap-4 lg:grid-cols-2">
        <article v-for="group in stack" :key="group.label" class="rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <component :is="group.icon" class="h-4 w-4" :class="group.color.text" />
            <h2 class="text-sm font-semibold text-slate-900">{{ group.label }}</h2>
          </div>
          <div class="divide-y divide-slate-100">
            <div v-for="item in group.items" :key="item.name" class="flex items-center justify-between px-4 py-2">
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-900">{{ item.name }}</p>
                <p v-if="item.description" class="text-xs text-slate-400">{{ item.description }}</p>
              </div>
              <span class="ml-3 shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-mono text-slate-600">{{ item.version }}</span>
            </div>
          </div>
        </article>
      </div>

      <!-- ───── Database Schema ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Database class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Database Models</h2>
        </div>
        <div class="p-4">
          <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="model in [
              { name: 'User', desc: 'Admin users with roles and credentials' },
              { name: 'Role', desc: 'Role definitions for authorization policies' },
              { name: 'Category', desc: 'Content taxonomy for organizing posts' },
              { name: 'Post', desc: 'Blog posts with draft/published/archived states' },
              { name: 'Page', desc: 'Static pages with publish workflow' },
              { name: 'Media', desc: 'Uploaded files with image metadata' },
              { name: 'Setting', desc: 'Key-value site configuration pairs' },
              { name: 'AuditLog', desc: 'Security and change tracking events' },
            ]" :key="model.name" class="rounded-lg border border-slate-200 px-3 py-2">
              <p class="text-sm font-medium text-slate-900">{{ model.name }}</p>
              <p class="mt-0.5 text-xs text-slate-400">{{ model.desc }}</p>
            </div>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
