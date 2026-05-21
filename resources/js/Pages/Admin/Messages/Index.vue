<script setup>
import { ref, h, computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  StatCard,
  StatusBadge,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  messages: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({ total: 0, new: 0, replied: 0, this_week: 0 }) },
})

const page = usePage()
const isManager = (() => page.props?.auth?.user?.role === 'manager')()

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? '')

function applyFilters(extraQuery = {}) {
  router.get(
    '/admin/messages',
    {
      q: q.value || undefined,
      status: status.value || undefined,
      ...extraQuery,
    },
    { preserveScroll: true, replace: true }
  )
}

function resetFilters() {
  q.value = ''
  status.value = ''
  applyFilters()
}

function goToPage(p) {
  applyFilters({ page: p })
}

function markStatus(row, next) {
  if (!isManager) return
  router.post(`/admin/messages/${row.id}/status`, { status: next }, { preserveScroll: true })
}

function destroyMessage(row) {
  if (!isManager) return
  if (!confirm('حذف هذه الرسالة نهائيًا؟')) return
  router.delete(`/admin/messages/${row.id}`, { preserveScroll: true })
}

const statusShare = computed(() =>
  props.stats.total > 0 ? Math.round((props.stats.new / props.stats.total) * 100) : 0
)
const repliedShare = computed(() =>
  props.stats.total > 0 ? Math.round((props.stats.replied / props.stats.total) * 100) : 0
)

const statusBadge = (s) => {
  switch (s) {
    case 'new': return { type: 'warning', label: 'جديدة' }
    case 'read': return { type: 'info', label: 'مقروءة' }
    case 'replied': return { type: 'success', label: 'تم الرد' }
    case 'archived': return { type: 'danger', label: 'مؤرشفة' }
    default: return { type: 'info', label: s }
  }
}

function formatDate(s) {
  if (!s) return '—'
  try {
    return new Date(s).toLocaleString('ar-SA', { dateStyle: 'short', timeStyle: 'short' })
  } catch (_) { return s }
}

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
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'المرسل' }),
    cell: ({ row }) => h('div', { class: 'flex flex-col' }, [
      h('span', { class: 'font-medium text-text-primary' }, row.original.name),
      h('span', { class: 'text-xs text-text-tertiary', dir: 'ltr' }, row.original.email),
    ]),
    meta: { label: 'المرسل' },
  },
  {
    accessorKey: 'subject',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الموضوع' }),
    cell: ({ row }) => h('span', { class: 'text-text-primary' }, row.original.subject),
    meta: { label: 'الموضوع' },
  },
  {
    accessorKey: 'phone',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الهاتف' }),
    cell: ({ row }) => h('span', { dir: 'ltr' }, row.original.phone || '—'),
    meta: { label: 'الهاتف' },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => {
      const b = statusBadge(row.original.status)
      return h(StatusBadge, { type: b.type, label: b.label })
    },
    meta: { label: 'الحالة' },
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'تاريخ الإرسال' }),
    cell: ({ row }) => h('span', { class: 'text-xs text-text-secondary', dir: 'ltr' }, formatDate(row.original.created_at)),
    meta: { label: 'تاريخ الإرسال' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => {
        const items = [
          h(DropdownMenuItem, {
            onClick: () => router.visit(`/admin/messages/${row.original.id}`),
          }, 'عرض الرسالة'),
        ]
        if (isManager) {
          if (row.original.status !== 'replied') {
            items.push(h(DropdownMenuItem, {
              onClick: () => markStatus(row.original, 'replied'),
            }, 'وسم: تم الرد'))
          }
          if (row.original.status !== 'archived') {
            items.push(h(DropdownMenuItem, {
              onClick: () => markStatus(row.original, 'archived'),
            }, 'أرشفة'))
          }
          items.push(h(DropdownMenuItem, {
            class: 'text-danger',
            onClick: () => destroyMessage(row.original),
          }, 'حذف'))
        }
        return items
      },
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="رسائل التواصل" description="رسائل الزوار والعملاء المرسلة من صفحة الدعم." />

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="إجمالي الرسائل"
          :value="stats.total"
          :trend="stats.total === 0 ? 'لا رسائل بعد' : `جديدة: ${statusShare}%`"
          trend-direction="neutral"
        />
        <StatCard
          title="جديدة"
          :value="stats.new"
          :trend="stats.new > 0 ? 'بانتظار الرد' : 'لا جديدة'"
          :trend-direction="stats.new > 0 ? 'down' : 'neutral'"
        />
        <StatCard
          title="تم الرد"
          :value="stats.replied"
          :trend="stats.total > 0 ? `${repliedShare}% من الإجمالي` : ''"
          trend-direction="up"
        />
        <StatCard
          title="آخر 7 أيام"
          :value="stats.this_week"
          :trend="stats.this_week > 0 ? `+${stats.this_week} هذا الأسبوع` : 'لا انضمامات'"
          :trend-direction="stats.this_week > 0 ? 'up' : 'neutral'"
        />
      </div>

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="messages.data"
          :server-meta="messages"
          :on-page-change="goToPage"
          empty-text="لا توجد رسائل تطابق الفلاتر."
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
                    placeholder="ابحث بالاسم، البريد، الموضوع…"
                    class="ps-9 h-9"
                  />
                </div>
                <select
                  v-model="status"
                  name="status"
                  aria-label="فلتر الحالة"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الحالات</option>
                  <option value="new">جديدة</option>
                  <option value="read">مقروءة</option>
                  <option value="replied">تم الرد</option>
                  <option value="archived">مؤرشفة</option>
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
