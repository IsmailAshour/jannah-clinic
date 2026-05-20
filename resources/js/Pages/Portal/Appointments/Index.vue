<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import {
  PageHeader,
  PageStates,
  StatusBadge,
  Modal,
  FormGroup,
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

function deliveryLabel(mode) { return mode === 'home' ? 'منزلية' : 'في العيادة' }

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
    <div class="p-4">
      <PageHeader title="مواعيدي" />

      <!-- Error banner -->
      <div
        v-if="errors.appointment"
        class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger"
        role="alert"
      >
        {{ errors.appointment }}
      </div>

      <PageStates :is-empty="appointments.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6 text-center">لا توجد مواعيد بعد.</div>
        </template>

        <div class="space-y-3">
          <div
            v-for="appt in appointments.data"
            :key="appt.id"
            class="rounded-lg border border-border-default bg-surface-card p-4 space-y-2"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="space-y-1 min-w-0">
                <p class="font-medium text-text-primary">{{ appt.service?.name }}</p>
                <p class="text-sm text-text-secondary">{{ appt.doctor?.user?.name }}</p>
                <p class="text-sm text-text-secondary">{{ formatDate(appt.start_at) }}</p>
                <p class="text-xs text-text-tertiary">{{ deliveryLabel(appt.delivery_mode) }} · {{ appt.price_at_booking }} ₪</p>
              </div>
              <StatusBadge :type="statusVariant(appt.status)" :label="statusLabel(appt.status)" />
            </div>

            <!-- Payment row — shown whenever a Payment exists. Always visible (including for terminal appointments)
                 so the customer can still see the receipt history / refund status. -->
            <div v-if="paymentAction(appt)" class="flex items-center justify-between gap-2 pt-1 border-t border-border-default mt-2">
              <p class="text-xs text-text-tertiary">{{ paymentAction(appt).subtext }}</p>
              <Link :href="`/portal/appointments/${appt.id}/payment`">
                <Button :variant="paymentAction(appt).variant" size="sm">{{ paymentAction(appt).label }}</Button>
              </Link>
            </div>

            <!-- Actions for non-terminal appointments -->
            <div v-if="!isTerminal(appt.status)" class="flex gap-2 pt-1">
              <Button variant="outline" size="sm" @click="openRescheduleModal(appt)">إعادة جدولة</Button>
              <Button variant="outline" size="sm" class="text-danger" @click="openCancelModal(appt)">إلغاء</Button>
            </div>
          </div>
        </div>
      </PageStates>
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
