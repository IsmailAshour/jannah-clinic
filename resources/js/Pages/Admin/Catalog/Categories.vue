<script setup>
import { ref, h } from 'vue'
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

const props = defineProps({ categories: { type: Array, default: () => [] } })

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  name: '',
  slug: '',
  color_variant: 'brand',
  display_order: 0,
  is_active: true,
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.color_variant = 'brand'
  form.is_active = true
  form.display_order = 0
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.name = row.name
  form.slug = row.slug
  form.color_variant = row.color_variant
  form.display_order = row.display_order
  form.is_active = row.is_active
  showModal.value = true
}

function submitForm() {
  if (editingId.value) {
    form.put(`/admin/catalog/categories/${editingId.value}`, {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/admin/catalog/categories', {
      onSuccess: () => { showModal.value = false },
    })
  }
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)
const deleteError = ref('')

function askDelete(row) {
  deleteTarget.value = row
  deleteError.value = ''
  confirmDelete.value = true
}

function doDelete() {
  deleteError.value = ''
  useForm({}).delete(`/admin/catalog/categories/${deleteTarget.value.id}`, {
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
    onError: (errors) => {
      deleteError.value = errors.delete ?? 'لا يمكن حذف هذه الفئة.'
      confirmDelete.value = true
    },
  })
}

const columns = [
  {
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'slug',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'المعرّف' }),
    cell: ({ row }) => h('span', { dir: 'ltr' }, row.original.slug),
    meta: { label: 'المعرّف' },
  },
  {
    accessorKey: 'color_variant',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'اللون' }),
    meta: { label: 'اللون' },
  },
  {
    accessorKey: 'display_order',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الترتيب' }),
    meta: { label: 'الترتيب' },
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
      <PageHeader title="فئات الخدمات">
        <template #action>
          <Button @click="openCreate">إضافة فئة</Button>
        </template>
      </PageHeader>

      <AdminDataTable
        :columns="columns"
        :data="categories"
        filter-column="name"
        filter-placeholder="ابحث في الفئات…"
        empty-text="لا توجد فئات بعد."
      />
    </div>

    <Modal :open="showModal" :title="editingId ? 'تعديل الفئة' : 'إضافة فئة'" @update:open="showModal = $event">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="المعرّف (slug)" name="slug" :error="form.errors.slug" required>
          <template #default="{ describedby }">
            <Input id="slug" v-model="form.slug" name="slug" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="نوع اللون" name="color_variant" :error="form.errors.color_variant" required>
          <template #default="{ describedby }">
            <select
              id="color_variant"
              v-model="form.color_variant"
              name="color_variant"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="brand">brand</option>
              <option value="gold">gold</option>
            </select>
          </template>
        </FormGroup>

        <FormGroup label="الترتيب" name="display_order" :error="form.errors.display_order">
          <template #default="{ describedby }">
            <Input id="display_order" v-model.number="form.display_order" type="number" name="display_order" min="0" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="نشطة" name="is_active" :error="form.errors.is_active">
          <template #default>
            <input id="is_active" v-model="form.is_active" type="checkbox" name="is_active" class="h-4 w-4" />
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
      title="حذف الفئة"
      :message="deleteError ? `هل أنت متأكد من حذف فئة «${deleteTarget?.name}»؟\n\n${deleteError}` : `هل أنت متأكد من حذف فئة «${deleteTarget?.name}»؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    />
  </AdminShell>
</template>
