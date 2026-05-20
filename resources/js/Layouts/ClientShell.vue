<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
  Home as HomeIcon,
  CalendarDays,
  Bell,
  User,
  Briefcase,
  Stethoscope,
  HelpCircle,
  Heart,
} from 'lucide-vue-next'
import { NotificationBell } from '@/Components/foundation'

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)

const guestTabs = [
  { label: 'الرئيسية', href: '/', icon: HomeIcon },
  { label: 'الخدمات', href: '/services', icon: Briefcase },
  { label: 'الأطبّاء', href: '/doctors', icon: Stethoscope },
  { label: 'الدعم', href: '/support', icon: HelpCircle },
]

const authedTabs = [
  { label: 'الرئيسية', href: '/', icon: HomeIcon },
  { label: 'مواعيدي', href: '/portal/appointments', icon: CalendarDays },
  { label: 'الإشعارات', href: '/portal/notifications', icon: Bell },
  { label: 'البروفايل', href: '/portal/profile', icon: User },
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
      <Link href="/" class="flex items-center gap-1.5">
        <Heart class="w-5 h-5 text-brand" aria-hidden="true" />
        <span class="font-bold text-brand">عيادة جنّة</span>
      </Link>

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
      class="z-shell fixed bottom-0 inset-inline-0 mx-auto max-w-md bg-surface-card border-t border-border-default grid grid-cols-4"
    >
      <Link
        v-for="t in tabs"
        :key="t.label"
        :href="t.href"
        :aria-current="isActive(t.href) ? 'page' : undefined"
        :class="['py-2 flex flex-col items-center gap-0.5 text-xs hover:text-brand transition', isActive(t.href) ? 'text-brand font-semibold' : 'text-text-secondary']"
      >
        <component :is="t.icon" class="w-5 h-5" aria-hidden="true" />
        <span>{{ t.label }}</span>
      </Link>
    </nav>
  </div>
</template>
