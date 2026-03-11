<script setup lang="ts">
import { Server, Clock, Terminal, Cog, Database, ScrollText, Shield } from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";

type JobDef = { name: string; schedule: string; description: string; config?: string };
type ServiceConfig = { name: string; icon: unknown; color: string; driver: string; connection: string; table: string; notes: string };

const services: ServiceConfig[] = [
  {
    name: "Audit Trail",
    icon: ScrollText,
    color: "text-amber-600",
    driver: "database",
    connection: "sqlite (default)",
    table: "audit_logs",
    notes: "Can be moved to a dedicated database for high-volume logging without impacting main DB performance.",
  },
  {
    name: "Queue / Jobs",
    icon: Server,
    color: "text-emerald-600",
    driver: "database",
    connection: "sqlite (default)",
    table: "jobs / failed_jobs",
    notes: "Supports redis, sqs, beanstalkd drivers. Separate DB recommended for heavy workloads.",
  },
  {
    name: "Sessions",
    icon: Shield,
    color: "text-violet-600",
    driver: "database",
    connection: "sqlite (default)",
    table: "sessions",
    notes: "Sanctum SPA auth sessions. Can be switched to redis or file driver for scaling.",
  },
  {
    name: "Cache",
    icon: Database,
    color: "text-blue-600",
    driver: "database",
    connection: "sqlite (default)",
    table: "cache / cache_locks",
    notes: "Supports redis, memcached, file drivers. Redis recommended for production.",
  },
];

const jobs: JobDef[] = [
  {
    name: "CleanExpiredSessions",
    schedule: "Daily",
    description: "Removes expired sessions older than 24 hours from the sessions table.",
  },
  {
    name: "PruneAuditLogs",
    schedule: "Weekly",
    description: "Deletes audit log entries older than the configured retention period.",
    config: "app.audit_retention_days (default: 90)",
  },
];
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Hero Header ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">Queue & Services</h1>
      </div>

      <!-- ───── Service Connections ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Cog class="h-4 w-4 text-slate-500" />
          <h2 class="text-sm font-semibold text-slate-900">Service Connections</h2>
          <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Configurable in .env</span>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="svc in services" :key="svc.name" class="px-4 py-3">
            <div class="flex items-start gap-3">
              <component :is="svc.icon" class="mt-0.5 h-4 w-4 shrink-0" :class="svc.color" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-slate-900">{{ svc.name }}</p>
                <div class="mt-1.5 grid gap-x-6 gap-y-1 sm:grid-cols-3">
                  <div>
                    <span class="text-xs text-slate-400">Driver</span>
                    <p class="text-sm text-slate-700">{{ svc.driver }}</p>
                  </div>
                  <div>
                    <span class="text-xs text-slate-400">Connection</span>
                    <p class="text-sm text-slate-700">{{ svc.connection }}</p>
                  </div>
                  <div>
                    <span class="text-xs text-slate-400">Table(s)</span>
                    <p class="font-mono text-sm text-slate-700">{{ svc.table }}</p>
                  </div>
                </div>
                <p class="mt-1.5 text-xs text-slate-400">{{ svc.notes }}</p>
              </div>
            </div>
          </div>
        </div>
      </article>

      <!-- ───── Scheduled Jobs ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Clock class="h-4 w-4 text-emerald-600" />
          <h2 class="text-sm font-semibold text-slate-900">Scheduled Jobs</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="job in jobs" :key="job.name" class="px-4 py-3">
            <div class="flex items-center gap-3">
              <Server class="h-4 w-4 shrink-0 text-slate-400" />
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <p class="text-sm font-semibold text-slate-900">{{ job.name }}</p>
                  <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">{{ job.schedule }}</span>
                </div>
                <p class="mt-0.5 text-xs text-slate-500">{{ job.description }}</p>
                <p v-if="job.config" class="mt-1 text-xs text-slate-400">Config: <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">{{ job.config }}</code></p>
              </div>
            </div>
          </div>
        </div>
      </article>

      <!-- ───── Worker Commands ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Terminal class="h-4 w-4 text-violet-600" />
          <h2 class="text-sm font-semibold text-slate-900">Worker Commands</h2>
        </div>
        <div class="space-y-3 p-4">
          <div class="space-y-1.5">
            <p class="text-xs font-medium text-slate-500">Start the queue worker</p>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">php artisan queue:work --tries=3</code>
          </div>
          <div class="space-y-1.5">
            <p class="text-xs font-medium text-slate-500">Run the scheduler (for scheduled jobs)</p>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">php artisan schedule:work</code>
          </div>
          <div class="space-y-1.5">
            <p class="text-xs font-medium text-slate-500">View failed jobs</p>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">php artisan queue:failed</code>
          </div>
          <div class="space-y-1.5">
            <p class="text-xs font-medium text-slate-500">Retry all failed jobs</p>
            <code class="block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">php artisan queue:retry all</code>
          </div>
        </div>
      </article>

      <!-- ───── Environment Keys ───── -->
      <article class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
          <Database class="h-4 w-4 text-slate-500" />
          <h2 class="text-sm font-semibold text-slate-900">.env Configuration Keys</h2>
        </div>
        <div class="divide-y divide-slate-100">
          <div v-for="item in [
            { key: 'QUEUE_CONNECTION', value: 'database', desc: 'Queue driver (database, redis, sqs, sync)' },
            { key: 'CACHE_STORE', value: 'database', desc: 'Cache driver (database, redis, memcached, file)' },
            { key: 'SESSION_DRIVER', value: 'database', desc: 'Session storage (database, redis, file, cookie)' },
            { key: 'DB_CONNECTION', value: 'sqlite', desc: 'Default database connection' },
            { key: 'AUDIT_DB_CONNECTION', value: '(not set)', desc: 'Separate DB for audit logs (future)' },
            { key: 'QUEUE_DB_CONNECTION', value: '(not set)', desc: 'Separate DB for queue jobs (future)' },
          ]" :key="item.key" class="flex items-center justify-between gap-4 px-4 py-2.5">
            <div class="min-w-0">
              <code class="text-sm font-medium text-slate-900">{{ item.key }}</code>
              <p class="text-xs text-slate-400">{{ item.desc }}</p>
            </div>
            <span class="shrink-0 rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600">{{ item.value }}</span>
          </div>
        </div>
      </article>
    </div>
  </AdminLayout>
</template>
