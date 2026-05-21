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
  LogOut,
} from 'lucide-vue-next'
import { AuthGuardLink } from '@/Components/foundation'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const unreadCount = computed(() => page.props?.notifications?.unread_count ?? 0)
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
  <!-- Whole-viewport radial gradient at the top (full screen width — not
       constrained to the centered max-w container). Fades into surface-page
       below so the rest of the page reads as a normal flat surface. -->
  <div
    class="min-h-screen flex flex-col"
    :style="{ background: 'radial-gradient(140% 55% at 50% 0%, color-mix(in oklab, var(--color-brand) 15%, var(--color-surface-page)) 0%, color-mix(in oklab, var(--color-warning) 8%, var(--color-surface-page)) 35%, var(--color-surface-page) 75%)' }"
  >
    <header class="h-14 flex items-center px-4 gap-2 max-w-3xl w-full mx-auto">
      <Link href="/" class="flex items-center gap-2 min-w-0">
        <img
          v-if="clinicLogoUrl"
          :src="clinicLogoUrl"
          :alt="clinicName"
          class="h-10 w-auto max-w-10 object-contain shrink-0"
        />
        <Heart v-else class="w-5 h-5 text-brand shrink-0" aria-hidden="true" />
        <span class="font-extrabold text-brand truncate">{{ clinicName }}</span>
      </Link>

      <div class="ms-auto inline-flex items-center gap-2">
        <template v-if="isAuthed">
          <!-- Notification bell — unified circle styling matches profile trigger -->
          <Link
            href="/portal/notifications"
            aria-label="الإشعارات"
            class="relative inline-flex items-center justify-center w-10 h-10 rounded-full bg-surface-card ring-2 ring-brand/20 shadow-sm text-brand hover:bg-brand/5 transition focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <Bell class="w-5 h-5" aria-hidden="true" />
            <span
              v-if="unreadCount > 0"
              data-testid="bell-badge"
              class="absolute -top-1 -end-1 min-w-5 h-5 px-1 rounded-full bg-danger text-white text-[10px] font-bold grid place-items-center ring-2 ring-surface-card"
            >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
          </Link>

          <!-- Profile dropdown — same circle treatment as the bell for visual parity -->
          <DropdownMenu>
            <DropdownMenuTrigger as-child>
              <button
                type="button"
                aria-label="قائمة الحساب"
                class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-surface-card ring-2 ring-brand/20 shadow-sm text-brand hover:bg-brand/5 transition focus:outline-none focus:ring-2 focus:ring-brand"
              >
                <User class="w-5 h-5" aria-hidden="true" />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-56 p-1">
              <!-- User header: avatar + name + role -->
              <div class="flex items-center gap-2.5 px-2 py-2.5">
                <div class="w-9 h-9 rounded-full bg-brand/10 text-brand grid place-items-center font-bold shrink-0">
                  {{ authedUser?.name ? Array.from(authedUser.name)[0] : 'م' }}
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-bold text-text-primary truncate">{{ authedUser.name }}</p>
                  <p class="text-xs text-text-tertiary truncate" dir="ltr">{{ authedUser.email || authedUser.phone || '' }}</p>
                </div>
              </div>
              <DropdownMenuSeparator />
              <DropdownMenuItem as-child>
                <Link
                  href="/portal/profile"
                  class="flex items-center gap-2 w-full cursor-pointer rounded-md px-2 py-2 text-sm text-text-primary hover:bg-brand/5 hover:text-brand transition"
                >
                  <User class="w-4 h-4 text-text-secondary" aria-hidden="true" />
                  <span>البروفايل</span>
                </Link>
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem as-child>
                <Link
                  href="/logout"
                  method="post"
                  as="button"
                  class="flex items-center gap-2 w-full cursor-pointer rounded-md px-2 py-2 text-sm text-danger hover:bg-danger/5 transition"
                >
                  <LogOut class="w-4 h-4" aria-hidden="true" />
                  <span>تسجيل الخروج</span>
                </Link>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </template>

        <template v-else>
          <!-- Guest: single LogIn icon, same circle style for consistency -->
          <Link
            href="/login"
            aria-label="تسجيل الدخول أو إنشاء حساب"
            title="تسجيل الدخول أو إنشاء حساب"
            class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-surface-card ring-2 ring-brand/20 shadow-sm text-brand hover:bg-brand/5 transition focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <LogIn class="w-5 h-5 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </template>
      </div>
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
