<script setup>
import { ref, h } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  Modal,
  ConfirmModal,
  StatusBadge,
  FormGroup,
} from '@/Components/foundation'
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  appointments: { type: Object, default: () => ({ data: [] }) },
  doctors: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  errors: { type: Object, default: () => ({}) },
})

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

const filterStatus = ref(props.filters.status ?? '')
const filterDoctor = ref(props.filters.doctor ?? '')
const filterDate = ref(props.filters.date ?? '')

function applyFilters(extraQuery = {}) {
  router.get(
    '/admin/appointments',
    {
      status: filterStatus.value || undefined,
      doctor: filterDoctor.value || undefined,
      date: filterDate.value || undefined,
      ...extraQuery,
    },
    { preserveScroll: true, replace: true }
  )
}

function resetFilters() {
  filterStatus.value = ''
  filterDoctor.value = ''
  filterDate.value = ''
  applyFilters()
}

function goToPage(p) {
  applyFilters({ page: p })
}

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

const confirmOpen = ref(false)
const pendingTransition = ref(null)

function requestTransition(appt, status) {
  pendingTransition.value = { appt, status }
  confirmOpen.value = true
}

function doTransition(appt, status) {
  const form = useForm({ status, reason: null })
  form.post(`/admin/appointments/${appt.id}/transition`, { preserveScroll: true })
}

function handleConfirmTransition() {
  if (!pendingTransition.value) return
  const { appt, status } = pendingTransition.value
  pendingTransition.value = null
  doTransition(appt, status)
}

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

// Row selection — header checkbox + per-row checkbox
const SelectAllHeader = (table) => h('input', {
  type: 'checkbox',
  class: 'h-4 w-4 cursor-pointer',
  'aria-label': 'تحديد الكل',
  checked: table.getIsAllPageRowsSelected(),
  indeterminate: table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected(),
  onChange: (e) => table.toggleAllPageRowsSelected(e.target.checked),
})
const SelectRow = (row) => h('input', {
  type: 'checkbox',
  class: 'h-4 w-4 cursor-pointer',
  'aria-label': 'تحديد الصف',
  checked: row.getIsSelected(),
  onChange: (e) => row.toggleSelected(e.target.checked),
})

const columns = [
  {
    id: 'select',
    enableHiding: false,
    enableSorting: false,
    header: ({ table }) => SelectAllHeader(table),
    cell: ({ row }) => SelectRow(row),
    meta: { label: 'تحديد', headerClass: 'w-10', cellClass: 'w-10 text-center' },
  },
  {
    accessorKey: 'customer',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'العميل' }),
    cell: ({ row }) => row.original.customer?.name ?? '—',
    meta: { label: 'العميل' },
  },
  {
    accessorKey: 'doctor',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الطبيب' }),
    cell: ({ row }) => row.original.doctor?.user?.name ?? '—',
    meta: { label: 'الطبيب' },
  },
  {
    accessorKey: 'service',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الخدمة' }),
    cell: ({ row }) => row.original.service?.name ?? '—',
    meta: { label: 'الخدمة' },
  },
  {
    accessorKey: 'start_at',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الموعد' }),
    cell: ({ row }) => formatDate(row.original.start_at),
    meta: { label: 'الموعد' },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => h(StatusBadge, {
      type: statusVariant(row.original.status),
      label: statusLabel(row.original.status),
    }),
    meta: { label: 'الحالة' },
  },
  {
    accessorKey: 'delivery_mode',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'طريقة التقديم' }),
    cell: ({ row }) => deliveryLabel(row.original.delivery_mode),
    meta: { label: 'طريقة التقديم' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => {
      const r = row.original
      if (isTerminal(r.status)) {
        return h(AdminDataTableRowActions, null, {
          default: () => h(DropdownMenuItem, { disabled: true }, '— لا توجد إجراءات —'),
        })
      }
      const items = []
      if (r.status === 'requested') {
        items.push(h(DropdownMenuItem, { onClick: () => requestTransition(r, 'confirmed') }, 'تأكيد'))
        items.push(h(DropdownMenuItem, { class: 'text-danger', onClick: () => requestTransition(r, 'rejected') }, 'رفض'))
      }
      if (r.status === 'confirmed') {
        items.push(h(DropdownMenuItem, { onClick: () => requestTransition(r, 'completed') }, 'إكمال'))
        items.push(h(DropdownMenuItem, { class: 'text-warning', onClick: () => requestTransition(r, 'no_show') }, 'لم يحضر'))
      }
      if (items.length > 0) {
        items.push(h(DropdownMenuSeparator))
      }
      items.push(h(DropdownMenuItem, { class: 'text-danger', onClick: () => openCancelModal(r) }, 'إلغاء بسبب…'))
      return h(AdminDataTableRowActions, null, { default: () => items })
    },
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="المواعيد" description="متابعة طلبات الحجز وحالاتها." />

      <div
        v-if="errors.appointment"
        class="rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger"
        role="alert"
      >
        {{ errors.appointment }}
      </div>

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="appointments.data"
          :server-meta="appointments"
          :on-page-change="goToPage"
          empty-text="لا توجد مواعيد."
        >
          <template #toolbar="{ table }">
            <form class="flex flex-wrap items-center justify-between gap-2 w-full" @submit.prevent="applyFilters()">
              <div class="flex flex-wrap items-center gap-2">
                <select
                  v-model="filterStatus"
                  aria-label="فلتر الحالة"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الحالات</option>
                  <option v-for="s in statusOptions" :key="s.value" :value="s.value">
                    {{ statusLabel(s.value) }}
                  </option>
                </select>
                <select
                  v-model="filterDoctor"
                  aria-label="فلتر الطبيب"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الأطباء</option>
                  <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <Input
                  v-model="filterDate"
                  type="date"
                  dir="ltr"
                  aria-label="فلتر التاريخ"
                  class="h-9 w-40"
                />
                <Button type="submit" size="sm" class="h-9">تطبيق</Button>
                <Button type="button" variant="ghost" size="sm" class="h-9" @click="resetFilters">تفريغ</Button>
              </div>
              <AdminDataTableViewOptions :table="table" />
            </form>
          </template>
        </AdminDataTable>
      </div>
    </div>

    <ConfirmModal
      :open="confirmOpen"
      title="تأكيد تغيير الحالة"
      :message="pendingTransition ? `هل أنت متأكد من تغيير حالة الموعد إلى &quot;${statusMap[pendingTransition.status]?.label ?? pendingTransition.status}&quot;؟` : ''"
      confirm-text="تأكيد"
      cancel-text="تراجع"
      @confirm="handleConfirmTransition"
      @update:open="confirmOpen = $event"
    />

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
