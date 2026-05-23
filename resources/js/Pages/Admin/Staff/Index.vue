<script setup>
import { computed, h, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { UserPlus, AlertCircle, Search } from 'lucide-vue-next'
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
  ConfirmModal,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  staff: { type: Array, default: () => [] },
  authedUserId: { type: Number, required: true },
  filters: { type: Object, default: () => ({}) },
  stats: { type: Object, default: () => ({ total: 0, managers: 0, receptionists: 0, active: 0, inactive: 0 }) },
})

const page = usePage()

// Reactive temp password (shown once after create or reset-password). Computed
// so it reflects the latest flash value across same-URL Inertia redirects.
const tempPassword = computed(() => page.props.flash?.temp_password ?? null)
const showTempPasswordModal = computed(() => tempPassword.value !== null)
function dismissTempPassword() {
  router.reload({ only: [] })
}

// ---- Filters ----
const q = ref(props.filters.q ?? '')
const roleFilter = ref(props.filters.role ?? '')
const statusFilter = ref(props.filters.status ?? '')

function applyFilters() {
  router.get('/admin/staff', {
    q: q.value || undefined,
    role: roleFilter.value || undefined,
    status: statusFilter.value || undefined,
  }, { preserveScroll: true, replace: true })
}
function resetFilters() {
  q.value = ''
  roleFilter.value = ''
  statusFilter.value = ''
  applyFilters()
}

// ---- Create / edit modal ----
const showFormModal = ref(false)
const editingId = ref(null)

const form = useForm({
  name: '',
  email: '',
  phone: '',
  role: 'receptionist',
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.clearErrors()
  form.role = 'receptionist'
  showFormModal.value = true
}
function openEdit(row) {
  editingId.value = row.id
  form.name = row.name
  form.email = row.email ?? ''
  form.phone = row.phone ?? ''
  form.role = row.role
  form.clearErrors()
  showFormModal.value = true
}
function submitForm() {
  const onSuccess = () => { showFormModal.value = false }
  if (editingId.value) {
    form.put(`/admin/staff/${editingId.value}`, { onSuccess, preserveScroll: true })
  } else {
    form.post('/admin/staff', { onSuccess, preserveScroll: true })
  }
}

// ---- Toggle active / reset password / delete ----
function toggleActive(row) {
  router.post(`/admin/staff/${row.id}/toggle-active`, {}, { preserveScroll: true })
}

const confirmReset = ref(false)
const resetTarget = ref(null)
function askResetPassword(row) { resetTarget.value = row; confirmReset.value = true }
function doResetPassword() {
  if (!resetTarget.value) return
  router.post(`/admin/staff/${resetTarget.value.id}/reset-password`, {}, {
    preserveScroll: true,
    onFinish: () => { confirmReset.value = false; resetTarget.value = null },
  })
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)
function askDelete(row) { deleteTarget.value = row; confirmDelete.value = true }
function doDelete() {
  if (!deleteTarget.value) return
  router.delete(`/admin/staff/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onFinish: () => { confirmDelete.value = false; deleteTarget.value = null },
  })
}

// ---- Table columns ----
function roleLabel(r) {
  if (r === 'manager') return 'مدير'
  if (r === 'receptionist') return 'موظّف استقبال'
  return r
}
function roleVariant(r) {
  if (r === 'manager') return 'success'
  if (r === 'receptionist') return 'info'
  return 'info'
}

const columns = [
  {
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    cell: ({ row }) => h('div', { class: 'flex flex-col' }, [
      h('span', { class: 'font-medium text-text-primary' }, row.original.name),
      h('span', { class: 'text-xs text-text-tertiary', dir: 'ltr' }, row.original.email || row.original.phone || '—'),
    ]),
    meta: { label: 'الاسم', primary: true },
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
    accessorKey: 'role',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الدور' }),
    cell: ({ row }) => h(StatusBadge, {
      type: roleVariant(row.original.role),
      label: roleLabel(row.original.role),
    }),
    meta: { label: 'الدور' },
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => h(StatusBadge, {
      type: row.original.is_active ? 'success' : 'danger',
      label: row.original.is_active ? 'نشط' : 'مُعطَّل',
    }),
    meta: { label: 'الحالة' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => {
      const isSelf = row.original.id === props.authedUserId
      return h(AdminDataTableRowActions, null, {
        default: () => [
          h(DropdownMenuItem, { onClick: () => openEdit(row.original) }, 'تعديل'),
          h(DropdownMenuItem, { onClick: () => askResetPassword(row.original) }, 'إعادة تعيين كلمة المرور'),
          ...(isSelf ? [] : [
            h(DropdownMenuItem, { onClick: () => toggleActive(row.original) }, row.original.is_active ? 'تعطيل' : 'تفعيل'),
            h(DropdownMenuItem, { onClick: () => askDelete(row.original), class: 'text-danger' }, 'حذف'),
          ]),
        ],
      })
    },
  },
]

const activeShare = computed(() => {
  if (props.stats.total === 0) return 0
  return Math.round((props.stats.active / props.stats.total) * 100)
})
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <!-- 1 — Header -->
      <PageHeader title="الفريق الإداري" description="إدارة المدراء وموظّفي الاستقبال — لإدارة الأطبّاء استخدم صفحة الفريق الطبيّ.">
        <template #action>
          <Button @click="openCreate">
            <UserPlus class="h-4 w-4" aria-hidden="true" />
            <span>إضافة موظّف</span>
          </Button>
        </template>
      </PageHeader>

      <!-- 2 — Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          title="إجمالي الفريق"
          :value="stats.total"
          :trend="stats.total === 0 ? 'لا يوجد موظّفون بعد' : `${activeShare}% نشط`"
          trend-direction="neutral"
        />
        <StatCard
          title="المدراء"
          :value="stats.managers"
          trend-direction="neutral"
        />
        <StatCard
          title="موظّفو الاستقبال"
          :value="stats.receptionists"
          trend-direction="neutral"
        />
        <StatCard
          title="المُعطَّلون"
          :value="stats.inactive"
          :trend-direction="stats.inactive > 0 ? 'down' : 'neutral'"
        />
      </div>

      <!-- 3 — Toolbar + 4 — Table -->
      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="staff"
          empty-text="لا يوجد موظّفون يطابقون الفلاتر."
        >
          <template #toolbar="{ table }">
            <form class="flex flex-wrap items-center justify-between gap-2 w-full" @submit.prevent="applyFilters">
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
                  v-model="roleFilter"
                  name="role"
                  aria-label="فلتر الدور"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الأدوار</option>
                  <option value="manager">مدير</option>
                  <option value="receptionist">موظّف استقبال</option>
                </select>
                <select
                  v-model="statusFilter"
                  name="status"
                  aria-label="فلتر الحالة"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الحالات</option>
                  <option value="active">نشط</option>
                  <option value="inactive">مُعطَّل</option>
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

    <!-- Create / Edit modal -->
    <Modal :open="showFormModal" :title="editingId ? 'تعديل موظّف' : 'إضافة موظّف'" size="md" @update:open="showFormModal = $event">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email" hint="يجب توفير البريد أو الهاتف.">
          <template #default="{ describedby }">
            <Input id="email" v-model="form.email" type="email" name="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="رقم الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="phone" v-model="form.phone" name="phone" placeholder="05xxxxxxxx" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الدور" name="role" :error="form.errors.role" required>
          <template #default="{ describedby }">
            <select
              id="role"
              v-model="form.role"
              name="role"
              :aria-describedby="describedby"
              class="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 py-1 text-base md:text-sm"
            >
              <option value="manager">مدير</option>
              <option value="receptionist">موظّف استقبال</option>
            </select>
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showFormModal = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitForm">
          {{ editingId ? 'حفظ التعديلات' : 'إضافة' }}
        </Button>
      </template>
    </Modal>

    <!-- Reset password confirm -->
    <ConfirmModal
      :open="confirmReset"
      title="إعادة تعيين كلمة المرور"
      :message="`سيتمّ توليد كلمة مرور جديدة للموظّف «${resetTarget?.name}». ستظهر مرّة واحدة فقط — يجب نسخها لإعطائها له.`"
      confirm-text="إعادة تعيين"
      cancel-text="إلغاء"
      @update:open="confirmReset = $event"
      @confirm="doResetPassword"
    />

    <!-- Delete confirm -->
    <ConfirmModal
      :open="confirmDelete"
      title="حذف موظّف"
      :message="`هل أنت متأكّد من حذف «${deleteTarget?.name}» نهائيًّا؟ لا يمكن التراجع.`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    />

    <!-- Temp password reveal modal (shown once after create or reset) -->
    <Modal :open="showTempPasswordModal" title="كلمة المرور المؤقّتة" size="md" @update:open="(v) => !v && dismissTempPassword()">
      <div class="space-y-3">
        <div class="rounded-lg bg-warning/5 border border-warning/30 p-3 text-sm text-warning inline-flex items-start gap-2">
          <AlertCircle class="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
          <span>هذه آخر مرّة سترى فيها كلمة المرور — انسخها الآن وأَعطها للموظّف. لن تُعرض مرّة أخرى.</span>
        </div>
        <div class="rounded-lg border border-border-default bg-surface-card p-3 font-mono text-base text-text-primary text-center select-all" dir="ltr">
          {{ tempPassword }}
        </div>
      </div>
      <template #footer>
        <Button @click="dismissTempPassword">إغلاق</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
