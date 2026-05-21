<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowRight } from 'lucide-vue-next'

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')
const clinicLogo = computed(() => {
  const path = page.props?.clinic?.logo_path
  return path ? `/storage/${path}` : '/images/clinic-logo.jpg'
})
</script>

<template>
  <div
    dir="rtl"
    class="min-h-screen flex flex-col items-center justify-start sm:justify-center bg-gradient-to-bl from-brand/10 via-surface-page to-brand/5 px-4 py-8"
  >
    <!-- Brand header -->
    <Link href="/" class="flex flex-col items-center gap-3 group" aria-label="العودة للصفحة الرئيسية">
      <img
        :src="clinicLogo"
        :alt="clinicName"
        class="h-20 w-20 rounded-full object-cover ring-4 ring-white shadow-md transition group-hover:scale-105"
      />
      <span class="text-lg font-extrabold text-brand">{{ clinicName }}</span>
    </Link>

    <!-- Card -->
    <main class="mt-6 w-full sm:max-w-md">
      <div class="bg-surface-card rounded-2xl shadow-lg ring-1 ring-border-default p-6 sm:p-8">
        <slot />
      </div>

      <!-- Back to home -->
      <p class="mt-4 text-center text-xs text-text-tertiary">
        <Link href="/" class="inline-flex items-center gap-1 hover:text-brand transition">
          <ArrowRight class="h-3.5 w-3.5 rtl:rotate-180" aria-hidden="true" />
          <span>العودة للصفحة الرئيسية</span>
        </Link>
      </p>
    </main>
  </div>
</template>
