<script setup>
import { ref, h, computed } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { UserPlus, Search } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  StatCard,
  StatusBadge,
  Modal,
  FormGroup,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  customers: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({ total: 0, active: 0, inactive: 0, new_this_month: 0 }) },
})

const page = usePage()
const isManager = (() => page.props?.auth?.user?.role === 'manager')()

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? '')

function applyFilters(extraQuery = {}) {
  router.get(
    '/admin/customers',
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

// Trend hints — small contextual signals under each stat
const activeShare = computed(() =>
  props.stats.total > 0 ? Math.round((props.stats.active / props.stats.total) * 100) : 0
)
const inactiveShare = computed(() =>
  props.stats.total > 0 ? Math.round((props.stats.inactive / props.stats.total) * 100) : 0
)

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

const showCreate = ref(false)
const form = useForm({
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: '',
  notes: '',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showCreate.value = true
}

function submitCreate() {
  form.post('/admin/customers', { preserveScroll: false })
}

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
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    cell: ({ row }) => h('div', { class: 'flex flex-col' }, [
      h('span', { class: 'font-medium text-text-primary' }, row.original.name),
      h('span', { class: 'text-xs text-text-tertiary', dir: 'ltr' }, row.original.email || row.original.phone || '—'),
    ]),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'phone',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الهاتف' }),
    cell: ({ row }) => h('span', { dir: 'ltr' }, row.original.phone || '—'),
    meta: { label: 'الهاتف' },
  },
  {
    accessorKey: 'email',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'البريد' }),
    cell: ({ row }) => h('span', { dir: 'ltr' }, row.original.email || '—'),
    meta: { label: 'البريد' },
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => h(StatusBadge, {
      type: row.original.is_active ? 'success' : 'danger',
      label: row.original.is_active ? 'نشط' : 'غير نشط',
    }),
    meta: { label: 'الحالة' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => h(DropdownMenuItem, {
        onClick: () => router.visit(`/admin/customers/${row.original.id}`),
      }, 'عرض التفاصيل'),
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <!-- 1 — Header -->
      <PageHeader title="العملاء" description="إدارة قاعدة العملاء ومتابعة حالاتهم.">
        <template v-if="isManager" #action>
          <Button @click="openCreate">
            <UserPlus class="h-4 w-4" aria-hidden="true" />
            <span>إضافة عميل</span>
          </Button>
        </template>
      </PageHeader>

      <!-- 2 — Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="إجمالي العملاء"
          :value="stats.total"
          :trend="stats.total === 0 ? 'لا يوجد عملاء بعد' : `نشط: ${activeShare}%`"
          trend-direction="neutral"
        />
        <StatCard
          title="العملاء النشطون"
          :value="stats.active"
          :trend="stats.total > 0 ? `${activeShare}% من الإجمالي` : ''"
          trend-direction="up"
        />
        <StatCard
          title="غير النشطين"
          :value="stats.inactive"
          :trend="stats.total > 0 ? `${inactiveShare}% من الإجمالي` : ''"
          :trend-direction="stats.inactive > 0 ? 'down' : 'neutral'"
        />
        <StatCard
          title="جدد هذا الشهر"
          :value="stats.new_this_month"
          :trend="stats.new_this_month > 0 ? `+${stats.new_this_month} منذ بداية الشهر` : 'لا انضمامات بعد'"
          :trend-direction="stats.new_this_month > 0 ? 'up' : 'neutral'"
        />
      </div>

      <!-- 3 — Toolbar + 4 — Table (single surface, single toolbar row) -->
      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="customers.data"
          :server-meta="customers"
          :on-page-change="goToPage"
          empty-text="لا يوجد عملاء يطابقون الفلاتر."
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
                    placeholder="ابحث بالاسم، البريد، الهاتف…"
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
                  <option value="active">نشط</option>
                  <option value="inactive">غير نشط</option>
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

    <Modal :open="showCreate" title="إضافة عميل" @update:open="showCreate = $event">
      <form class="space-y-4" @submit.prevent="submitCreate">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="c-name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email" hint="يجب توفير البريد أو الهاتف.">
          <template #default="{ describedby }">
            <Input id="c-email" v-model="form.email" type="email" name="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="c-phone" v-model="form.phone" name="phone" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="تاريخ الميلاد" name="date_of_birth" :error="form.errors.date_of_birth">
          <template #default="{ describedby }">
            <Input id="c-dob" v-model="form.date_of_birth" type="date" name="date_of_birth" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الجنس" name="gender" :error="form.errors.gender">
          <template #default="{ describedby }">
            <select
              id="c-gender"
              v-model="form.gender"
              name="gender"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="">—</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </template>
        </FormGroup>

        <FormGroup label="ملاحظات" name="notes" :error="form.errors.notes" hint="ملاحظات داخلية للطاقم (اختياري).">
          <template #default="{ describedby }">
            <textarea
              id="c-notes"
              v-model="form.notes"
              name="notes"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>

        <p class="text-xs text-text-secondary">
          سيتم توليد كلمة مرور مؤقتة تلقائيًا — تُعرض مرة واحدة في صفحة العميل بعد الإنشاء. شارِكها مع العميل.
        </p>
      </form>
      <template #footer>
        <Button variant="outline" @click="showCreate = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitCreate">إنشاء</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
