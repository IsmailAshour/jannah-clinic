<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  DataTable,
  Modal,
  PageStates,
  StatusBadge,
  FormGroup,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  appointments: { type: Object, default: () => ({ data: [], links: [] }) },
  doctors: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
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

// Filters
const filterStatus = ref(props.filters.status ?? '')
const filterDoctor = ref(props.filters.doctor ?? '')
const filterDate = ref(props.filters.date ?? '')

function applyFilters() {
  router.get(
    '/admin/appointments',
    {
      status: filterStatus.value || undefined,
      doctor: filterDoctor.value || undefined,
      date: filterDate.value || undefined,
    },
    { preserveScroll: true, replace: true }
  )
}

const columns = [
  { key: 'customer',      label: 'العميل' },
  { key: 'doctor',        label: 'الطبيب' },
  { key: 'service',       label: 'الخدمة' },
  { key: 'start_at',      label: 'الموعد' },
  { key: 'status',        label: 'الحالة' },
  { key: 'delivery_mode', label: 'طريقة التقديم' },
  { key: 'actions',       label: 'إجراءات', align: 'end' },
]

function formatDate(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function deliveryLabel(mode) { return mode === 'home' ? 'منزلية' : 'في العيادة' }

function isTerminal(status) {
  return ['completed', 'cancelled', 'rejected', 'no_show', 'rescheduled'].includes(status)
}

// Quick transitions (no reason needed)
function doTransition(appt, status) {
  const form = useForm({ status, reason: null })
  form.post(`/admin/appointments/${appt.id}/transition`, { preserveScroll: true })
}

// Cancel with reason
const showCancelModal = ref(false)
const cancelTarget = ref(null)
const cancelForm = useForm({ status: 'cancelled', reason: '' })

function openCancelModal(appt) {
  cancelTarget.value = appt
  cancelForm.reset()
  cancelForm.status = 'cancelled'
  showCancelModal.value = true
}

function submitCancel() {
  if (!cancelTarget.value) return
  cancelForm.post(`/admin/appointments/${cancelTarget.value.id}/transition`, {
    onSuccess: () => { showCancelModal.value = false; cancelTarget.value = null },
  })
}
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="المواعيد" />

      <!-- Appointment error banner -->
      <div
        v-if="errors.appointment"
        class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger"
        role="alert"
      >
        {{ errors.appointment }}
      </div>

      <!-- Filter bar -->
      <div class="mb-6 flex flex-wrap gap-3 items-end">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            v-model="filterStatus"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="">كل الحالات</option>
            <option v-for="s in statusOptions" :key="s.value" :value="s.value">
              {{ statusLabel(s.value) }}
            </option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-text-secondary">الطبيب</label>
          <select
            v-model="filterDoctor"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="">كل الأطباء</option>
            <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-text-secondary">التاريخ</label>
          <Input v-model="filterDate" type="date" dir="ltr" class="w-40" />
        </div>
        <Button @click="applyFilters">تصفية</Button>
      </div>

      <PageStates :is-empty="appointments.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا توجد مواعيد.</div>
        </template>

        <DataTable :columns="columns" :rows="appointments.data">
          <template #cell-customer="{ row }">{{ row.customer?.name }}</template>
          <template #cell-doctor="{ row }">{{ row.doctor?.user?.name }}</template>
          <template #cell-service="{ row }">{{ row.service?.name }}</template>
          <template #cell-start_at="{ row }">{{ formatDate(row.start_at) }}</template>
          <template #cell-status="{ row }">
            <StatusBadge :type="statusVariant(row.status)" :label="statusLabel(row.status)" />
          </template>
          <template #cell-delivery_mode="{ row }">{{ deliveryLabel(row.delivery_mode) }}</template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2 flex-wrap">
              <template v-if="!isTerminal(row.status)">
                <Button v-if="row.status === 'requested'" variant="outline" size="sm" @click="doTransition(row, 'confirmed')">تأكيد</Button>
                <Button v-if="row.status === 'requested'" variant="outline" size="sm" class="text-danger" @click="doTransition(row, 'rejected')">رفض</Button>
                <Button v-if="row.status === 'confirmed'" variant="outline" size="sm" @click="doTransition(row, 'completed')">إكمال</Button>
                <Button v-if="row.status === 'confirmed'" variant="outline" size="sm" class="text-warning" @click="doTransition(row, 'no_show')">لم يحضر</Button>
                <Button variant="outline" size="sm" class="text-danger" @click="openCancelModal(row)">إلغاء</Button>
              </template>
              <span v-else class="text-xs text-text-tertiary">—</span>
            </div>
          </template>
        </DataTable>

        <!-- Pagination links -->
        <div v-if="appointments.links && appointments.links.length > 3" class="mt-4 flex gap-1 justify-center">
          <template v-for="link in appointments.links" :key="link.label">
            <component
              :is="link.url ? 'a' : 'span'"
              :href="link.url ?? undefined"
              class="px-3 py-1 text-sm rounded-md border border-border-default"
              :class="link.active ? 'bg-brand text-white' : 'bg-surface-card text-text-secondary hover:bg-surface-sunken'"
              v-html="link.label"
            />
          </template>
        </div>
      </PageStates>
    </div>

    <!-- Cancel with reason modal -->
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
  </AdminShell>
</template>
