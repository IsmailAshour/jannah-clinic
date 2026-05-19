<script setup>
import { Link } from '@inertiajs/vue3'
const tabs = [
  { label: 'الرئيسية', href: '/portal' },
  { label: 'الحجز', href: '/portal' },
  { label: 'الإشعارات', href: '/portal' },
  { label: 'حسابي', href: '/portal' },
]
</script>
<template>
  <div class="min-h-screen mx-auto max-w-md flex flex-col bg-surface-page">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card">
      <span class="font-bold text-brand">عيادة جنّة</span>
      <Link href="/logout" method="post" as="button" class="ms-auto text-xs text-text-secondary">خروج</Link>
    </header>
    <main class="flex-1 p-4 pb-20"><slot /></main>
    <nav class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid grid-cols-4">
      <Link v-for="(t,i) in tabs" :key="i" :href="t.href" class="py-3 text-center text-xs text-text-secondary hover:text-brand">{{ t.label }}</Link>
    </nav>
  </div>
</template>
