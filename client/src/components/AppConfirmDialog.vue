<script setup lang="ts">
import { AlertTriangle } from "lucide-vue-next";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const { isOpen, options, cancel, accept } = useConfirmDialog();
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm"
    @click.self="cancel()"
  >
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-2xl">
      <div class="flex items-start gap-2.5">
        <div
          class="mt-0.5 rounded-full p-1.5"
          :class="options.destructive ? 'bg-rose-100 text-rose-600' : 'bg-slate-100 text-slate-600'"
        >
          <AlertTriangle class="h-4 w-4" />
        </div>
        <div class="min-w-0 flex-1">
          <h3 class="text-base font-semibold text-slate-900">{{ options.title }}</h3>
          <p v-if="options.message" class="mt-1 text-sm text-slate-600">
            {{ options.message }}
          </p>
        </div>
      </div>

      <div class="mt-4 flex justify-end gap-2">
        <button
          class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50"
          @click="cancel()"
        >
          {{ options.cancelText }}
        </button>
        <button
          class="rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors"
          :class="options.destructive ? 'bg-rose-600 hover:bg-rose-700' : 'bg-slate-900 hover:bg-slate-800'"
          @click="accept()"
        >
          {{ options.confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>
