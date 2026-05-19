<script setup>
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  DataTable,
  FormGroup,
  FormSection,
  Modal,
  ConfirmModal,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  doctor: { type: Object, required: true },
  schedules: { type: Array, default: () => [] },
  exceptions: { type: Array, default: () => [] },
})

const WEEKDAY_LABELS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']

function defaultRow(weekday) {
  return {
    weekday,
    morning_enabled: false,
    morning_start: '',
    morning_end: '',
    evening_enabled: false,
    evening_start: '',
    evening_end: '',
    slot_interval_minutes: 30,
  }
}

function buildInitialSchedules() {
  return WEEKDAY_LABELS.map((_, i) => {
    const existing = props.schedules.find(s => s.weekday === i)
    if (existing) {
      return {
        weekday: existing.weekday,
        morning_enabled: !!existing.morning_enabled,
        morning_start: existing.morning_start ?? '',
        morning_end: existing.morning_end ?? '',
        evening_enabled: !!existing.evening_enabled,
        evening_start: existing.evening_start ?? '',
        evening_end: existing.evening_end ?? '',
        slot_interval_minutes: existing.slot_interval_minutes ?? 30,
      }
    }
    return defaultRow(i)
  })
}

const scheduleForm = useForm({ schedules: buildInitialSchedules() })

function saveSchedule() {
  scheduleForm.put(`/admin/doctors/${props.doctor.id}/schedule`)
}

// Exceptions table
const exceptionColumns = [
  { key: 'date_display', label: 'التاريخ' },
  { key: 'type_display', label: 'النوع' },
  { key: 'note', label: 'ملاحظة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]

const exceptionRows = computed(() => props.exceptions.map(e => ({
  ...e,
  date_display: e.date,
  type_display: e.type === 'closed' ? 'مغلق' : 'ساعات مخصصة',
})))

// Add exception modal
const showAddException = ref(false)
const exceptionForm = useForm({
  date: '',
  type: 'closed',
  custom_start: '',
  custom_end: '',
  note: '',
})

function submitException() {
  exceptionForm.post(`/admin/doctors/${props.doctor.id}/exceptions`, {
    onSuccess: () => {
      showAddException.value = false
      exceptionForm.reset()
      exceptionForm.type = 'closed'
    },
  })
}

// Delete exception
const confirmDeleteException = ref(false)
const deleteExceptionTarget = ref(null)
const deleteExceptionError = ref(null)

function askDeleteException(row) {
  deleteExceptionTarget.value = row
  deleteExceptionError.value = null
  confirmDeleteException.value = true
}

function doDeleteException() {
  deleteExceptionError.value = null
  useForm({}).delete(`/admin/doctors/${props.doctor.id}/exceptions/${deleteExceptionTarget.value.id}`, {
    onSuccess: () => {
      confirmDeleteException.value = false
      deleteExceptionTarget.value = null
    },
    onError: (errors) => {
      deleteExceptionError.value = errors.delete ?? null
    },
  })
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-8">
      <PageHeader :title="`جدول الطبيب — ${doctor.user?.name ?? ''}`" />

      <!-- Weekly schedule -->
      <FormSection title="الجدول الأسبوعي">
        <div class="space-y-6">
          <div
            v-for="(row, idx) in scheduleForm.schedules"
            :key="row.weekday"
            class="rounded-md border border-border-default p-4 space-y-4"
          >
            <h3 class="font-semibold text-text-primary">{{ WEEKDAY_LABELS[row.weekday] }}</h3>

            <!-- Morning session -->
            <div class="space-y-2">
              <FormGroup :name="`schedules.${idx}.morning_enabled`" :error="scheduleForm.errors[`schedules.${idx}.morning_enabled`]">
                <template #default>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="scheduleForm.schedules[idx].morning_enabled"
                      type="checkbox"
                      class="h-4 w-4"
                    />
                    <span class="text-sm">فترة الصباح</span>
                  </label>
                </template>
              </FormGroup>
              <div v-if="scheduleForm.schedules[idx].morning_enabled" class="flex gap-3 flex-wrap">
                <FormGroup label="من" :name="`schedules.${idx}.morning_start`" :error="scheduleForm.errors[`schedules.${idx}.morning_start`]">
                  <template #default="{ describedby }">
                    <Input
                      v-model="scheduleForm.schedules[idx].morning_start"
                      type="time"
                      dir="ltr"
                      :aria-describedby="describedby"
                      class="w-36"
                    />
                  </template>
                </FormGroup>
                <FormGroup label="إلى" :name="`schedules.${idx}.morning_end`" :error="scheduleForm.errors[`schedules.${idx}.morning_end`]">
                  <template #default="{ describedby }">
                    <Input
                      v-model="scheduleForm.schedules[idx].morning_end"
                      type="time"
                      dir="ltr"
                      :aria-describedby="describedby"
                      class="w-36"
                    />
                  </template>
                </FormGroup>
              </div>
            </div>

            <!-- Evening session -->
            <div class="space-y-2">
              <FormGroup :name="`schedules.${idx}.evening_enabled`" :error="scheduleForm.errors[`schedules.${idx}.evening_enabled`]">
                <template #default>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input
                      v-model="scheduleForm.schedules[idx].evening_enabled"
                      type="checkbox"
                      class="h-4 w-4"
                    />
                    <span class="text-sm">فترة المساء</span>
                  </label>
                </template>
              </FormGroup>
              <div v-if="scheduleForm.schedules[idx].evening_enabled" class="flex gap-3 flex-wrap">
                <FormGroup label="من" :name="`schedules.${idx}.evening_start`" :error="scheduleForm.errors[`schedules.${idx}.evening_start`]">
                  <template #default="{ describedby }">
                    <Input
                      v-model="scheduleForm.schedules[idx].evening_start"
                      type="time"
                      dir="ltr"
                      :aria-describedby="describedby"
                      class="w-36"
                    />
                  </template>
                </FormGroup>
                <FormGroup label="إلى" :name="`schedules.${idx}.evening_end`" :error="scheduleForm.errors[`schedules.${idx}.evening_end`]">
                  <template #default="{ describedby }">
                    <Input
                      v-model="scheduleForm.schedules[idx].evening_end"
                      type="time"
                      dir="ltr"
                      :aria-describedby="describedby"
                      class="w-36"
                    />
                  </template>
                </FormGroup>
              </div>
            </div>

            <!-- Slot interval -->
            <FormGroup label="مدة الموعد (دقائق)" :name="`schedules.${idx}.slot_interval_minutes`" :error="scheduleForm.errors[`schedules.${idx}.slot_interval_minutes`]">
              <template #default="{ describedby }">
                <Input
                  v-model.number="scheduleForm.schedules[idx].slot_interval_minutes"
                  type="number"
                  min="5"
                  dir="ltr"
                  :aria-describedby="describedby"
                  class="w-28"
                />
              </template>
            </FormGroup>
          </div>
        </div>

        <div class="flex justify-end">
          <Button :disabled="scheduleForm.processing" @click="saveSchedule">حفظ الجدول</Button>
        </div>
      </FormSection>

      <!-- Schedule exceptions -->
      <FormSection title="استثناءات الجدول">
        <div class="flex justify-end">
          <Button variant="outline" @click="showAddException = true">إضافة استثناء</Button>
        </div>
        <DataTable :columns="exceptionColumns" :rows="exceptionRows" empty-text="لا توجد استثناءات.">
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Button variant="outline" size="sm" class="text-danger" @click="askDeleteException(row)">حذف</Button>
            </div>
          </template>
        </DataTable>
      </FormSection>
    </div>

    <!-- Add exception modal -->
    <Modal :open="showAddException" title="إضافة استثناء" @update:open="showAddException = $event">
      <form class="space-y-4" @submit.prevent="submitException">
        <FormGroup label="التاريخ" name="date" :error="exceptionForm.errors.date" required>
          <template #default="{ describedby }">
            <Input
              v-model="exceptionForm.date"
              type="date"
              dir="ltr"
              :aria-describedby="describedby"
            />
          </template>
        </FormGroup>

        <FormGroup label="النوع" name="type" :error="exceptionForm.errors.type" required>
          <template #default="{ describedby }">
            <select
              v-model="exceptionForm.type"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="closed">مغلق</option>
              <option value="custom_hours">ساعات مخصصة</option>
            </select>
          </template>
        </FormGroup>

        <template v-if="exceptionForm.type === 'custom_hours'">
          <FormGroup label="من" name="custom_start" :error="exceptionForm.errors.custom_start">
            <template #default="{ describedby }">
              <Input
                v-model="exceptionForm.custom_start"
                type="time"
                dir="ltr"
                :aria-describedby="describedby"
              />
            </template>
          </FormGroup>
          <FormGroup label="إلى" name="custom_end" :error="exceptionForm.errors.custom_end">
            <template #default="{ describedby }">
              <Input
                v-model="exceptionForm.custom_end"
                type="time"
                dir="ltr"
                :aria-describedby="describedby"
              />
            </template>
          </FormGroup>
        </template>

        <FormGroup label="ملاحظة" name="note" :error="exceptionForm.errors.note">
          <template #default="{ describedby }">
            <Input
              v-model="exceptionForm.note"
              :aria-describedby="describedby"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showAddException = false">إلغاء</Button>
        <Button :disabled="exceptionForm.processing" @click="submitException">إضافة</Button>
      </template>
    </Modal>

    <!-- Confirm delete exception -->
    <ConfirmModal
      :open="confirmDeleteException"
      title="حذف الاستثناء"
      :message="`هل أنت متأكد من حذف استثناء ${deleteExceptionTarget?.date_display ?? ''}؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDeleteException = $event"
      @confirm="doDeleteException"
    >
      <template v-if="deleteExceptionError" #description>
        <p class="text-sm text-danger">{{ deleteExceptionError }}</p>
      </template>
    </ConfirmModal>
  </AdminShell>
</template>
