<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Bell, Sparkles, Star, Stethoscope } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { AuthGuardLink } from '@/Components/foundation'
import { iconForCategory } from '@/lib/categoryIcons'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  doctors: { type: Array, default: () => [] },
  tip: { type: [String, Object], default: null },
  greetingName: { type: String, default: null },
  upcomingAppointments: { type: Array, default: () => [] },
  loyaltyBalance: { type: Number, default: 0 },
})

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')
const isAuthed = computed(() => !!page.props?.auth?.user)
const unreadCount = computed(() => page.props?.notifications?.unread_count ?? 0)

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
  <ClientShell>
    <div class="px-5 pt-6 pb-10 space-y-6">
      <!-- Greeting -->
      <header class="space-y-1.5">
        <h1 class="text-3xl font-extrabold text-brand">
          أهلًا{{ greetingName ? `، ${greetingName}` : ' بك' }}!
        </h1>
        <p class="text-sm text-text-secondary">أهلًا بك في {{ clinicName }} — اعتنِ بصحّتك وجمالك.</p>
      </header>

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
          <AuthGuardLink intent="booking" authed-href="/portal/booking" class="font-bold text-brand">احجز الآن</AuthGuardLink>
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

      <!-- Big CTA card — gradient -->
      <AuthGuardLink
        intent="booking"
        authed-href="/portal/booking"
        class="block relative overflow-hidden rounded-2xl p-5 text-white shadow-md bg-gradient-to-bl from-brand/95 via-brand to-brand/80"
      >
        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-warning text-white text-xs font-extrabold">
          <Sparkles class="w-3 h-3" aria-hidden="true" /> ابدأ الآن
        </span>
        <h3 class="mt-2 text-lg font-extrabold">احجز موعدك بسهولة</h3>
        <p class="mt-1 text-sm text-white/85">اختر الخدمة والطبيب والوقت — كل ذلك في دقائق.</p>
      </AuthGuardLink>

      <!-- Categories (iconic squares) -->
      <section v-if="categories.length > 0">
        <h2 class="text-base font-bold text-text-primary mb-3">خدماتنا</h2>
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

      <!-- Loyalty points card (authed only) — gradient -->
      <section
        v-if="isAuthed"
        class="rounded-2xl p-5 text-white shadow-md bg-gradient-to-bl from-brand via-brand/90 to-brand/70"
      >
        <p class="text-xs font-bold text-white/80">نقاط الولاء</p>
        <p class="mt-1 text-4xl font-extrabold">
          {{ loyaltyBalance.toLocaleString('ar') }}
          <span class="text-base font-bold text-white/85">نقطة</span>
        </p>
        <p class="mt-1 text-xs text-white/80">
          {{ loyaltyBalance >= POINTS_PER_SESSION
            ? 'يمكنك استبدال جلسة الآن!'
            : `باقي ${pointsToNextSession.toLocaleString('ar')} نقطة لجلسة مجانيّة` }}
        </p>
        <Link href="/portal/loyalty" class="mt-3 inline-flex items-center gap-1 text-xs font-bold text-white/90">
          إدارة النقاط <ArrowLeft class="w-3.5 h-3.5 rtl:rotate-180" aria-hidden="true" />
        </Link>
      </section>

      <!-- Doctors grid -->
      <section v-if="doctors.length > 0">
        <h2 class="text-base font-bold text-text-primary mb-3">الأطباء</h2>
        <div class="grid grid-cols-2 gap-3">
          <Link
            v-for="d in doctors"
            :key="d.id"
            :href="`/doctors`"
            class="bg-surface-card rounded-2xl p-3 border-2 border-brand/15 hover:border-brand/40 transition space-y-2"
          >
            <div class="w-full aspect-square rounded-xl bg-brand/10 grid place-items-center text-brand">
              <Stethoscope class="w-10 h-10" aria-hidden="true" />
            </div>
            <p class="text-sm font-extrabold text-text-primary truncate">{{ d.user?.name }}</p>
            <p class="text-[11px] text-text-secondary truncate">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
            <p class="text-[11px] text-warning font-bold inline-flex items-center gap-0.5">
              <Star class="w-3 h-3 fill-current" aria-hidden="true" />
              {{ Number(d.rating_average).toFixed(1) }}
            </p>
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
  </ClientShell>
</template>
