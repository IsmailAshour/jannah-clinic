<script setup>
import { Link } from '@inertiajs/vue3'
const nav = [{ label: 'لوحة التحكم', href: '/admin' }]
</script>
<template>
  <div class="min-h-screen flex bg-surface-page">
    <aside class="z-shell w-64 shrink-0 bg-brand text-white p-4 space-y-2">
      <div class="text-lg font-bold pb-4">عيادة جنّة</div>
      <Link v-for="n in nav" :key="n.href" :href="n.href"
            class="block rounded-[var(--radius-md)] px-3 py-2 hover:bg-white/10 transition">{{ n.label }}</Link>
    </aside>
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="z-sticky h-16 bg-surface-card border-b border-border-default flex items-center px-6">
        <div class="ms-auto">
          <Link href="/logout" method="post" as="button" class="text-sm text-text-secondary hover:text-text-primary">تسجيل الخروج</Link>
        </div>
      </header>
      <main class="flex-1"><slot /></main>
    </div>
  </div>
</template>
