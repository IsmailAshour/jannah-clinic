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

const props = defineProps({ areas: { type: Array, default: () => [] } })

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  name: '',
  is_active: true,
  display_order: 0,
})

function openCreate() {
  editingId.value = null
  form.reset()
  form.is_active = true
  form.display_order = 0
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.name = row.name
  form.is_active = row.is_active
  form.display_order = row.display_order
  showModal.value = true
}

function submitForm() {
  if (editingId.value) {
    form.put(`/admin/coverage/${editingId.value}`, {
      onSuccess: () => { showModal.value = false },
    })
  } else {
    form.post('/admin/coverage', {
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
  useForm({}).delete(`/admin/coverage/${deleteTarget.value.id}`, {
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
    onError: (errors) => {
      deleteError.value = errors.delete ?? 'لا يمكن حذف هذه المنطقة.'
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
      <PageHeader title="مناطق الخدمة المنزلية">
        <template #action>
          <Button @click="openCreate">إضافة منطقة</Button>
        </template>
      </PageHeader>

      <AdminDataTable
        :columns="columns"
        :data="areas"
        filter-column="name"
        filter-placeholder="ابحث في المناطق…"
        empty-text="لا توجد مناطق بعد."
      />
    </div>

    <Modal :open="showModal" :title="editingId ? 'تعديل المنطقة' : 'إضافة منطقة'" @update:open="showModal = $event">
      <form class="space-y-4" @submit.prevent="submitForm">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
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
      title="حذف المنطقة"
      :message="deleteError ? `هل أنت متأكد من حذف منطقة «${deleteTarget?.name}»؟\n\n${deleteError}` : `هل أنت متأكد من حذف منطقة «${deleteTarget?.name}»؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    />
  </AdminShell>
</template>
