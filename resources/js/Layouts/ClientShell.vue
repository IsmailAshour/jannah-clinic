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
  LogIn,
} from 'lucide-vue-next'
import { NotificationBell, AuthGuardLink } from '@/Components/foundation'

defineProps({
  // Pages that render their own brand/auth/notification controls at the top
  // (e.g. Home's hero) set this false to avoid duplicating logo + bell + auth.
  showTopBar: { type: Boolean, default: true },
  // Lets the page bleed a gradient/coloured background all the way to the
  // viewport edges instead of the default flat surface-page colour.
  fullBleed: { type: Boolean, default: false },
})

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const isAuthed = computed(() => authedUser.value !== null)
const isStaff = computed(() => {
  const role = authedUser.value?.role
  return role === 'manager' || role === 'doctor' || role === 'receptionist'
})
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
  <div :class="['min-h-screen flex flex-col', fullBleed ? '' : 'bg-surface-page']">
    <header v-if="showTopBar" class="h-14 flex items-center px-4 border-b border-border-default bg-surface-card gap-2 max-w-3xl w-full mx-auto">
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
        <Link
          href="/login"
          class="ms-auto inline-flex items-center justify-center w-10 h-10 rounded-full bg-brand/10 text-brand hover:bg-brand/15 transition focus:outline-none focus:ring-2 focus:ring-brand"
          aria-label="تسجيل الدخول أو إنشاء حساب"
          title="تسجيل الدخول أو إنشاء حساب"
        >
          <LogIn class="w-5 h-5 rtl:rotate-180" aria-hidden="true" />
        </Link>
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

          <!-- Center FAB: booking. Staff → admin on-behalf flow; customer → portal booking; guest → /login?intent=booking. -->
          <div class="flex justify-center">
            <Link
              v-if="isStaff"
              href="/admin/booking"
              aria-label="حجز موعد لعميل"
              class="-translate-y-5 w-14 h-14 rounded-full bg-brand text-white shadow-lg flex items-center justify-center hover:opacity-90 active:opacity-100 transition"
            >
              <CalendarPlus class="w-6 h-6" aria-hidden="true" />
            </Link>
            <AuthGuardLink
              v-else
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
