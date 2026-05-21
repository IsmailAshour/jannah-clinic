<script setup>
import { ref, computed, h } from 'vue'
import { useForm, router, usePage } from '@inertiajs/vue3'
import { Search, User as UserIcon } from 'lucide-vue-next'

const TEAM_ROLE_LABELS = {
  doctor: 'طبيب',
  nurse: 'ممرّض',
  physiotherapist: 'أخصّائي علاج طبيعي',
}
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
  team_role_label: TEAM_ROLE_LABELS[d.team_role] ?? d.team_role,
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
  team_role: 'doctor',
  is_bookable: true,
  display_order: 0,
  services: [],
  image: null,
  remove_image: false,
  _method: 'POST',
})

const currentImagePath = ref(null)
const imagePreview = ref(null)
const imageInputEl = ref(null)

function clearImagePreview() {
  imagePreview.value = null
  if (imageInputEl.value) imageInputEl.value.value = ''
}
function onImageChange(e) {
  const file = e.target.files?.[0] ?? null
  form.image = file
  if (file) {
    const reader = new FileReader()
    reader.onload = (ev) => { imagePreview.value = ev.target.result }
    reader.readAsDataURL(file)
    form.remove_image = false
  } else {
    imagePreview.value = null
  }
}
function clearImageSelection() {
  form.image = null
  clearImagePreview()
}
function markRemoveCurrentImage() {
  form.remove_image = true
  currentImagePath.value = null
}

function openCreate() {
  editingId.value = null
  form.reset()
  form.team_role = 'doctor'
  form.is_bookable = true
  form.display_order = 0
  form.services = []
  form.remove_image = false
  currentImagePath.value = null
  clearImagePreview()
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
  form.team_role = row.team_role ?? 'doctor'
  form.is_bookable = row.is_bookable
  form.display_order = row.display_order
  form.services = (row.services ?? []).map(s => ({
    service_id: s.id,
    price_override: s.pivot?.price_override ?? null,
  }))
  form.image = null
  form.remove_image = false
  currentImagePath.value = row.image_path ?? null
  clearImagePreview()
  showModal.value = true
}

function submitForm() {
  form.transform((data) => ({
    ...data,
    is_bookable: !!data.is_bookable,
    remove_image: !!data.remove_image,
  }))
  const onSuccess = () => { showModal.value = false }
  if (editingId.value) {
    form._method = 'PUT'
    form.post(`/admin/doctors/${editingId.value}`, { forceFormData: true, onSuccess })
  } else {
    form._method = 'POST'
    form.post('/admin/doctors', { forceFormData: true, onSuccess })
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
    id: 'avatar',
    enableHiding: false,
    enableSorting: false,
    header: () => '',
    cell: ({ row }) => {
      const src = row.original.image_path ? `/storage/${row.original.image_path}` : null
      return h('div', { class: 'flex items-center justify-center' }, [
        src
          ? h('img', { src, alt: row.original.user?.name ?? '', class: 'h-9 w-9 rounded-full object-cover ring-1 ring-border-default' })
          : h('div', { class: 'h-9 w-9 rounded-full bg-brand/10 text-brand grid place-items-center' }, [h(UserIcon, { class: 'h-4 w-4', 'aria-hidden': 'true' })]),
      ])
    },
    meta: { label: 'الصورة', headerClass: 'w-14', cellClass: 'w-14' },
  },
  {
    accessorKey: 'doctor_name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'team_role_label',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الدور' }),
    meta: { label: 'الدور' },
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
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="الفريق الطبي" description="إدارة فريق العيادة (أطبّاء، ممرّضين، أخصّائيين) وتخصّصاته والخدمات المسندة.">
        <template v-if="isManager" #action>
          <Button @click="openCreate">إضافة عضو</Button>
        </template>
      </PageHeader>

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="filteredRows"
          empty-text="لا يوجد أعضاء في الفريق بعد."
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

    <Modal :open="showModal" :title="editingId ? 'تعديل عضو الفريق' : 'إضافة عضو إلى الفريق'" @update:open="showModal = $event">
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

        <FormGroup label="الدور" name="team_role" :error="form.errors.team_role" required>
          <template #default="{ describedby }">
            <select
              id="team_role"
              v-model="form.team_role"
              name="team_role"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="doctor">طبيب</option>
              <option value="nurse">ممرّض</option>
              <option value="physiotherapist">أخصّائي علاج طبيعي</option>
            </select>
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

        <FormGroup label="الصورة الشخصية" name="image" :error="form.errors.image" hint="JPG / PNG / WEBP — حتى 4MB. تظهر في صفحات الموقع العامّة.">
          <template #default="{ describedby }">
            <div class="space-y-2">
              <div v-if="imagePreview" class="relative inline-block">
                <img :src="imagePreview" alt="معاينة الصورة الجديدة" class="h-24 w-24 object-cover rounded-full ring-2 ring-brand/30" />
                <button type="button" class="absolute -top-1 -end-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-danger text-white shadow text-xs" aria-label="إلغاء" @click="clearImageSelection">×</button>
              </div>
              <div v-else-if="currentImagePath" class="relative inline-block">
                <img :src="`/storage/${currentImagePath}`" alt="الصورة الحالية" class="h-24 w-24 object-cover rounded-full ring-2 ring-border-default" />
                <button type="button" class="absolute -top-1 -end-1 inline-flex h-6 w-6 items-center justify-center rounded-full bg-danger text-white shadow text-xs" aria-label="إزالة" @click="markRemoveCurrentImage">×</button>
              </div>
              <input
                id="image"
                ref="imageInputEl"
                type="file"
                name="image"
                accept="image/jpeg,image/png,image/webp"
                :aria-describedby="describedby"
                class="block w-full text-sm text-text-secondary file:me-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand/10 file:text-brand file:font-medium hover:file:bg-brand/15"
                @change="onImageChange"
              />
            </div>
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
      title="حذف العضو"
      :message="`هل أنت متأكد من حذف «${deleteTarget?.user?.name ?? deleteTarget?.specialty}»؟`"
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
