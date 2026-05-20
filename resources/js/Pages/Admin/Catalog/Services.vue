<script setup>
import { ref, computed, h } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  FormGroup,
  Modal,
  ConfirmModal,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
})

const rows = computed(() => props.services.map(s => ({
  ...s,
  category_name: s.category?.name ?? '—',
})))

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  category_id: '',
  name: '',
  description: '',
  base_price: 0,
  duration_minutes: 30,
  home_service_enabled: false,
  icon_key: '',
  is_active: true,
  display_order: 0,
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.is_active = true
  form.display_order = 0
  form.duration_minutes = 30
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.category_id = row.category_id
  form.name = row.name
  form.description = row.description ?? ''
  form.base_price = row.base_price
  form.duration_minutes = row.duration_minutes
  form.home_service_enabled = row.home_service_enabled
  form.icon_key = row.icon_key ?? ''
  form.is_active = row.is_active
  form.display_order = row.display_order
  showModal.value = true
}

function submitForm() {
  if (editingId.value) {
    form.put(`/admin/catalog/services/${editingId.value}`, {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/admin/catalog/services', {
      onSuccess: () => { showModal.value = false },
    })
  }
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)

function askDelete(row) {
  deleteTarget.value = row
  confirmDelete.value = true
}

function doDelete() {
  useForm({}).delete(`/admin/catalog/services/${deleteTarget.value.id}`, {
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
  })
}

const columns = [
  {
    accessorKey: 'category_name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الفئة' }),
    meta: { label: 'الفئة' },
  },
  {
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'base_price',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'السعر' }),
    cell: ({ row }) => `${row.original.base_price} ₪`,
    meta: { label: 'السعر' },
  },
  {
    accessorKey: 'duration_minutes',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'المدة (د)' }),
    cell: ({ row }) => `${row.original.duration_minutes} دقيقة`,
    meta: { label: 'المدة' },
  },
  {
    accessorKey: 'home_service_enabled',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'خدمة منزلية' }),
    cell: ({ row }) => row.original.home_service_enabled ? 'نعم' : 'لا',
    meta: { label: 'خدمة منزلية' },
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'نشطة' }),
    cell: ({ row }) => row.original.is_active ? 'نعم' : 'لا',
    meta: { label: 'نشطة' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => [
        h(DropdownMenuItem, { onClick: () => openEdit(row.original) }, 'تعديل'),
        h(DropdownMenuItem, { class: 'text-danger', onClick: () => askDelete(row.original) }, 'حذف'),
      ],
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="الخدمات">
        <template #action>
          <Button @click="openCreate">إضافة خدمة</Button>
        </template>
      </PageHeader>

      <AdminDataTable
        :columns="columns"
        :data="rows"
        filter-column="name"
        filter-placeholder="ابحث في الخدمات…"
        empty-text="لا توجد خدمات بعد."
      />
    </div>

    <Modal :open="showModal" :title="editingId ? 'تعديل الخدمة' : 'إضافة خدمة'" @update:open="showModal = $event">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormGroup label="الفئة" name="category_id" :error="form.errors.category_id" required>
          <template #default="{ describedby }">
            <select
              id="category_id"
              v-model="form.category_id"
              name="category_id"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="" disabled>اختر الفئة</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </template>
        </FormGroup>

        <FormGroup label="اسم الخدمة" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الوصف" name="description" :error="form.errors.description">
          <template #default="{ describedby }">
            <textarea
              id="description"
              v-model="form.description"
              name="description"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>

        <FormGroup label="السعر الأساسي (₪)" name="base_price" :error="form.errors.base_price" required>
          <template #default="{ describedby }">
            <Input id="base_price" v-model.number="form.base_price" type="number" name="base_price" min="0" step="0.01" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="المدة (دقيقة)" name="duration_minutes" :error="form.errors.duration_minutes" required>
          <template #default="{ describedby }">
            <Input id="duration_minutes" v-model.number="form.duration_minutes" type="number" name="duration_minutes" min="1" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="خدمة منزلية" name="home_service_enabled" :error="form.errors.home_service_enabled">
          <template #default>
            <input id="home_service_enabled" v-model="form.home_service_enabled" type="checkbox" name="home_service_enabled" class="h-4 w-4" />
          </template>
        </FormGroup>

        <FormGroup label="مفتاح الأيقونة" name="icon_key" :error="form.errors.icon_key">
          <template #default="{ describedby }">
            <Input id="icon_key" v-model="form.icon_key" name="icon_key" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="نشطة" name="is_active" :error="form.errors.is_active">
          <template #default>
            <input id="is_active" v-model="form.is_active" type="checkbox" name="is_active" class="h-4 w-4" />
          </template>
        </FormGroup>

        <FormGroup label="الترتيب" name="display_order" :error="form.errors.display_order">
          <template #default="{ describedby }">
            <Input id="display_order" v-model.number="form.display_order" type="number" name="display_order" min="0" :aria-describedby="describedby" />
          </template>
        </FormGroup>
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
      title="حذف الخدمة"
      :message="`هل أنت متأكد من حذف خدمة «${deleteTarget?.name}»؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    />
  </AdminShell>
</template>
