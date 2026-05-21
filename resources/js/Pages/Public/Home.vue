<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Bell, LogIn, Sparkles, Star, User as UserIcon } from 'lucide-vue-next'

const TEAM_ROLE_LABEL = {
  doctor: 'طبيب',
  nurse: 'ممرّض',
  physiotherapist: 'أخصّائي علاج طبيعي',
}
function roleLabel(d) {
  return TEAM_ROLE_LABEL[d.team_role] ?? 'طبيب'
}
import ClientShell from '@/Layouts/ClientShell.vue'
import { AuthGuardLink } from '@/Components/foundation'
import { iconForCategory } from '@/lib/categoryIcons'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  featuredServices: { type: Array, default: () => [] },
  doctors: { type: Array, default: () => [] },
  tip: { type: [String, Object], default: null },
  greetingName: { type: String, default: null },
  upcomingAppointments: { type: Array, default: () => [] },
  loyaltyBalance: { type: Number, default: 0 },
})

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')
const clinicLogoUrl = computed(() => {
  const p = page.props?.clinic?.logo_path
  return p ? `/storage/${p}` : null
})
const clinicInitial = computed(() => {
  const n = (clinicName.value ?? '').trim()
  return n ? Array.from(n)[0] : 'ج'
})
const isAuthed = computed(() => !!page.props?.auth?.user)
const unreadCount = computed(() => page.props?.notifications?.unread_count ?? 0)
const notificationsHref = computed(() => isAuthed.value ? '/portal/notifications' : '/login?intent=notifications')

const statusLabel = {
  requested: 'بانتظار التأكيد',
  confirmed: 'مؤكَّد',
}

const POINTS_PER_SESSION = 1500
const pointsToNextSession = computed(() => Math.max(0, POINTS_PER_SESSION - props.loyaltyBalance))

function formatDateAr(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}
function formatTimeAr(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}

const tipText = computed(() => {
  if (!props.tip) return null
  if (typeof props.tip === 'string') return { title: 'نصيحة اليوم', body: props.tip }
  return props.tip
})
</script>

<template>
  <ClientShell :show-top-bar="false" full-bleed>
    <!-- Whole-page radial gradient surface — fills under the bottom-nav too because
         ClientShell.fullBleed drops the bg-surface-page so this wrapper paints
         everything. Brand 18% from top-centre fading through warning 8% to white. -->
    <div
      class="min-h-[calc(100vh-4rem)]"
      :style="{ background: 'radial-gradient(120% 50% at 50% 0%, color-mix(in oklab, var(--color-brand) 18%, white) 0%, color-mix(in oklab, var(--color-warning) 10%, white) 45%, white 90%)' }"
    >
      <!-- Hero header — brand badge + bell + greeting (replaces ClientShell's top bar on this page). -->
      <section class="px-5 pt-6 pb-4">
        <div class="flex items-center justify-between">
          <!-- Brand: bare logo image + clinic name beside it -->
          <Link href="/" class="flex items-center gap-2 min-w-0">
            <img
              v-if="clinicLogoUrl"
              :src="clinicLogoUrl"
              :alt="clinicName"
              class="h-12 w-auto max-w-12 object-contain shrink-0"
            />
            <span v-else class="h-12 w-12 grid place-items-center text-brand text-xl font-extrabold shrink-0">{{ clinicInitial }}</span>
            <span class="text-base font-extrabold text-brand truncate">{{ clinicName }}</span>
          </Link>

          <!-- Notification bell (authed) OR Login icon (guest) -->
          <Link
            v-if="isAuthed"
            :href="notificationsHref"
            :aria-label="`الإشعارات${unreadCount > 0 ? ` (${unreadCount} غير مقروءة)` : ''}`"
            class="relative w-12 h-12 rounded-full bg-surface-card ring-2 ring-brand/20 shadow-sm grid place-items-center text-brand hover:bg-brand/5 transition"
          >
            <Bell class="w-5 h-5" aria-hidden="true" />
            <span
              v-if="unreadCount > 0"
              class="absolute -top-1 -end-1 min-w-5 h-5 px-1 rounded-full bg-danger text-white text-[10px] font-bold grid place-items-center ring-2 ring-surface-card"
            >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
          </Link>
          <Link
            v-else
            href="/login"
            aria-label="تسجيل الدخول أو إنشاء حساب"
            title="تسجيل الدخول أو إنشاء حساب"
            class="w-12 h-12 rounded-full bg-surface-card ring-2 ring-brand/20 shadow-sm grid place-items-center text-brand hover:bg-brand/5 transition"
          >
            <LogIn class="w-5 h-5 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </div>

        <header class="mt-5 space-y-1.5">
          <h1 class="text-3xl leading-tight font-extrabold text-brand">
            أهلًا{{ greetingName ? `، ${greetingName}` : ' بك' }}!
          </h1>
          <p class="text-sm text-text-secondary">أهلًا بك في {{ clinicName }} — اعتنِ بصحّتك وجمالك.</p>
        </header>
      </section>

      <div class="px-5 pt-2 pb-10 space-y-6">

      <!-- Upcoming appointments (authed only) -->
      <section v-if="isAuthed">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-bold text-text-primary">مواعيدك القادمة</h2>
          <Link href="/portal/appointments" class="text-sm font-bold text-brand inline-flex items-center gap-1">
            عرض الكل <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </div>

        <div v-if="upcomingAppointments.length === 0" class="bg-surface-card rounded-2xl border-2 border-brand/15 p-5 text-sm text-text-secondary">
          لا مواعيد قادمة.
          <AuthGuardLink intent="booking" authed-href="/portal/booking" staff-href="/admin/booking" class="font-bold text-brand">احجز الآن</AuthGuardLink>
        </div>

        <ul v-else class="space-y-3">
          <li
            v-for="(a, i) in upcomingAppointments"
            :key="a.id"
            :class="['bg-surface-card rounded-2xl border-2 p-4 space-y-1.5', i === 0 ? 'border-brand/30' : 'border-border-default']"
          >
            <h3 class="text-base font-extrabold text-text-primary">{{ a.service?.name }}</h3>
            <p class="text-sm text-text-secondary">
              {{ formatDateAr(a.start_at) }} • {{ formatTimeAr(a.start_at) }} • {{ a.doctor?.user?.name }}
            </p>
            <div class="flex items-center justify-between pt-1">
              <span :class="['inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border',
                a.status === 'confirmed' ? 'bg-brand/10 text-brand border-brand/20' : 'bg-warning/10 text-warning border-warning/30']">
                {{ statusLabel[a.status] ?? a.status }}
              </span>
              <Link
                v-if="a.payment"
                :href="`/portal/appointments/${a.id}/payment`"
                class="text-xs font-bold text-brand"
              >إدارة الدفع ←</Link>
            </div>
          </li>
        </ul>
      </section>

      <!-- Big CTA card — gradient with decorative orbs (reference parity) -->
      <AuthGuardLink
        intent="booking"
        authed-href="/portal/booking"
        staff-href="/admin/booking"
        class="block relative overflow-hidden rounded-2xl p-5 text-white shadow-lg ring-2 ring-warning/60"
        :style="{ background: 'linear-gradient(135deg, color-mix(in oklab, var(--color-brand) 100%, black 10%) 0%, var(--color-brand) 60%, color-mix(in oklab, var(--color-brand) 80%, white 15%) 100%)' }"
      >
        <!-- Decorative translucent orbs (inline-start side, decorative only) -->
        <span aria-hidden="true" class="pointer-events-none absolute -top-4 start-6 w-20 h-20 rounded-full bg-white/10" />
        <span aria-hidden="true" class="pointer-events-none absolute top-12 start-0 w-16 h-16 rounded-full bg-white/8" />
        <span aria-hidden="true" class="pointer-events-none absolute -bottom-6 start-16 w-24 h-24 rounded-full bg-white/5" />

        <div class="relative">
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-warning text-brand text-xs font-extrabold">
            <Sparkles class="w-3 h-3" aria-hidden="true" />
            عرض خاص
          </span>
          <h3 class="mt-3 text-lg font-extrabold leading-snug">احجز موعدك بسهولة</h3>
          <p class="mt-1 text-sm text-white/85">اختر الخدمة والطبيب والوقت المناسب — كل ذلك في دقائق.</p>

          <div class="mt-4 flex justify-end">
            <span class="inline-flex items-center gap-1 px-4 py-2 rounded-full bg-white text-brand text-sm font-extrabold shadow-sm hover:bg-warning hover:text-brand transition">
              احجز الآن
              <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
            </span>
          </div>
        </div>
      </AuthGuardLink>

      <!-- Categories (iconic squares) -->
      <section v-if="categories.length > 0">
        <h2 class="text-base font-bold text-text-primary mb-3">تصفّح حسب الفئة</h2>
        <div class="grid grid-cols-2 gap-3">
          <Link
            v-for="c in categories"
            :key="c.id"
            :href="`/services?category=${c.id}`"
            class="bg-surface-card rounded-2xl p-4 flex flex-col items-center gap-2 border-2 border-brand/15 hover:border-brand/40 transition"
          >
            <span class="w-12 h-12 rounded-full bg-brand/10 text-brand grid place-items-center">
              <component :is="iconForCategory(c)" class="w-6 h-6" aria-hidden="true" />
            </span>
            <span class="text-sm font-bold text-text-primary text-center">{{ c.name }}</span>
            <span class="text-[11px] text-text-tertiary">{{ c.services_count }} خدمة</span>
          </Link>
        </div>
      </section>

      <!-- Featured services (with images) -->
      <section v-if="featuredServices.length > 0">
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-bold text-text-primary">خدمات مميّزة</h2>
          <Link href="/services" class="text-sm font-bold text-brand inline-flex items-center gap-1">
            عرض الكل
            <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </div>
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <li v-for="s in featuredServices" :key="s.id">
            <Link
              :href="`/services/${s.id}`"
              class="block bg-surface-card rounded-2xl shadow-sm overflow-hidden border-2 border-brand/10 hover:border-brand/40 hover:shadow-md transition"
            >
              <div
                v-if="s.image_path"
                class="w-full aspect-[16/9] bg-cover bg-center"
                :style="{ backgroundImage: `url(/storage/${s.image_path})` }"
              />
              <div v-else class="w-full aspect-[16/9] flex items-center justify-center bg-brand/10 text-brand">
                <component :is="iconForCategory(s.category)" class="w-12 h-12" aria-hidden="true" />
              </div>
              <div class="p-3 space-y-1">
                <p class="text-[11px] text-text-tertiary">{{ s.category?.name }}</p>
                <h3 class="text-sm font-extrabold text-text-primary truncate">{{ s.name }}</h3>
                <div class="flex items-center justify-between pt-1">
                  <p class="text-sm font-bold text-brand">{{ s.base_price }} ₪</p>
                  <p class="text-[11px] text-text-tertiary">{{ s.duration_minutes }} د</p>
                </div>
              </div>
            </Link>
          </li>
        </ul>
      </section>

      <!-- Loyalty points card (authed only) — gradient + decorative star -->
      <section
        v-if="isAuthed"
        class="relative overflow-hidden rounded-2xl p-5 text-white shadow-lg ring-2 ring-warning/40"
        :style="{ background: 'linear-gradient(120deg, color-mix(in oklab, var(--color-brand) 100%, black 18%) 0%, var(--color-brand) 55%, color-mix(in oklab, var(--color-brand) 70%, var(--color-warning) 40%) 100%)' }"
      >
        <!-- Decorative star — outline only, inline-start side, large and translucent -->
        <Star
          aria-hidden="true"
          class="pointer-events-none absolute -bottom-4 start-2 w-32 h-32 text-white/10 fill-current"
          stroke-width="1"
        />

        <div class="relative flex flex-col items-end text-end">
          <p class="text-xs font-bold text-white/85">نقاط الولاء</p>
          <p class="mt-1 text-4xl font-extrabold tracking-tight">
            {{ loyaltyBalance.toLocaleString('ar') }}
            <span class="text-base font-bold text-warning">نقطة</span>
          </p>
          <p class="mt-1 text-xs text-white/85">
            {{ loyaltyBalance >= POINTS_PER_SESSION
              ? 'يمكنك استبدال جلسة الآن!'
              : `باقي ${pointsToNextSession.toLocaleString('ar')} نقطة لجلسة مجانيّة` }}
          </p>

          <Link
            href="/portal/loyalty"
            class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-white/15 hover:bg-white/25 backdrop-blur-sm text-white text-sm font-extrabold transition ring-1 ring-white/30"
          >
            استبدل النقاط
            <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </div>
      </section>

      <!-- Medical team grid -->
      <section v-if="doctors.length > 0">
        <h2 class="text-base font-bold text-text-primary mb-3">الفريق الطبي</h2>
        <div class="grid grid-cols-2 gap-3">
          <Link
            v-for="d in doctors"
            :key="d.id"
            :href="`/doctors`"
            class="bg-surface-card rounded-2xl p-3 border-2 border-brand/15 hover:border-brand/40 transition space-y-2"
          >
            <div
              v-if="d.image_path"
              class="w-full aspect-square rounded-xl bg-cover bg-center"
              :style="{ backgroundImage: `url(/storage/${d.image_path})` }"
              role="img"
              :aria-label="d.user?.name"
            />
            <div v-else class="w-full aspect-square rounded-xl bg-brand/10 grid place-items-center text-brand">
              <UserIcon class="w-10 h-10" aria-hidden="true" />
            </div>
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/10 text-brand">{{ roleLabel(d) }}</span>
            <p class="text-sm font-extrabold text-text-primary truncate">{{ d.user?.name }}</p>
            <p class="text-[11px] text-text-secondary truncate">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
          </Link>
        </div>
      </section>

      <!-- Tip of the day -->
      <section
        v-if="tipText"
        class="rounded-2xl p-5 border-2 border-warning/30 bg-warning/5"
      >
        <span class="inline-block px-2.5 py-1 rounded-full bg-warning text-white text-[11px] font-extrabold">
          نصيحة اليوم
        </span>
        <h4 v-if="tipText.title" class="mt-2 text-base font-extrabold text-brand">{{ tipText.title }}</h4>
        <p class="mt-1 text-sm text-text-primary leading-relaxed">{{ tipText.body }}</p>
      </section>

      <!-- Clinic info footer card -->
      <section class="bg-surface-card rounded-2xl border-2 border-brand/15 p-4 space-y-1">
        <p class="text-base font-extrabold text-text-primary">{{ clinicName }}</p>
        <p class="text-xs text-text-tertiary">لرعايتك بأعلى معايير الجودة.</p>
        <Link href="/support" class="text-xs font-bold text-brand inline-flex items-center gap-1 mt-2">
          الدعم والتواصل <ArrowLeft class="w-3.5 h-3.5 rtl:rotate-180" aria-hidden="true" />
        </Link>
      </section>
      </div>
    </div>
  </ClientShell>
</template>
