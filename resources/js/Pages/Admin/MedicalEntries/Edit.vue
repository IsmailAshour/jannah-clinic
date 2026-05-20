<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, FormSection, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Trash2, Plus } from 'lucide-vue-next'

const props = defineProps({
  entry: { type: Object, default: null },
  prescriptions: { type: Array, default: () => [] },
  appointment: { type: Object, required: true },
  customer: { type: Object, required: true },
})

const isNew = !props.entry?.id

const form = useForm({
  visible_summary: props.entry?.visible_summary ?? '',
  staff_notes: props.entry?.staff_notes ?? '',
  prescriptions: props.prescriptions.map((p) => ({ ...p })),
})

function addPrescription() {
  form.prescriptions.push({ medication_name: '', dosage: '', frequency: '', duration: '', notes: '' })
}

function removePrescription(i) {
  form.prescriptions.splice(i, 1)
}

function save() {
  if (isNew) {
    form.post(`/admin/appointments/${props.appointment.id}/medical-entry`, { preserveScroll: true })
  } else {
    form.put(`/admin/medical-entries/${props.entry.id}`, { preserveScroll: true })
  }
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6 max-w-3xl">
      <PageHeader :title="`السجل الطبي — ${customer.name}`" />
      <p class="text-sm text-text-secondary">موعد بتاريخ {{ formatDate(appointment.start_at) }}</p>

      <form class="space-y-6" @submit.prevent="save">
        <FormSection title="خلاصة الزيارة" description="يراها العميل في بوابته.">
          <FormGroup label="الخلاصة" name="visible_summary" :error="form.errors.visible_summary" required>
            <template #default="{ describedby }">
              <textarea
                id="visible_summary"
                v-model="form.visible_summary"
                name="visible_summary"
                rows="4"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
              />
            </template>
          </FormGroup>
        </FormSection>

        <FormSection title="ملاحظات داخلية" description="لن يراها العميل — مرئية للطاقم الطبي فقط.">
          <FormGroup label="الملاحظات" name="staff_notes" :error="form.errors.staff_notes">
            <template #default="{ describedby }">
              <textarea
                id="staff_notes"
                v-model="form.staff_notes"
                name="staff_notes"
                rows="3"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
              />
            </template>
          </FormGroup>
        </FormSection>

        <FormSection title="الوصفات الطبية">
          <div class="space-y-3">
            <div
              v-for="(p, i) in form.prescriptions"
              :key="i"
              class="grid grid-cols-1 md:grid-cols-2 gap-3 p-3 rounded-md border border-border-default bg-surface-card"
            >
              <Input v-model="p.medication_name" placeholder="اسم الدواء" :aria-label="`prescription-${i}-name`" />
              <Input v-model="p.dosage" placeholder="الجرعة (مثل: 500 ملغ)" :aria-label="`prescription-${i}-dosage`" />
              <Input v-model="p.frequency" placeholder="التكرار (مثل: مرتان يوميًا)" :aria-label="`prescription-${i}-frequency`" />
              <Input v-model="p.duration" placeholder="المدة (مثل: 7 أيام)" :aria-label="`prescription-${i}-duration`" />
              <Input v-model="p.notes" placeholder="ملاحظات (اختياري)" class="md:col-span-2" :aria-label="`prescription-${i}-notes`" />
              <div class="md:col-span-2 flex justify-end">
                <Button type="button" variant="ghost" @click="removePrescription(i)">
                  <Trash2 class="size-4 me-1" /> حذف
                </Button>
              </div>
            </div>
            <div>
              <Button type="button" variant="outline" @click="addPrescription">
                <Plus class="size-4 me-1" /> إضافة وصفة
              </Button>
            </div>
          </div>
        </FormSection>

        <div class="flex justify-end gap-2">
          <Link :href="`/admin/customers/${customer.id}`" class="text-sm underline self-center">رجوع لصفحة العميل</Link>
          <Button :disabled="form.processing" type="submit">حفظ</Button>
        </div>
      </form>
    </div>
  </AdminShell>
</template>
