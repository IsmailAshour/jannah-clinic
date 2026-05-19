<script setup>
import { Link, usePage } from '@inertiajs/vue3'
// TODO(P1): consider Inertia persistent layout (defineOptions layout) to keep shell state across navigations.
const nav = [
  { label: 'لوحة التحكم', href: '/admin' },
  {
    label: 'الخدمات',
    children: [
      { label: 'تصنيفات الخدمات', href: '/admin/catalog/categories' },
      { label: 'الخدمات', href: '/admin/catalog/services' },
    ],
  },
  { label: 'الأطباء', href: '/admin/doctors' },
  { label: 'مناطق التغطية', href: '/admin/coverage' },
  { label: 'الإعدادات', href: '/admin/settings' },
]
const page = usePage()
function isActive(href) {
  const current = page.url
  if (href === '/admin') return current === '/admin' || current === '/admin/'
  return current === href || current.startsWith(href + '/')
}
</script>
<template>
  <div class="min-h-screen flex bg-surface-page">
    <aside class="z-shell w-64 shrink-0 bg-brand text-white p-4 space-y-1">
      <div class="text-lg font-bold pb-4">عيادة جنّة</div>
      <template v-for="n in nav" :key="n.label">
        <Link v-if="n.href" :href="n.href"
              :aria-current="isActive(n.href) ? 'page' : undefined"
              :class="['block rounded-[var(--radius-md)] px-3 py-2 hover:bg-white/10 transition', isActive(n.href) ? 'bg-white/15 font-semibold' : '']">{{ n.label }}</Link>
        <div v-else>
          <div class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-white/60">{{ n.label }}</div>
          <Link v-for="c in n.children" :key="c.href" :href="c.href"
                :aria-current="isActive(c.href) ? 'page' : undefined"
                :class="['block rounded-[var(--radius-md)] ps-6 pe-3 py-2 hover:bg-white/10 transition', isActive(c.href) ? 'bg-white/15 font-semibold' : '']">{{ c.label }}</Link>
        </div>
      </template>
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
