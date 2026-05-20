<script setup>
import { ref, h } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { UserPlus } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
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
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
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
      }, 'عرض'),
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="العملاء">
        <template v-if="isManager" #action>
          <Button @click="openCreate">
            <UserPlus class="h-4 w-4" aria-hidden="true" />
            <span>إضافة عميل</span>
          </Button>
        </template>
      </PageHeader>

      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters()">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (الاسم، البريد، الهاتف)</label>
          <Input id="q" v-model="q" name="q" placeholder="ابحث..." class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            id="status"
            v-model="status"
            name="status"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <AdminDataTable
        :columns="columns"
        :data="customers.data"
        :server-meta="customers"
        :on-page-change="goToPage"
        empty-text="لا يوجد عملاء."
      />
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
