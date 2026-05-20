<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { NotificationBell } from '@/Components/foundation'
// TODO(P1): switch to Inertia persistent layout (defineOptions layout) to preserve bottom-nav/shell state across navigation.
const tabs = [
  { label: 'الرئيسية', href: '/portal', real: true },
  { label: 'الخدمات', href: '/portal/services', real: true },
  { label: 'الحجز', href: '/portal/booking', real: true },
  { label: 'مواعيدي', href: '/portal/appointments', real: true },
  { label: 'سجلي الطبي', href: '/portal/medical-record', real: true },
]
const page = usePage()
function isActive(t) {
  if (!t.real) return false
  const current = page.url
  if (t.href === '/portal') return current === '/portal' || current === '/portal/'
  return current === t.href || current.startsWith(t.href + '/')
}
</script>
<template>
  <div class="min-h-screen mx-auto max-w-md flex flex-col bg-surface-page">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card">
      <span class="font-bold text-brand">عيادة جنّة</span>
      <NotificationBell href="/portal/notifications" class="ms-auto me-2" />
      <Link href="/logout" method="post" as="button" class="text-xs text-text-secondary">خروج</Link>
    </header>
    <main class="flex-1 pb-20"><slot /></main>
    <nav class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid grid-cols-5">
      <template v-for="t in tabs" :key="t.label">
        <Link v-if="t.real" :href="t.href"
              :aria-current="isActive(t) ? 'page' : undefined"
              :class="['py-3 text-center text-xs hover:text-brand transition', isActive(t) ? 'text-brand font-semibold' : 'text-text-secondary']">{{ t.label }}</Link>
        <span v-else aria-disabled="true"
              class="py-3 text-center text-xs text-text-secondary/40 cursor-not-allowed select-none">{{ t.label }}</span>
      </template>
    </nav>
  </div>
</template>
