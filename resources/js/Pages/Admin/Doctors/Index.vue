<script setup>
import { ref, computed } from 'vue'
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

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  services: { type: Array, default: () => [] },
})

const columns = [
  { key: 'doctor_name', label: 'الاسم' },
  { key: 'specialty', label: 'التخصص' },
  { key: 'is_bookable', label: 'قابل للحجز' },
  { key: 'services_count', label: 'الخدمات' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]

const rows = computed(() => props.doctors.map(d => ({
  ...d,
  doctor_name: d.user?.name ?? '—',
  services_count: d.services?.length ?? 0,
})))

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
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="الأطباء">
        <template #action>
          <Button @click="openCreate">إضافة طبيب</Button>
        </template>
      </PageHeader>

      <PageStates :is-empty="doctors.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا يوجد أطباء بعد.</div>
        </template>
        <DataTable :columns="columns" :rows="rows">
          <template #cell-is_bookable="{ row }">
            {{ row.is_bookable ? 'نعم' : 'لا' }}
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
