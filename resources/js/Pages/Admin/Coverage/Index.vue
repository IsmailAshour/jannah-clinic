<script setup>
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  DataTable,
  FormGroup,
  Modal,
  ConfirmModal,
  PageStates,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({ areas: { type: Array, default: () => [] } })

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'display_order', label: 'الترتيب' },
  { key: 'is_active', label: 'نشطة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]

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
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="مناطق الخدمة المنزلية">
        <template #action>
          <Button @click="openCreate">إضافة منطقة</Button>
        </template>
      </PageHeader>

      <PageStates :is-empty="areas.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا توجد مناطق بعد.</div>
        </template>
        <DataTable :columns="columns" :rows="areas">
          <template #cell-is_active="{ row }">
            {{ row.is_active ? 'نعم' : 'لا' }}
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" @click="openEdit(row)">تعديل</Button>
              <Button variant="outline" size="sm" class="text-danger" @click="askDelete(row)">حذف</Button>
            </div>
          </template>
        </DataTable>
      </PageStates>
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
