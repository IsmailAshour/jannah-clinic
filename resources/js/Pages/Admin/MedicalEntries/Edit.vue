<script setup>
import { ref } from 'vue'
import { useForm, Link, router } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, FormSection, FormGroup, ConfirmModal } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Trash2, Plus, FileText, Download, Upload, Paperclip } from 'lucide-vue-next'

const props = defineProps({
  entry: { type: Object, default: null },
  prescriptions: { type: Array, default: () => [] },
  attachments: { type: Array, default: () => [] },
  appointment: { type: Object, required: true },
  customer: { type: Object, required: true },
})

// ---- Attachments upload ----
const attachmentForm = useForm({ file: null, title: '' })
const attachmentInputEl = ref(null)

function onPickFile(e) {
  attachmentForm.file = e.target.files?.[0] ?? null
}
function clearFilePick() {
  attachmentForm.file = null
  if (attachmentInputEl.value) attachmentInputEl.value.value = ''
}
function uploadAttachment() {
  if (!attachmentForm.file) return
  // Attachments are now bound to the appointment, not the medical entry,
  // so we can upload even before the entry is saved.
  attachmentForm.post(`/admin/appointments/${props.appointment.id}/medical-attachments`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      attachmentForm.reset()
      clearFilePick()
    },
  })
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)
function askDelete(att) {
  deleteTarget.value = att
  confirmDelete.value = true
}
function doDeleteAttachment() {
  if (!deleteTarget.value) return
  router.delete(`/admin/appointments/${props.appointment.id}/medical-attachments/${deleteTarget.value.id}`, {
    preserveScroll: true,
    onFinish: () => { confirmDelete.value = false; deleteTarget.value = null },
  })
}

function humanSize(bytes) {
  if (!bytes) return '—'
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
}
function fileIcon(mime) {
  return (mime || '').startsWith('image/') ? FileText : FileText
}

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

        <FormSection title="الملفّات المرفقة" description="تحاليل / أشعّة / صور وصفات قديمة — PDF أو صورة، حدّ أقصى 10MB.">
          <div class="space-y-3">
            <ul v-if="attachments.length" class="space-y-2">
              <li
                v-for="att in attachments"
                :key="att.id"
                class="flex items-center gap-3 p-3 rounded-md border border-border-default bg-surface-card"
              >
                <component :is="fileIcon(att.mime_type)" class="w-5 h-5 text-brand shrink-0" aria-hidden="true" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold text-text-primary truncate">{{ att.title || att.original_filename }}</p>
                  <p class="text-xs text-text-tertiary">
                    {{ humanSize(att.file_size) }} · رفع: {{ att.uploaded_by_name || '—' }}
                  </p>
                </div>
                <a
                  :href="att.file_url"
                  target="_blank"
                  rel="noopener"
                  class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:underline"
                >
                  <Download class="w-3.5 h-3.5" aria-hidden="true" />
                  تنزيل
                </a>
                <button
                  type="button"
                  class="inline-flex items-center justify-center w-7 h-7 rounded-md text-danger hover:bg-danger/5"
                  :aria-label="`حذف ${att.original_filename}`"
                  @click="askDelete(att)"
                >
                  <Trash2 class="w-4 h-4" aria-hidden="true" />
                </button>
              </li>
            </ul>
            <p v-else class="text-xs text-text-tertiary">لا ملفّات مرفقة بعد.</p>

            <div class="rounded-md border border-dashed border-border-default p-3 space-y-2">
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                <div class="sm:col-span-2">
                  <label class="block text-xs text-text-secondary mb-1">عنوان الملف (اختياري)</label>
                  <Input v-model="attachmentForm.title" placeholder="مثلًا: تحليل دم 2026" />
                </div>
                <input
                  ref="attachmentInputEl"
                  type="file"
                  accept="application/pdf,image/jpeg,image/png,image/webp"
                  class="block w-full text-xs text-text-secondary file:me-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand/10 file:text-brand file:font-medium hover:file:bg-brand/15"
                  @change="onPickFile"
                />
              </div>
              <p v-if="attachmentForm.errors.file" class="text-xs text-danger">{{ attachmentForm.errors.file }}</p>
              <div class="flex justify-end">
                <Button
                  type="button"
                  :disabled="!attachmentForm.file || attachmentForm.processing"
                  @click="uploadAttachment"
                >
                  <Upload class="w-4 h-4 me-1" aria-hidden="true" />
                  رفع الملف
                </Button>
              </div>
            </div>
          </div>
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

    <ConfirmModal
      :open="confirmDelete"
      title="حذف الملف"
      :message="`هل أنت متأكّد من حذف الملف «${deleteTarget?.title || deleteTarget?.original_filename}»؟ لا يمكن التراجع.`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDeleteAttachment"
    />
  </AdminShell>
</template>
