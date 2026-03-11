<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { Shield, ArrowRight, AlertCircle, Eye, EyeOff } from "lucide-vue-next";

import { useAuthStore } from "@/stores/auth";
import { useSiteStore } from "@/stores/site";
import { API_BASE_URL } from "@/env";

const router = useRouter();
const auth = useAuthStore();
const site = useSiteStore();

const email = ref("admin@example.com");
const password = ref("admin12345");
const error = ref("");
const showPassword = ref(false);

function resolveUrl(url: string) {
  if (!url) return "";
  if (url.startsWith("http")) return url;
  return `${API_BASE_URL}${url}`;
}

onMounted(() => {
  if (!site.initialized) site.load();
});

async function submit() {
  error.value = "";
  try {
    await auth.signIn(email.value, password.value);
    router.push("/admin");
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Login failed";
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-[#f6f9fc] px-4">
    <div class="w-full max-w-[400px]">
      <!-- Logo -->
      <div class="mb-7 flex justify-center">
        <div v-if="site.siteIconUrl" class="flex h-8 items-center justify-center overflow-hidden">
          <img :src="resolveUrl(site.siteIconUrl)" alt="Site logo" class="h-full w-auto object-contain" />
        </div>
        <div v-else class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-violet-600 to-indigo-600">
          <Shield class="h-4 w-4 text-white" />
        </div>
      </div>

      <!-- Card -->
      <div class="rounded-lg border border-[#e3e8ee] bg-white px-10 pb-10 pt-8 shadow-[0_2px_4px_rgba(0,0,0,0.05),0_1px_2px_rgba(0,0,0,0.06)]">
        <h1 class="mb-1 text-center text-xl font-semibold tracking-tight text-[#1a1f36]">Sign in to your account</h1>
        <p class="mb-8 text-center text-[13px] text-[#697386]">{{ site.siteTitle || 'Admin' }} Dashboard</p>

        <!-- Form -->
        <form class="space-y-5" @submit.prevent="submit">
          <div class="space-y-1.5">
            <label class="text-[13px] font-medium text-[#1a1f36]">Email</label>
            <input
              v-model="email"
              type="email"
              class="w-full rounded-md border border-[#d8dee4] bg-white px-3 py-[9px] text-sm text-[#1a1f36] shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow placeholder:text-[#a3acb9] focus:border-[#5469d4] focus:outline-none focus:ring-2 focus:ring-[#5469d4]/20"
              placeholder="you@example.com"
            />
          </div>

          <div class="space-y-1.5">
            <label class="text-[13px] font-medium text-[#1a1f36]">Password</label>
            <div class="relative">
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                class="w-full rounded-md border border-[#d8dee4] bg-white px-3 py-[9px] pr-10 text-sm text-[#1a1f36] shadow-[0_1px_2px_rgba(0,0,0,0.04)] transition-shadow placeholder:text-[#a3acb9] focus:border-[#5469d4] focus:outline-none focus:ring-2 focus:ring-[#5469d4]/20"
                placeholder="Enter your password"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-[#a3acb9] transition-colors hover:text-[#697386]"
                @click="showPassword = !showPassword"
              >
                <EyeOff v-if="showPassword" class="h-4 w-4" />
                <Eye v-else class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="flex items-center gap-2 rounded-md border border-[#f8d7da] bg-[#fdf2f2] px-3.5 py-2.5 text-[13px] text-[#cd3d64]">
            <AlertCircle class="h-4 w-4 shrink-0" />
            {{ error }}
          </div>

          <button
            type="submit"
            class="flex w-full items-center justify-center gap-2 rounded-md bg-[#5469d4] px-4 py-[9px] text-sm font-medium text-white shadow-[0_1px_2px_rgba(0,0,0,0.08),0_2px_4px_rgba(0,0,0,0.04)] transition-all hover:bg-[#4558b8] disabled:opacity-60"
            :disabled="auth.loading"
          >
            {{ auth.loading ? 'Signing in...' : 'Continue' }}
            <ArrowRight v-if="!auth.loading" class="h-4 w-4" />
          </button>
        </form>
      </div>

      <!-- Footer -->
      <p class="mt-8 text-center text-[12px] text-[#8792a2]">&copy; {{ new Date().getFullYear() }} {{ site.siteTitle || 'Admin' }}</p>
    </div>
  </div>
</template>
