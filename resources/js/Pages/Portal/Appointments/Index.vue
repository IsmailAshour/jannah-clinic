<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import { CalendarDays, CalendarPlus, Clock, Home, MapPin, MessageCircle, Video, AlertCircle } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import {
  StatusBadge,
  Modal,
  FormGroup,
  AuthGuardLink,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  appointments: { type: Object, default: () => ({ data: [] }) },
  errors: { type: Object, default: () => ({}) },
})

// Arabic label + StatusBadge variant for each AppointmentStatus value
const statusMap = {
  requested:   { label: 'بانتظار التأكيد', variant: 'warning' },
  confirmed:   { label: 'مؤكد',            variant: 'success' },
  rejected:    { label: 'مرفوض',           variant: 'danger'  },
  completed:   { label: 'مكتمل',           variant: 'info'    },
  cancelled:   { label: 'ملغى',            variant: 'danger'  },
  no_show:     { label: 'لم يحضر',         variant: 'warning' },
  rescheduled: { label: 'أُعيد جدولته',    variant: 'info'    },
}

function statusLabel(val) { return statusMap[val]?.label ?? val }
function statusVariant(val) { return statusMap[val]?.variant ?? 'info' }

function isTerminal(status) {
  return ['completed', 'cancelled', 'rejected', 'no_show', 'rescheduled'].includes(status)
}

// Payment label/variant per payment.status. Returns null when no Payment row
// (e.g. appointment paid via loyalty_points — no separate Payment exists).
const paymentMap = {
  pending: { label: 'الدفع', variant: 'default', subtext: 'ادفع وارفع الإيصال' },
  rejected: { label: 'أعد الرفع', variant: 'default', subtext: 'الإيصال مرفوض' },
  submitted: { label: 'عرض الإيصال', variant: 'outline', subtext: 'بانتظار المراجعة' },
  paid: { label: 'إيصال الدفع', variant: 'ghost', subtext: 'مدفوع' },
  refund_pending: { label: 'حالة الاسترداد', variant: 'ghost', subtext: 'بانتظار الاسترداد' },
  refunded: { label: 'حالة الاسترداد', variant: 'ghost', subtext: 'مُسترَدّ' },
}
function paymentAction(appt) {
  return appt.payment ? paymentMap[appt.payment.status] ?? null : null
}

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

// Date block helpers for the per-card calendar tile
function formatDay(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ar-SA', { day: 'numeric' })
}
function formatMonth(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleDateString('ar-SA', { month: 'short' })
}
function formatTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}
function formatRelativeDay(dt) {
  if (!dt) return null
  const target = new Date(dt)
  const today = new Date()
  const tomorrow = new Date()
  tomorrow.setDate(today.getDate() + 1)
  const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
  if (sameDay(target, today)) return 'اليوم'
  if (sameDay(target, tomorrow)) return 'غدًا'
  return null
}

function deliveryLabel(mode) {
  if (mode === 'home') return 'منزلية'
  if (mode === 'online') return 'أونلاين'
  return 'في العيادة'
}

function deliveryIcon(mode) {
  if (mode === 'home') return Home
  if (mode === 'online') return Video
  return MapPin
}

// --- Tab filter (client-side) ---
const activeTab = ref('upcoming')
const upcomingAppts = computed(() => props.appointments.data.filter(a => !isTerminal(a.status)))
const pastAppts = computed(() => props.appointments.data.filter(a => isTerminal(a.status)))
const visibleAppts = computed(() => activeTab.value === 'upcoming' ? upcomingAppts.value : pastAppts.value)
const tabCounts = computed(() => ({ upcoming: upcomingAppts.value.length, past: pastAppts.value.length }))

// --- Cancel flow ---
const showCancelModal = ref(false)
const cancelTarget = ref(null)
const cancelForm = useForm({ reason: '' })

function openCancelModal(appt) {
  cancelTarget.value = appt
  cancelForm.reset()
  showCancelModal.value = true
}

function submitCancel() {
  if (!cancelTarget.value) return
  cancelForm.post(`/portal/appointments/${cancelTarget.value.id}/cancel`, {
    onSuccess: () => { showCancelModal.value = false; cancelTarget.value = null },
  })
}

// --- Reschedule flow ---
const showRescheduleModal = ref(false)
const rescheduleTarget = ref(null)
const rescheduleForm = useForm({ start: '' })

const rescheduleDate = ref('')
const slots = ref([])
const slotsLoading = ref(false)
const slotsEmpty = ref(false)
const slotsError = ref(false)
const selectedStart = ref(null)

function openRescheduleModal(appt) {
  rescheduleTarget.value = appt
  rescheduleForm.reset()
  rescheduleDate.value = ''
  slots.value = []
  slotsLoading.value = false
  slotsEmpty.value = false
  slotsError.value = false
  selectedStart.value = null
  showRescheduleModal.value = true
}

async function fetchRescheduleSlots() {
  if (!rescheduleTarget.value || !rescheduleDate.value) return
  const appt = rescheduleTarget.value
  slotsLoading.value = true
  slotsEmpty.value = false
  slotsError.value = false
  slots.value = []
  selectedStart.value = null
  try {
    const url = `/portal/availability?doctor=${appt.doctor_profile_id}&service=${appt.service_id}&date=${rescheduleDate.value}`
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    slots.value = data
    slotsEmpty.value = data.length === 0
  } catch {
    slotsError.value = true
  } finally {
    slotsLoading.value = false
  }
}

function submitReschedule() {
  if (!rescheduleTarget.value || !selectedStart.value) return
  rescheduleForm.start = selectedStart.value
  rescheduleForm.post(`/portal/appointments/${rescheduleTarget.value.id}/reschedule`, {
    onSuccess: () => { showRescheduleModal.value = false; rescheduleTarget.value = null },
  })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <!-- Header -->
      <header class="space-y-2">
        <div class="flex items-center justify-between gap-2">
          <div>
            <h1 class="text-2xl font-extrabold text-text-primary">مواعيدي</h1>
            <p class="text-sm text-text-secondary">إدارة مواعيدك ومتابعة المدفوعات.</p>
          </div>
          <AuthGuardLink
            intent="booking"
            authed-href="/portal/booking"
            staff-href="/admin/booking"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-brand text-white text-sm font-bold hover:bg-brand-hover transition shrink-0"
          >
            <CalendarPlus class="w-4 h-4" aria-hidden="true" />
            <span>حجز جديد</span>
          </AuthGuardLink>
        </div>

        <!-- Tabs -->
        <div role="tablist" class="inline-flex bg-surface-card rounded-full p-1 ring-1 ring-border-default">
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'upcoming'"
            :class="[
              'px-4 py-1.5 rounded-full text-sm font-bold transition',
              activeTab === 'upcoming' ? 'bg-brand text-white shadow-sm' : 'text-text-secondary hover:text-text-primary',
            ]"
            @click="activeTab = 'upcoming'"
          >
            القادمة <span class="text-xs opacity-80">({{ tabCounts.upcoming }})</span>
          </button>
          <button
            type="button"
            role="tab"
            :aria-selected="activeTab === 'past'"
            :class="[
              'px-4 py-1.5 rounded-full text-sm font-bold transition',
              activeTab === 'past' ? 'bg-brand text-white shadow-sm' : 'text-text-secondary hover:text-text-primary',
            ]"
            @click="activeTab = 'past'"
          >
            المنتهية <span class="text-xs opacity-80">({{ tabCounts.past }})</span>
          </button>
        </div>
      </header>

      <!-- Error banner -->
      <div
        v-if="errors.appointment"
        class="rounded-2xl bg-danger/10 border-2 border-danger/30 p-4 text-sm text-danger inline-flex items-start gap-2"
        role="alert"
      >
        <AlertCircle class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" />
        <span>{{ errors.appointment }}</span>
      </div>

      <!-- Empty states (per-tab) -->
      <div
        v-if="appointments.data.length === 0"
        class="bg-surface-card rounded-2xl border-2 border-dashed border-brand/20 p-8 text-center space-y-3"
      >
        <div class="mx-auto w-16 h-16 rounded-full bg-brand/10 grid place-items-center text-brand">
          <CalendarDays class="w-8 h-8" aria-hidden="true" />
        </div>
        <p class="text-base font-bold text-text-primary">لا توجد مواعيد بعد</p>
        <p class="text-sm text-text-secondary">احجز موعدك الأوّل وابدأ رحلتك معنا.</p>
        <AuthGuardLink
          intent="booking"
          authed-href="/portal/booking"
          staff-href="/admin/booking"
          class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-brand text-white text-sm font-bold hover:bg-brand-hover transition"
        >
          <CalendarPlus class="w-4 h-4" aria-hidden="true" />
          <span>احجز الآن</span>
        </AuthGuardLink>
      </div>

      <div
        v-else-if="visibleAppts.length === 0"
        class="bg-surface-card rounded-2xl border border-border-default p-6 text-center text-sm text-text-secondary"
      >
        {{ activeTab === 'upcoming' ? 'لا مواعيد قادمة حاليًا.' : 'لا مواعيد منتهية بعد.' }}
      </div>

      <!-- Appointment cards -->
      <ul v-else class="space-y-3">
        <li
          v-for="appt in visibleAppts"
          :key="appt.id"
          class="bg-surface-card rounded-2xl border-2 border-border-default p-4 space-y-3 hover:shadow-sm transition"
        >
          <!-- Top: date block + service info + status -->
          <div class="flex items-start gap-3">
            <!-- Calendar tile -->
            <div class="shrink-0 w-16 rounded-xl bg-brand/5 ring-1 ring-brand/15 text-center overflow-hidden">
              <div class="bg-brand text-white py-0.5 text-[10px] font-bold">{{ formatMonth(appt.start_at) }}</div>
              <div class="py-1.5">
                <div class="text-xl font-extrabold text-brand leading-none">{{ formatDay(appt.start_at) }}</div>
                <div class="mt-0.5 inline-flex items-center gap-0.5 text-[10px] text-text-secondary font-bold">
                  <Clock class="w-2.5 h-2.5" aria-hidden="true" />
                  <span dir="ltr">{{ formatTime(appt.start_at) }}</span>
                </div>
              </div>
            </div>

            <!-- Service + doctor + meta -->
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <h3 class="text-base font-extrabold text-text-primary">{{ (appt.services ?? []).map(s => s.name).join(' + ') || appt.service?.name || '—' }}</h3>
                <StatusBadge :type="statusVariant(appt.status)" :label="statusLabel(appt.status)" />
              </div>
              <p class="mt-0.5 text-sm text-text-secondary truncate">{{ appt.doctor?.user?.name }}</p>
              <p v-if="formatRelativeDay(appt.start_at)" class="mt-0.5 text-xs font-bold text-warning">{{ formatRelativeDay(appt.start_at) }}</p>
              <div class="mt-1.5 flex items-center gap-2 flex-wrap text-xs text-text-tertiary">
                <span class="inline-flex items-center gap-1">
                  <component :is="deliveryIcon(appt.delivery_mode)" class="w-3 h-3" aria-hidden="true" />
                  {{ deliveryLabel(appt.delivery_mode) }}
                </span>
                <span aria-hidden="true">·</span>
                <span class="font-bold text-text-secondary">{{ appt.price_at_booking }} ₪</span>
              </div>
            </div>
          </div>

          <!-- Action row — payment first, then reschedule, then cancel. -->
          <div
            v-if="paymentAction(appt) || !isTerminal(appt.status)"
            class="flex flex-wrap items-center gap-2 pt-3 border-t border-border-default"
          >
            <Link v-if="paymentAction(appt)" :href="`/portal/appointments/${appt.id}/payment`">
              <Button :variant="paymentAction(appt).variant" size="sm">{{ paymentAction(appt).label }}</Button>
            </Link>
            <template v-if="!isTerminal(appt.status)">
              <Button variant="outline" size="sm" @click="openRescheduleModal(appt)">إعادة جدولة</Button>
              <Button variant="outline" size="sm" class="text-danger" @click="openCancelModal(appt)">إلغاء</Button>
            </template>
            <p v-if="paymentAction(appt)" class="text-xs text-text-tertiary ms-auto">
              {{ paymentAction(appt).subtext }}
            </p>
          </div>
        </li>
      </ul>
    </div>

    <!-- Cancel Modal -->
    <Modal
      :open="showCancelModal"
      title="إلغاء الموعد"
      @update:open="showCancelModal = $event"
    >
      <form class="space-y-4" @submit.prevent="submitCancel">
        <FormGroup label="سبب الإلغاء" name="reason" :error="cancelForm.errors.reason" required>
          <template #default="{ describedby }">
            <textarea
              id="reason"
              v-model="cancelForm.reason"
              name="reason"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
              placeholder="اذكر سبب الإلغاء..."
            />
          </template>
        </FormGroup>
        <div v-if="cancelForm.errors.appointment" class="text-xs text-danger">{{ cancelForm.errors.appointment }}</div>
      </form>
      <template #footer>
        <Button variant="outline" @click="showCancelModal = false">تراجع</Button>
        <Button class="bg-danger text-white" :disabled="cancelForm.processing || !cancelForm.reason.trim()" @click="submitCancel">
          إلغاء الموعد
        </Button>
      </template>
    </Modal>

    <!-- Reschedule Modal -->
    <Modal
      :open="showRescheduleModal"
      title="إعادة جدولة الموعد"
      @update:open="showRescheduleModal = $event"
    >
      <div class="space-y-4">
        <FormGroup label="تاريخ الموعد الجديد" name="reschedule_date">
          <template #default="{ describedby }">
            <input
              id="reschedule_date"
              v-model="rescheduleDate"
              type="date"
              :aria-describedby="describedby"
              dir="ltr"
              class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
              @change="fetchRescheduleSlots"
            />
          </template>
        </FormGroup>

        <div v-if="rescheduleDate">
          <p class="text-sm font-medium text-text-primary mb-3">الفترات المتاحة</p>
          <div v-if="slotsLoading" class="text-sm text-text-secondary">جارٍ تحميل الفترات...</div>
          <div v-else-if="slotsError" class="rounded-md bg-danger/10 border border-danger/20 p-3 text-sm text-danger" role="alert">
            تعذّر تحميل الفترات، حاول مرة أخرى.
          </div>
          <div v-else-if="slotsEmpty" class="text-sm text-text-secondary py-2">لا فترات متاحة في هذا اليوم.</div>
          <div v-else class="flex flex-wrap gap-2">
            <button
              v-for="slot in slots"
              :key="slot.start"
              type="button"
              :class="[
                'rounded-md border px-4 py-2 text-sm transition-colors',
                selectedStart === slot.start
                  ? 'border-brand bg-brand text-white'
                  : 'border-border-default bg-surface-card text-text-primary hover:border-brand hover:text-brand',
              ]"
              @click="selectedStart = slot.start"
            >
              {{ slot.label }}
            </button>
          </div>
        </div>

        <div v-if="rescheduleForm.errors.appointment" class="text-xs text-danger">
          {{ rescheduleForm.errors.appointment }}
        </div>
      </div>
      <template #footer>
        <Button variant="outline" @click="showRescheduleModal = false">إلغاء</Button>
        <Button :disabled="rescheduleForm.processing || !selectedStart" @click="submitReschedule">
          تأكيد إعادة الجدولة
        </Button>
      </template>
    </Modal>
  </ClientShell>
</template>
