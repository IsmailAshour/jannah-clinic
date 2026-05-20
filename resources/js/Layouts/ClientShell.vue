<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { NotificationBell } from '@/Components/foundation'

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)

const guestTabs = [
  { label: 'الرئيسية', href: '/' },
  { label: 'الخدمات', href: '/services' },
  { label: 'الأطبّاء', href: '/doctors' },
  { label: 'الدعم', href: '/support' },
]

const authedTabs = [
  { label: 'الرئيسية', href: '/' },
  { label: 'مواعيدي', href: '/portal/appointments' },
  { label: 'سجلي', href: '/portal/medical-record' },
  { label: 'نقاطي', href: '/portal/loyalty' },
  { label: 'حسابي', href: '/portal/profile' },
  { label: 'خدمات', href: '/services' },
]

const tabs = computed(() => (isAuthed.value ? authedTabs : guestTabs))

function isActive(href) {
  const current = page.url
  if (href === '/') return current === '/' || current === ''
  return current === href || current.startsWith(href + '/')
}
</script>

<template>
  <div class="min-h-screen mx-auto max-w-md flex flex-col bg-surface-page">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card gap-2">
      <span class="font-bold text-brand">عيادة جنّة</span>

      <template v-if="isAuthed">
        <NotificationBell href="/portal/notifications" class="ms-auto" />
        <span class="text-xs text-text-secondary truncate max-w-24">{{ authedUser.name }}</span>
        <Link href="/logout" method="post" as="button" class="text-xs text-text-secondary">خروج</Link>
      </template>

      <template v-else>
        <div class="ms-auto flex items-center gap-2">
          <Link href="/login" class="text-xs text-text-secondary hover:text-brand">تسجيل الدخول</Link>
          <Link href="/register" class="text-xs font-semibold text-brand">إنشاء حساب</Link>
        </div>
      </template>
    </header>

    <main class="flex-1 pb-20"><slot /></main>

    <nav
      class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid"
      :class="isAuthed ? 'grid-cols-6' : 'grid-cols-4'"
    >
      <Link
        v-for="t in tabs"
        :key="t.label"
        :href="t.href"
        :aria-current="isActive(t.href) ? 'page' : undefined"
        :class="['py-3 text-center text-xs hover:text-brand transition', isActive(t.href) ? 'text-brand font-semibold' : 'text-text-secondary']"
      >{{ t.label }}</Link>
    </nav>
  </div>
</template>
