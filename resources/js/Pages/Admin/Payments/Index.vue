<script setup>
import { ref, h } from 'vue'
import { router } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  StatusBadge,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  payments: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? 'submitted')

function applyFilters(extraQuery = {}) {
  router.get('/admin/payments', {
    q: q.value || undefined,
    status: status.value || undefined,
    ...extraQuery,
  }, { preserveScroll: true, replace: true })
}

function resetFilters() {
  q.value = ''
  status.value = 'submitted'
  applyFilters()
}

function goToPage(p) {
  applyFilters({ page: p })
}

const statusMap = {
  pending:        { label: 'بانتظار الدفع',    variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',   variant: 'info'    },
  paid:           { label: 'مدفوع',             variant: 'success' },
  rejected:       { label: 'مرفوض',             variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded:       { label: 'مُسترَد',           variant: 'info'    },
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
    cell: ({ row }) => row.original.appointment?.customer?.name ?? '—',
    meta: { label: 'العميل' },
  },
  {
    accessorKey: 'service',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الخدمة' }),
    cell: ({ row }) => row.original.appointment?.service?.name ?? '—',
    meta: { label: 'الخدمة' },
  },
  {
    accessorKey: 'doctor',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الطبيب' }),
    cell: ({ row }) => row.original.appointment?.doctor?.user?.name ?? '—',
    meta: { label: 'الطبيب' },
  },
  {
    accessorKey: 'amount',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'المبلغ' }),
    cell: ({ row }) => `${row.original.amount} ₪`,
    meta: { label: 'المبلغ' },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => h(StatusBadge, {
      type: statusMap[row.original.status]?.variant ?? 'info',
      label: statusMap[row.original.status]?.label ?? row.original.status,
    }),
    meta: { label: 'الحالة' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => h(DropdownMenuItem, {
        onClick: () => router.visit(`/admin/payments/${row.original.id}`),
      }, 'عرض'),
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="المدفوعات" description="مراجعة إثباتات الدفع والتحقق من حالة المعاملات." />

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="payments.data"
          :server-meta="payments"
          :on-page-change="goToPage"
          empty-text="لا مدفوعات تطابق الفلتر."
        >
          <template #toolbar="{ table }">
            <form class="flex flex-wrap items-center justify-between gap-2 w-full" @submit.prevent="applyFilters()">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-72">
                  <Search class="absolute top-1/2 -translate-y-1/2 start-3 h-4 w-4 text-text-tertiary pointer-events-none" aria-hidden="true" />
                  <Input
                    id="q"
                    v-model="q"
                    name="q"
                    placeholder="ابحث باسم/بريد/هاتف العميل…"
                    class="ps-9 h-9"
                  />
                </div>
                <select
                  v-model="status"
                  name="status"
                  aria-label="فلتر الحالة"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="all">الكل</option>
                  <option value="submitted">بانتظار التحقّق</option>
                  <option value="pending">بانتظار الدفع</option>
                  <option value="paid">مدفوع</option>
                  <option value="rejected">مرفوض</option>
                  <option value="refund_pending">بانتظار الاسترداد</option>
                  <option value="refunded">مُسترَد</option>
                </select>
                <Button type="submit" size="sm" class="h-9">تطبيق</Button>
                <Button type="button" variant="ghost" size="sm" class="h-9" @click="resetFilters">تفريغ</Button>
              </div>
              <AdminDataTableViewOptions :table="table" />
            </form>
          </template>
        </AdminDataTable>
      </div>
    </div>
  </AdminShell>
</template>
