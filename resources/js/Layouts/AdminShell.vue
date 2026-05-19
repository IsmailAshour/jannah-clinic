<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { onMounted, onBeforeUnmount, ref, watch } from 'vue'
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
  {
    label: 'العيادة',
    children: [
      { label: 'الأطباء', href: '/admin/doctors' },
      { label: 'المواعيد', href: '/admin/appointments' },
      { label: 'حجز موعد  لعميل', href: '/admin/booking' },
    ],
  },
  { label: 'مناطق التغطية', href: '/admin/coverage' },
  { label: 'الإعدادات', href: '/admin/settings' },
]
const page = usePage()
function isActive(href) {
  const current = page.url
  if (href === '/admin') return current === '/admin' || current === '/admin/'
  return current === href || current.startsWith(href + '/')
}

const STORAGE_KEY = 'jannah.adminSidebarCollapsed'

// Desktop (≥ lg): user-collapsible, persisted in localStorage.
const collapsed = ref(false)
// Mobile (< lg): off-canvas overlay drawer.
const open = ref(false)

function toggleCollapsed() {
  collapsed.value = !collapsed.value
}
function openDrawer() {
  open.value = true
}
function closeDrawer() {
  open.value = false
}
function onKeydown(e) {
  if (e.key === 'Escape' && open.value) closeDrawer()
}

watch(collapsed, (val) => {
  if (typeof window === 'undefined') return
  try {
    window.localStorage.setItem(STORAGE_KEY, val ? '1' : '0')
  } catch {
    /* storage unavailable (private mode / quota) — non-fatal */
  }
})

const aside = ref(null)

onMounted(() => {
  if (typeof window !== 'undefined') {
    try {
      collapsed.value = window.localStorage.getItem(STORAGE_KEY) === '1'
    } catch {
      /* storage unavailable — keep default */
    }
    window.addEventListener('keydown', onKeydown)
  }
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', onKeydown)
  }
})

// Move focus into the drawer when it opens on mobile.
watch(open, async (isOpen) => {
  if (!isOpen || typeof window === 'undefined') return
  await Promise.resolve()
  aside.value?.focus?.()
})
</script>
<template>
  <div class="min-h-screen flex bg-surface-page">
    <!-- Backdrop: mobile-only, sits below the drawer but above the sticky header. -->
    <div v-if="open"
         class="z-overlay fixed inset-0 bg-black/40 lg:hidden"
         @click="closeDrawer"></div>

    <!--
      Single markup serves both modes:
      - mobile (< lg): fixed off-canvas overlay drawer, slid out via RTL-aware
        translate; `open` controls visibility.
      - desktop (≥ lg): in-flow column; `collapsed` controls width (w-64 ↔ w-0).
    -->
    <aside id="admin-sidebar"
           ref="aside"
           tabindex="-1"
           :aria-hidden="!open && undefined"
           role="dialog"
           aria-modal="true"
           aria-label="القائمة"
           :class="[
             'z-overlay fixed inset-block-0 inset-inline-start-0 w-64 bg-brand text-white p-4 space-y-1 overflow-y-auto transition-transform duration-[var(--duration-normal)]',
             open ? 'translate-x-0' : 'ltr:-translate-x-full rtl:translate-x-full',
             'lg:z-shell lg:static lg:translate-x-0 lg:transition-[width,padding] lg:overflow-hidden lg:shrink-0',
             collapsed ? 'lg:w-0 lg:p-0' : 'lg:w-64 lg:p-4',
           ]">
      <div class="text-lg font-bold pb-4 whitespace-nowrap">عيادة جنّة</div>
      <template v-for="n in nav" :key="n.label">
        <Link v-if="n.href" :href="n.href"
              @click="closeDrawer"
              :aria-current="isActive(n.href) ? 'page' : undefined"
              :class="['block rounded-[var(--radius-md)] px-3 py-2 hover:bg-white/10 transition whitespace-nowrap', isActive(n.href) ? 'bg-white/15 font-semibold' : '']">{{ n.label }}</Link>
        <div v-else>
          <div class="px-3 pt-3 pb-1 text-xs font-semibold tracking-wide text-white/60 whitespace-nowrap">{{ n.label }}</div>
          <Link v-for="c in n.children" :key="c.href" :href="c.href"
                @click="closeDrawer"
                :aria-current="isActive(c.href) ? 'page' : undefined"
                :class="['block rounded-[var(--radius-md)] ps-6 pe-3 py-2 hover:bg-white/10 transition whitespace-nowrap', isActive(c.href) ? 'bg-white/15 font-semibold' : '']">{{ c.label }}</Link>
        </div>
      </template>
    </aside>
    <div class="flex-1 min-w-0 flex flex-col">
      <header class="z-sticky h-16 bg-surface-card border-b border-border-default flex items-center px-6">
        <!-- Inline-start controls (logical order; me-auto pushes logout to the end). -->
        <button type="button"
                class="lg:hidden -ms-2 me-auto p-2 rounded-[var(--radius-md)] text-text-secondary hover:text-text-primary hover:bg-surface-sunken transition"
                aria-label="القائمة"
                aria-controls="admin-sidebar"
                :aria-expanded="open"
                @click="openDrawer">
          <span aria-hidden="true" class="block w-5 space-y-1">
            <span class="block h-0.5 bg-current"></span>
            <span class="block h-0.5 bg-current"></span>
            <span class="block h-0.5 bg-current"></span>
          </span>
        </button>
        <button type="button"
                class="hidden lg:inline-flex me-auto p-2 rounded-[var(--radius-md)] text-text-secondary hover:text-text-primary hover:bg-surface-sunken transition"
                :aria-label="collapsed ? 'إظهار القائمة' : 'طيّ القائمة'"
                aria-controls="admin-sidebar"
                :aria-expanded="!collapsed"
                @click="toggleCollapsed">
          <span aria-hidden="true" class="block w-5 space-y-1">
            <span class="block h-0.5 bg-current"></span>
            <span class="block h-0.5 bg-current"></span>
            <span class="block h-0.5 bg-current"></span>
          </span>
        </button>
        <div class="ms-auto">
          <Link href="/logout" method="post" as="button" class="text-sm text-text-secondary hover:text-text-primary">تسجيل الخروج</Link>
        </div>
      </header>
      <main class="flex-1"><slot /></main>
    </div>
  </div>
</template>
