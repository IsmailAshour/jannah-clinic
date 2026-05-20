<script setup>
import { ref, h } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
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

const columns = [
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
    <div class="p-6">
      <PageHeader title="المدفوعات" />

      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters()">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (اسم/بريد/هاتف العميل)</label>
          <Input id="q" v-model="q" name="q" placeholder="ابحث..." class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            id="status"
            v-model="status"
            name="status"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="all">الكل</option>
            <option value="submitted">بانتظار التحقّق</option>
            <option value="pending">بانتظار الدفع</option>
            <option value="paid">مدفوع</option>
            <option value="rejected">مرفوض</option>
            <option value="refund_pending">بانتظار الاسترداد</option>
            <option value="refunded">مُسترَد</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <AdminDataTable
        :columns="columns"
        :data="payments.data"
        :server-meta="payments"
        :on-page-change="goToPage"
        empty-text="لا مدفوعات تطابق الفلتر."
      />
    </div>
  </AdminShell>
</template>
