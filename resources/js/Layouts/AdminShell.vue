<script setup>
import { Link, usePage } from '@inertiajs/vue3'
// TODO(P1): consider Inertia persistent layout (defineOptions layout) to keep shell state across navigations.
const nav = [
  { label: 'لوحة التحكم', href: '/admin' },
  { label: 'تصنيفات الخدمات', href: '/admin/catalog/categories' },
  { label: 'الخدمات', href: '/admin/catalog/services' },
  { label: 'الأطباء', href: '/admin/doctors' },
  { label: 'مناطق التغطية', href: '/admin/coverage' },
  { label: 'الإعدادات', href: '/admin/settings' },
]
const page = usePage()
function isActive(n) {
  const current = page.url
  if (n.href === '/admin') return current === '/admin' || current === '/admin/'
  return current === n.href || current.startsWith(n.href + '/')
}
</script>
<template>
  <div class="min-h-screen flex bg-surface-page">
    <aside class="z-shell w-64 shrink-0 bg-brand text-white p-4 space-y-2">
      <div class="text-lg font-bold pb-4">عيادة جنّة</div>
      <Link v-for="n in nav" :key="n.href" :href="n.href"
            :aria-current="isActive(n) ? 'page' : undefined"
            :class="['block rounded-[var(--radius-md)] px-3 py-2 hover:bg-white/10 transition', isActive(n) ? 'bg-white/15 font-semibold' : '']">{{ n.label }}</Link>
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
