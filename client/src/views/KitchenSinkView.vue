<script setup lang="ts">
import { computed, ref } from "vue";
import {
  Check,
  Eye,
  Info,
  Bell,
  Pencil,
  Trash2,
  Layers,
  Palette,
  MousePointerClick,
  Tag,
  TextCursorInput,
  ListFilter,
  PanelTop,
  MessageSquare,
  ChevronDown,
  HelpCircle,
  Table2,
  ChevronsLeftRight,
  CheckCircle2,
  XCircle,
  Clock3,
  ListChecks,
} from "lucide-vue-next";

import AdminLayout from "@/layouts/AdminLayout.vue";
import { useToast } from "@/composables/useToast";

const selectValue = ref("active");
const dropdownStatus = ref("published");
const showMeta = ref(true);
const page = ref(2);
const dropdownOpen = ref(false);
const dialogOpen = ref(false);
const activeTab = ref("content");
const toast = useToast();

const totalItems = 48;
const itemsPerPage = 10;
const totalPages = computed(() => Math.ceil(totalItems / itemsPerPage));

const sectionLinks = [
  { id: "overview", label: "Overview", icon: Layers, color: "text-slate-600" },
  { id: "tokens", label: "Color & Typography", icon: Palette, color: "text-violet-600" },
  { id: "buttons", label: "Buttons", icon: MousePointerClick, color: "text-blue-600" },
  { id: "badges", label: "Badges", icon: Tag, color: "text-emerald-600" },
  { id: "inputs", label: "Inputs", icon: TextCursorInput, color: "text-amber-600" },
  { id: "select", label: "Select", icon: ListFilter, color: "text-cyan-600" },
  { id: "tabs", label: "Tabs", icon: PanelTop, color: "text-pink-600" },
  { id: "toast", label: "Toast", icon: Bell, color: "text-blue-600" },
  { id: "dialog", label: "Dialog", icon: MessageSquare, color: "text-indigo-600" },
  { id: "dropdown", label: "Dropdown", icon: ChevronDown, color: "text-teal-600" },
  { id: "tooltip", label: "Tooltip", icon: HelpCircle, color: "text-orange-600" },
  { id: "table", label: "Table", icon: Table2, color: "text-rose-600" },
  { id: "timeline", label: "Timeline", icon: Clock3, color: "text-sky-600" },
  { id: "steps", label: "Steps", icon: ListChecks, color: "text-indigo-600" },
  { id: "pagination", label: "Pagination", icon: ChevronsLeftRight, color: "text-fuchsia-600" },
];

function nextPage(delta: number) {
  const next = Math.max(1, Math.min(totalPages.value, page.value + delta));
  page.value = next;
}

function demoToast(variant: "success" | "error" | "info") {
  if (variant === "success") {
    toast.success("Changes saved", "Your content has been updated.");
    return;
  }
  if (variant === "error") {
    toast.error("Save failed", "Please retry in a few seconds.");
    return;
  }
  toast.info("Sync in progress", "Background update is running.");
}
</script>

<template>
  <AdminLayout>
    <div class="mx-auto max-w-7xl space-y-4">
      <!-- ───── Slim Title Bar ───── -->
      <div class="flex items-center justify-between">
        <h1 class="page-title">UI Standard Reference</h1>
        <div class="flex items-center gap-2">
          <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">{{ sectionLinks.length }} Components</span>
        </div>
      </div>

      <!-- ───── Quick Jump Nav ───── -->
      <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Quick Jump</p>
        <div class="grid gap-1.5 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
          <a
            v-for="link in sectionLinks"
            :key="link.id"
            :href="`#${link.id}`"
            class="group flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-600 transition-all hover:bg-slate-50 hover:text-slate-900"
          >
            <component :is="link.icon" class="h-4 w-4 text-slate-400 transition-colors group-hover:text-slate-600" />
            {{ link.label }}
          </a>
        </div>
      </div>

      <section class="space-y-4">
        <!-- ═══════ OVERVIEW ═══════ -->
        <article id="overview" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Layers class="h-4 w-4 text-slate-600" />
            <h2 class="text-sm font-semibold text-slate-900">Overview</h2>
          </div>
          <div class="p-4">
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-5">
                <p class="mb-3 text-sm font-semibold text-slate-900">Usage rules</p>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">1</span> Use existing <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">components/ui</code> primitives first.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">2</span> Keep labels explicit and action-oriented.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">3</span> Always include loading, empty, and error states.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">4</span> Validate accessibility before shipping.</li>
                </ul>
              </div>
              <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-5">
                <p class="mb-3 text-sm font-semibold text-slate-900">Naming conventions</p>
                <ul class="space-y-2 text-sm text-slate-600">
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">1</span> Use semantic labels: <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">Save changes</code>, not <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">Submit</code>.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">2</span> Use consistent status terms: Draft, Published, Archived.</li>
                  <li class="flex gap-2"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">3</span> Keep page sections predictable: Header, Filters, Content, Actions.</li>
                </ul>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ COLOR & TYPOGRAPHY ═══════ -->
        <article id="tokens" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Palette class="h-4 w-4 text-violet-600" />
            <h2 class="text-sm font-semibold text-slate-900">Color & Typography</h2>
          </div>
          <div class="space-y-4 p-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-900" />
                <div class="p-3">
                  <p class="text-sm font-semibold">primary</p>
                  <p class="font-mono text-xs text-slate-400">slate-900</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-200" />
                <div class="p-3">
                  <p class="text-sm font-semibold">secondary</p>
                  <p class="font-mono text-xs text-slate-400">slate-200</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-slate-100" />
                <div class="p-3">
                  <p class="text-sm font-semibold">accent</p>
                  <p class="font-mono text-xs text-slate-400">slate-100</p>
                </div>
              </div>
              <div class="group overflow-hidden rounded-lg border border-slate-200 transition-shadow hover:shadow-md">
                <div class="h-20 bg-red-600" />
                <div class="p-3">
                  <p class="text-sm font-semibold">destructive</p>
                  <p class="font-mono text-xs text-slate-400">red-600</p>
                </div>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                <p class="text-3xl font-semibold tracking-tight">Heading / 30-36px</p>
                <p class="mt-2 text-slate-500">For page titles and section hierarchy.</p>
                <div class="mt-3 flex gap-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">font-bold</span>
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">tracking-tight</span>
                </div>
              </div>
              <div class="rounded-lg border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4">
                <p class="text-base">Body / 14-16px</p>
                <p class="mt-2 text-sm text-slate-500">For labels, descriptions, and table values.</p>
                <div class="mt-3 flex gap-2">
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">font-normal</span>
                  <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">text-sm / text-base</span>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ BUTTONS ═══════ -->
        <article id="buttons" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <MousePointerClick class="h-4 w-4 text-blue-600" />
            <h2 class="text-sm font-semibold text-slate-900">Buttons</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-3 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800">Save changes</button>
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50">Cancel</button>
                <button class="rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium transition-colors hover:bg-slate-300">Preview</button>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700">Delete</button>
                <button class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100">More</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm">Small</button>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm">Default</button>
                <button class="rounded-lg bg-slate-900 px-5 py-2.5 text-base font-medium text-white shadow-sm">Large</button>
                <button class="text-sm font-medium text-slate-700 underline underline-offset-4 transition-colors hover:text-slate-900">Link style</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm">Default</button>
                <button disabled class="cursor-not-allowed rounded-lg bg-slate-200 px-4 py-2 text-sm font-medium text-slate-400">Disabled</button>
                <button class="rounded-lg border-2 border-red-300 bg-red-50 px-4 py-2 text-sm font-medium text-red-700">Invalid</button>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use one clear primary CTA per section, then fallback actions as outline/ghost.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not place multiple competing primary buttons side by side.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ BADGES ═══════ -->
        <article id="badges" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Tag class="h-4 w-4 text-emerald-600" />
            <h2 class="text-sm font-semibold text-slate-900">Badges</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-2.5 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">Published</span>
                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700">Draft</span>
                <span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700">Archived</span>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Rejected</span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-medium text-emerald-700">Approved</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-700">Pending</span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap gap-2.5 rounded-lg border border-slate-200 p-5">
                <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">Default</span>
                <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-medium text-slate-700">Secondary</span>
                <span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700">Outline</span>
                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Destructive</span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 p-5">
                <span class="flex items-center gap-1.5 rounded-full bg-slate-900 px-3 py-1 text-xs font-medium text-white">
                  <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                  Active
                </span>
                <span class="text-sm text-slate-500">Use dot indicator + text + badge for live-state clarity.</span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use badges for short statuses and keep wording consistent across screens.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use badge color alone to communicate meaning; include readable text.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ INPUTS ═══════ -->
        <article id="inputs" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <TextCursorInput class="h-4 w-4 text-amber-600" />
            <h2 class="text-sm font-semibold text-slate-900">Inputs, Label, Textarea</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="grid gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5 md:grid-cols-2">
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Title</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="Enter title" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Slug</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" placeholder="my-first-post" />
                </div>
                <div class="space-y-1.5 md:col-span-2">
                  <label class="text-sm font-medium text-slate-700">Description</label>
                  <textarea class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200" rows="3" placeholder="Write short description..." />
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="grid gap-4 rounded-lg border border-slate-200 p-5 md:grid-cols-3">
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Default</label>
                  <input class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm" placeholder="Default" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Disabled</label>
                  <input disabled class="w-full rounded-lg border border-slate-200 bg-slate-100 px-3 py-2 text-sm text-slate-400" placeholder="Disabled" />
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-red-500">Invalid</label>
                  <input class="w-full rounded-lg border-2 border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900" value="invalid@email" />
                  <p class="text-xs text-red-500">Please enter a valid email address.</p>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="space-y-2 rounded-lg border border-slate-200 p-5 text-sm">
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white p-3">
                  <span class="h-2 w-2 rounded-full bg-slate-400" />
                  <span class="font-medium text-slate-700">Default:</span> editable and readable.
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50/50 p-3">
                  <span class="h-2 w-2 rounded-full bg-red-400" />
                  <span class="font-medium text-red-700">Invalid:</span> <span class="text-slate-600">set <code class="rounded bg-slate-200 px-1 py-0.5 text-xs">aria-invalid</code> and show error message nearby.</span>
                </div>
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <span class="h-2 w-2 rounded-full bg-slate-300" />
                  <span class="font-medium text-slate-500">Disabled:</span> <span class="text-slate-600">non-editable for unavailable fields.</span>
                </div>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Pair every input with a clear label and nearby validation message.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not rely on placeholder text as the only field label.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ SELECT ═══════ -->
        <article id="select" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ListFilter class="h-4 w-4 text-cyan-600" />
            <h2 class="text-sm font-semibold text-slate-900">Select</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="max-w-sm space-y-1.5">
                  <label class="text-sm font-medium text-slate-700">Status</label>
                  <select v-model="selectValue" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm transition-colors focus:border-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="active">Active</option>
                    <option value="review">In Review</option>
                    <option value="archived">Archived</option>
                  </select>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="flex flex-wrap gap-4 rounded-lg border border-slate-200 p-5">
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Default</label>
                  <select class="w-44 rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm"><option>Active</option></select>
                </div>
                <div class="space-y-1.5">
                  <label class="text-xs font-medium text-slate-500">Compact</label>
                  <select class="w-44 rounded-lg border border-slate-300 px-3 py-1.5 text-xs shadow-sm"><option>Review</option></select>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use default state for required fields and keep options concise.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use select when options are finite and mutually exclusive.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use select for long free-text content or huge option sets without search.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TABS ═══════ -->
        <article id="tabs" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <PanelTop class="h-4 w-4 text-pink-600" />
            <h2 class="text-sm font-semibold text-slate-900">Tabs</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="inline-flex rounded-lg bg-slate-200/60 p-1 text-sm">
                  <button
                    :class="activeTab === 'content' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'content'"
                  >Content</button>
                  <button
                    :class="activeTab === 'seo' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'seo'"
                  >SEO</button>
                  <button
                    :class="activeTab === 'publish' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="rounded-md px-4 py-1.5 font-medium transition-all"
                    @click="activeTab = 'publish'"
                  >Publish</button>
                </div>
                <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
                  <template v-if="activeTab === 'content'">Main editor settings and content management.</template>
                  <template v-else-if="activeTab === 'seo'">Meta tags, description, and search optimization.</template>
                  <template v-else>Scheduling, visibility, and publishing options.</template>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use 2-5 tabs max. Keep tab labels short and noun-based.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Active tab must be visually obvious and keyboard accessible.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use tabs for related content under the same task context.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not put unrelated workflows in tabs; split into separate pages instead.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TOAST ═══════ -->
        <article id="toast" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Bell class="h-4 w-4 text-blue-600" />
            <h2 class="text-sm font-semibold text-slate-900">Toast</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex flex-wrap gap-2 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-emerald-700" @click="demoToast('success')">Success Toast</button>
                <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-blue-700" @click="demoToast('info')">Info Toast</button>
                <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-rose-700" @click="demoToast('error')">Error Toast</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Notes</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Toast appears in the admin topbar area (current app style), with auto-dismiss progress.
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ DIALOG ═══════ -->
        <article id="dialog" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <MessageSquare class="h-4 w-4 text-indigo-600" />
            <h2 class="text-sm font-semibold text-slate-900">Dialog</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dialogOpen = true">Open confirmation dialog</button>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Prefer one primary and one secondary action in dialogs.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">For destructive actions, use <code class="rounded bg-slate-100 px-1 py-0.5 text-xs">destructive</code> button styling and explicit confirmation text.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep dialog content concise and task-specific.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not place long multi-step workflows inside dialogs.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ DROPDOWN ═══════ -->
        <article id="dropdown" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ChevronDown class="h-4 w-4 text-teal-600" />
            <h2 class="text-sm font-semibold text-slate-900">Dropdown Menu</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="relative inline-block">
                  <button class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dropdownOpen = !dropdownOpen">
                    Open menu
                    <ChevronDown class="h-4 w-4 text-slate-400" />
                  </button>
                  <div v-if="dropdownOpen" class="absolute left-0 top-11 z-10 w-56 rounded-lg border border-slate-200 bg-white p-1.5 shadow-lg">
                    <p class="px-2.5 py-1.5 text-xs font-semibold text-slate-400">Post actions</p>
                    <div class="my-1 border-t border-slate-100" />
                    <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition-colors hover:bg-slate-100">
                      <Pencil class="h-3.5 w-3.5 text-slate-400" /> Edit post
                    </button>
                    <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition-colors hover:bg-slate-100">
                      <Layers class="h-3.5 w-3.5 text-slate-400" /> Duplicate
                    </button>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100">
                      <input v-model="showMeta" type="checkbox" class="rounded" />
                      Show meta panel
                    </label>
                    <div class="my-1 border-t border-slate-100" />
                    <p class="px-2.5 py-1.5 text-xs font-semibold text-slate-400">Change status</p>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="published" /> Published</label>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="draft" /> Draft</label>
                    <label class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition-colors hover:bg-slate-100"><input v-model="dropdownStatus" type="radio" value="archived" /> Archived</label>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Mix item, checkbox, and radio entries only when needed for real context actions.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="flex items-center gap-3 rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Current status:
                <span class="rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white">{{ dropdownStatus }}</span>
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-500">{{ showMeta ? "meta shown" : "meta hidden" }}</span>
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use dropdown for secondary actions to reduce visual clutter.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not hide primary actions inside dropdown menus.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TOOLTIP ═══════ -->
        <article id="tooltip" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <HelpCircle class="h-4 w-4 text-orange-600" />
            <h2 class="text-sm font-semibold text-slate-900">Tooltip</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="flex items-center gap-4 rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <button title="Save draft" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white shadow-sm transition-colors hover:bg-slate-50" aria-label="Save draft">
                  <Check class="h-4 w-4 text-slate-600" />
                </button>
                <span title="More information" class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-300 bg-white shadow-sm transition-colors hover:bg-slate-50">
                  <Info class="h-4 w-4 text-slate-600" />
                </span>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Keep tooltip text short (1 line), descriptive, and action-focused.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Tooltip appears on hover/focus and should not block nearby controls.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Use tooltip to clarify icon-only actions.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not use tooltip as a replacement for mandatory visible labels.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TABLE ═══════ -->
        <article id="table" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Table2 class="h-4 w-4 text-rose-600" />
            <h2 class="text-sm font-semibold text-slate-900">Table</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="overflow-x-auto rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <table class="w-full min-w-175 text-sm">
                  <thead>
                    <tr class="border-b border-slate-200">
                      <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Title</th>
                      <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Author</th>
                      <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Status</th>
                      <th class="pb-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Updated</th>
                      <th class="pb-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr class="border-b border-slate-100 transition-colors hover:bg-white">
                      <td class="py-3 font-medium text-slate-900">Welcome Post</td>
                      <td class="py-3 text-slate-600">Admin</td>
                      <td class="py-3"><span class="rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white">Published</span></td>
                      <td class="py-3 text-slate-500">2h ago</td>
                      <td class="py-3">
                        <div class="flex justify-end gap-1">
                          <button title="View" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Eye class="h-4 w-4 text-slate-500" /></button>
                          <button title="Edit" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Pencil class="h-4 w-4 text-slate-500" /></button>
                          <button title="Delete" class="rounded-lg p-2 transition-colors hover:bg-red-100"><Trash2 class="h-4 w-4 text-red-500" /></button>
                        </div>
                      </td>
                    </tr>
                    <tr class="border-b border-slate-100 transition-colors hover:bg-white">
                      <td class="py-3 font-medium text-slate-900">SEO Checklist</td>
                      <td class="py-3 text-slate-600">Editor</td>
                      <td class="py-3"><span class="rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700">Draft</span></td>
                      <td class="py-3 text-slate-500">1d ago</td>
                      <td class="py-3">
                        <div class="flex justify-end gap-1">
                          <button title="View" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Eye class="h-4 w-4 text-slate-500" /></button>
                          <button title="Edit" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Pencil class="h-4 w-4 text-slate-500" /></button>
                          <button title="Delete" class="rounded-lg p-2 transition-colors hover:bg-red-100"><Trash2 class="h-4 w-4 text-red-500" /></button>
                        </div>
                      </td>
                    </tr>
                    <tr class="transition-colors hover:bg-white">
                      <td class="py-3 font-medium text-slate-900">Product Launch</td>
                      <td class="py-3 text-slate-600">Manager</td>
                      <td class="py-3"><span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700">Pending</span></td>
                      <td class="py-3 text-slate-500">3d ago</td>
                      <td class="py-3">
                        <div class="flex justify-end gap-1">
                          <button title="View" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Eye class="h-4 w-4 text-slate-500" /></button>
                          <button title="Edit" class="rounded-lg p-2 transition-colors hover:bg-slate-200"><Pencil class="h-4 w-4 text-slate-500" /></button>
                          <button title="Delete" class="rounded-lg p-2 transition-colors hover:bg-red-100"><Trash2 class="h-4 w-4 text-red-500" /></button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use badges or icons in cells when status clarity is important.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Provide empty, loading, and error table states in real feature pages.</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep columns scannable and order by user priority.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not overload table rows with too many inline actions.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ TIMELINE ═══════ -->
        <article id="timeline" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <Clock3 class="h-4 w-4 text-sky-600" />
            <h2 class="text-sm font-semibold text-slate-900">Timeline</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <ol class="space-y-5">
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-emerald-500 ring-4 ring-emerald-100" />
                    <span class="absolute left-[7px] top-5 h-[calc(100%+8px)] w-px bg-slate-200" />
                    <p class="text-sm font-medium text-slate-900">Content drafted</p>
                    <p class="text-xs text-slate-500">By Editor · 09:20 AM</p>
                  </li>
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-blue-500 ring-4 ring-blue-100" />
                    <span class="absolute left-[7px] top-5 h-[calc(100%+8px)] w-px bg-slate-200" />
                    <p class="text-sm font-medium text-slate-900">Sent for review</p>
                    <p class="text-xs text-slate-500">By Content Lead · 11:05 AM</p>
                  </li>
                  <li class="relative pl-8">
                    <span class="absolute left-0 top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 ring-4 ring-amber-100" />
                    <p class="text-sm font-medium text-slate-900">Scheduled to publish</p>
                    <p class="text-xs text-slate-500">Tomorrow · 08:00 AM</p>
                  </li>
                </ol>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Use compact timeline for activity logs and expanded timeline for release histories.
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Show clear status color coding (done, in review, scheduled) with readable timestamps.
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep chronology obvious with consistent spacing and timestamp formatting.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not overload each timeline row with too many metadata fields.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ STEPS ═══════ -->
        <article id="steps" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ListChecks class="h-4 w-4 text-indigo-600" />
            <h2 class="text-sm font-semibold text-slate-900">Steps</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="grid gap-3 md:grid-cols-4">
                  <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
                    <p class="text-xs font-semibold text-emerald-700">Step 1</p>
                    <p class="mt-1 text-sm font-medium text-emerald-900">Draft</p>
                  </div>
                  <div class="rounded-lg border border-blue-200 bg-blue-50 p-3">
                    <p class="text-xs font-semibold text-blue-700">Step 2</p>
                    <p class="mt-1 text-sm font-medium text-blue-900">Review</p>
                  </div>
                  <div class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                    <p class="text-xs font-semibold text-amber-700">Step 3</p>
                    <p class="mt-1 text-sm font-medium text-amber-900">Approve</p>
                  </div>
                  <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-xs font-semibold text-slate-500">Step 4</p>
                    <p class="mt-1 text-sm font-medium text-slate-700">Publish</p>
                  </div>
                </div>

                <div class="mt-5 rounded-lg border border-slate-200 bg-white p-4">
                  <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Design 2: Connector Stepper</p>
                  <div class="flex items-center justify-between">
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">1</span>
                      <span class="text-xs font-medium text-slate-700">Setup</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-emerald-300" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-xs font-semibold text-white">2</span>
                      <span class="text-xs font-medium text-slate-700">Details</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-blue-300" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">3</span>
                      <span class="text-xs font-medium text-blue-700">Review</span>
                    </div>
                    <div class="mx-2 h-px flex-1 bg-slate-200" />
                    <div class="flex flex-col items-center gap-1">
                      <span class="flex h-7 w-7 items-center justify-center rounded-full border border-slate-300 bg-white text-xs font-semibold text-slate-500">4</span>
                      <span class="text-xs font-medium text-slate-500">Done</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Use horizontal steps for short wizards and vertical steps for long setup flows.
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">
                Include complete, active, and pending states with labels and color contrast.
              </div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep each step title short and action-oriented.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not hide step status solely in color; always show text labels.</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <!-- ═══════ PAGINATION ═══════ -->
        <article id="pagination" class="scroll-mt-24 rounded-lg border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5">
            <ChevronsLeftRight class="h-4 w-4 text-fuchsia-600" />
            <h2 class="text-sm font-semibold text-slate-900">Pagination</h2>
          </div>
          <div class="space-y-4 p-4">
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Preview</p>
              <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50/50 p-5">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-slate-500">Showing {{ (page - 1) * itemsPerPage + 1 }}-{{ Math.min(page * itemsPerPage, totalItems) }} of {{ totalItems }}</span>
                  <div class="flex items-center gap-2">
                    <button
                      class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="page <= 1"
                      @click="nextPage(-1)"
                    >Previous</button>
                    <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm font-medium">{{ page }} / {{ totalPages }}</span>
                    <button
                      class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                      :disabled="page >= totalPages"
                      @click="nextPage(1)"
                    >Next</button>
                  </div>
                </div>
              </div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">Variants</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Use with table/list views when results exceed one page.</div>
            </div>
            <div>
              <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">States</p>
              <div class="rounded-lg border border-slate-200 p-5 text-sm text-slate-600">Disable previous/next controls at page boundaries (already built into component).</div>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
              <div class="flex gap-3 rounded-lg border border-emerald-200 bg-emerald-50/50 p-4">
                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
                <div class="text-sm text-emerald-900">
                  <p class="mb-1 font-semibold">Do</p>
                  <p>Keep pagination near the list it controls and preserve filters when changing pages.</p>
                </div>
              </div>
              <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/50 p-4">
                <XCircle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
                <div class="text-sm text-amber-900">
                  <p class="mb-1 font-semibold">Don&apos;t</p>
                  <p>Do not paginate very small datasets; keep them on one page.</p>
                </div>
              </div>
            </div>
          </div>
        </article>
      </section>
    </div>

    <!-- ───── Dialog Overlay ───── -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="dialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm" @click.self="dialogOpen = false">
          <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
          >
            <div v-if="dialogOpen" class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-4 shadow-2xl">
              <h4 class="text-lg font-semibold text-slate-900">Publish this article?</h4>
              <p class="mt-2 text-sm text-slate-500">This action will make the article visible to all visitors.</p>
              <div class="mt-5 flex justify-end gap-2">
                <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-slate-50" @click="dialogOpen = false">Cancel</button>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white shadow-sm transition-colors hover:bg-slate-800" @click="dialogOpen = false">Publish</button>
              </div>
            </div>
          </Transition>
        </div>
      </Transition>
    </Teleport>
  </AdminLayout>
</template>
