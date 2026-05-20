<script setup>
import { ref, computed, h } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  FormGroup,
  Modal,
  ConfirmModal,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  services: { type: Array, default: () => [] },
})

const page = usePage()
const isManager = (() => page.props?.auth?.user?.role === 'manager')()

const rows = computed(() => props.doctors.map(d => ({
  ...d,
  doctor_name: d.user?.name ?? '—',
  services_count: d.services?.length ?? 0,
})))

const q = ref('')

const filteredRows = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return rows.value
  return rows.value.filter(r => {
    const hay = `${r.doctor_name ?? ''} ${r.specialty ?? ''}`.toLowerCase()
    return hay.includes(term)
  })
})

function applyFilters() {
  // Client-side filtering — handled by computed.
}

function resetFilters() {
  q.value = ''
}

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  specialty: '',
  bio: '',
  is_bookable: true,
  display_order: 0,
  services: [],
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.is_bookable = true
  form.display_order = 0
  form.services = []
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.name = row.user?.name ?? ''
  form.email = row.user?.email ?? ''
  form.password = ''
  form.password_confirmation = ''
  form.specialty = row.specialty
  form.bio = row.bio ?? ''
  form.is_bookable = row.is_bookable
  form.display_order = row.display_order
  form.services = (row.services ?? []).map(s => ({
    service_id: s.id,
    price_override: s.pivot?.price_override ?? null,
  }))
  showModal.value = true
}

function submitForm() {
  if (editingId.value) {
    form.put(`/admin/doctors/${editingId.value}`, {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/admin/doctors', {
      onSuccess: () => { showModal.value = false },
    })
  }
}

function addServiceRow() {
  form.services.push({ service_id: '', price_override: null })
}

function removeServiceRow(index) {
  form.services.splice(index, 1)
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)
const deleteError = ref(null)

function openSchedule(row) {
  router.visit(`/admin/doctors/${row.id}/schedule`)
}

function askDelete(row) {
  deleteTarget.value = row
  deleteError.value = null
  confirmDelete.value = true
}

function doDelete() {
  deleteError.value = null
  useForm({}).delete(`/admin/doctors/${deleteTarget.value.id}`, {
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
    onError: (errors) => { deleteError.value = errors.delete ?? null },
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
    accessorKey: 'doctor_name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'specialty',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'التخصص' }),
    meta: { label: 'التخصص' },
  },
  {
    accessorKey: 'is_bookable',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'قابل للحجز' }),
    cell: ({ row }) => row.original.is_bookable ? 'نعم' : 'لا',
    meta: { label: 'قابل للحجز' },
  },
  {
    accessorKey: 'services_count',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الخدمات' }),
    meta: { label: 'الخدمات' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => [
        h(DropdownMenuItem, { onClick: () => openSchedule(row.original) }, 'الجدول'),
        h(DropdownMenuItem, { onClick: () => openEdit(row.original) }, 'تعديل'),
        h(DropdownMenuItem, { class: 'text-danger', onClick: () => askDelete(row.original) }, 'حذف'),
      ],
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6">
      <PageHeader title="الأطباء" description="إدارة الكادر الطبي وتخصّصاته والخدمات المسندة.">
        <template v-if="isManager" #action>
          <Button @click="openCreate">إضافة طبيب</Button>
        </template>
      </PageHeader>

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="filteredRows"
          empty-text="لا يوجد أطباء بعد."
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
                    placeholder="ابحث بالاسم أو التخصص…"
                    class="ps-9 h-9"
                  />
                </div>
                <Button type="submit" size="sm" class="h-9">تطبيق</Button>
                <Button type="button" variant="ghost" size="sm" class="h-9" @click="resetFilters">تفريغ</Button>
              </div>
              <AdminDataTableViewOptions :table="table" />
            </form>
          </template>
        </AdminDataTable>
      </div>
    </div>

    <Modal :open="showModal" :title="editingId ? 'تعديل الطبيب' : 'إضافة طبيب'" @update:open="showModal = $event">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email" required>
          <template #default="{ describedby }">
            <Input id="email" v-model="form.email" type="email" name="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="كلمة المرور" name="password" :error="form.errors.password" :required="!editingId">
          <template #default="{ describedby }">
            <Input id="password" v-model="form.password" type="password" name="password" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="تأكيد كلمة المرور" name="password_confirmation" :error="form.errors.password_confirmation" :required="!editingId">
          <template #default="{ describedby }">
            <Input id="password_confirmation" v-model="form.password_confirmation" type="password" name="password_confirmation" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="التخصص" name="specialty" :error="form.errors.specialty" required>
          <template #default="{ describedby }">
            <Input id="specialty" v-model="form.specialty" name="specialty" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="السيرة الذاتية" name="bio" :error="form.errors.bio">
          <template #default="{ describedby }">
            <textarea
              id="bio"
              v-model="form.bio"
              name="bio"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>

        <FormGroup label="قابل للحجز" name="is_bookable" :error="form.errors.is_bookable">
          <template #default>
            <input id="is_bookable" v-model="form.is_bookable" type="checkbox" name="is_bookable" class="h-4 w-4" />
          </template>
        </FormGroup>

        <FormGroup label="الترتيب" name="display_order" :error="form.errors.display_order">
          <template #default="{ describedby }">
            <Input id="display_order" v-model.number="form.display_order" type="number" name="display_order" min="0" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <div>
          <div class="mb-2 flex items-center justify-between">
            <span class="text-sm font-medium">الخدمات المسندة</span>
            <Button type="button" variant="outline" size="sm" @click="addServiceRow">+ إضافة خدمة</Button>
          </div>
          <div v-for="(row, idx) in form.services" :key="idx" class="mb-2 flex gap-2">
            <select
              v-model="row.service_id"
              class="flex-1 rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="" disabled>اختر الخدمة</option>
              <option v-for="svc in services" :key="svc.id" :value="svc.id">{{ svc.name }}</option>
            </select>
            <Input
              v-model="row.price_override"
              type="number"
              min="0"
              step="0.01"
              placeholder="سعر مخصص (اختياري)"
              class="w-36"
            />
            <Button type="button" variant="outline" size="sm" class="text-danger" @click="removeServiceRow(idx)">حذف</Button>
          </div>
          <div v-if="form.errors['services.0.service_id']" class="text-sm text-danger">
            {{ form.errors['services.0.service_id'] }}
          </div>
        </div>
      </form>
      <template #footer>
        <Button variant="outline" @click="showModal = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitForm">
          {{ editingId ? 'حفظ التعديلات' : 'إضافة' }}
        </Button>
      </template>
    </Modal>

    <ConfirmModal
      :open="confirmDelete"
      title="حذف الطبيب"
      :message="`هل أنت متأكد من حذف الطبيب «${deleteTarget?.user?.name ?? deleteTarget?.specialty}»؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    >
      <template v-if="deleteError" #description>
        <p class="text-sm text-danger">{{ deleteError }}</p>
      </template>
    </ConfirmModal>
  </AdminShell>
</template>
