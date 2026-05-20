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
  CalendarPlus,
} from 'lucide-vue-next'
import { NotificationBell, AuthGuardLink } from '@/Components/foundation'

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')
const clinicLogoPath = computed(() => page.props?.clinic?.logo_path ?? null)
const clinicLogoUrl = computed(() => clinicLogoPath.value ? `/storage/${clinicLogoPath.value}` : null)

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
const leftTabs = computed(() => tabs.value.slice(0, 2))
const rightTabs = computed(() => tabs.value.slice(2, 4))

function isActive(href) {
  const current = page.url
  if (href === '/') return current === '/' || current === ''
  return current === href || current.startsWith(href + '/')
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-surface-page">
    <header class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card gap-2 max-w-3xl w-full mx-auto">
      <Link href="/" class="flex items-center gap-1.5">
        <img
          v-if="clinicLogoUrl"
          :src="clinicLogoUrl"
          :alt="clinicName"
          class="w-7 h-7 rounded-md object-cover"
        />
        <Heart v-else class="w-5 h-5 text-brand" aria-hidden="true" />
        <span class="font-bold text-brand">{{ clinicName }}</span>
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

    <main class="flex-1 pb-24 max-w-3xl w-full mx-auto"><slot /></main>

    <nav class="z-shell fixed bottom-0 inset-inline-0 w-full bg-surface-card border-t border-border-default">
      <div class="relative max-w-3xl mx-auto">
        <div class="grid grid-cols-5 items-end">
          <Link
            v-for="t in leftTabs"
            :key="t.label"
            :href="t.href"
            :aria-current="isActive(t.href) ? 'page' : undefined"
            :class="['py-2 flex flex-col items-center gap-0.5 text-xs hover:text-brand transition', isActive(t.href) ? 'text-brand font-semibold' : 'text-text-secondary']"
          >
            <component :is="t.icon" class="w-5 h-5" aria-hidden="true" />
            <span>{{ t.label }}</span>
          </Link>

          <div class="flex justify-center">
            <AuthGuardLink
              intent="booking"
              authed-href="/portal/booking"
              aria-label="احجز موعد"
              class="-translate-y-5 w-14 h-14 rounded-full bg-brand text-white shadow-lg flex items-center justify-center hover:opacity-90 active:opacity-100 transition"
            >
              <CalendarPlus class="w-6 h-6" aria-hidden="true" />
            </AuthGuardLink>
          </div>

          <Link
            v-for="t in rightTabs"
            :key="t.label"
            :href="t.href"
            :aria-current="isActive(t.href) ? 'page' : undefined"
            :class="['py-2 flex flex-col items-center gap-0.5 text-xs hover:text-brand transition', isActive(t.href) ? 'text-brand font-semibold' : 'text-text-secondary']"
          >
            <component :is="t.icon" class="w-5 h-5" aria-hidden="true" />
            <span>{{ t.label }}</span>
          </Link>
        </div>
      </div>
    </nav>
  </div>
</template>
