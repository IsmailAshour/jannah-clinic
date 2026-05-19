<script setup>
import { ref, reactive, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  FormSection,
  FormGroup,
  ConfirmModal,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  doctor: { type: Object, required: true },
  grid: { type: Object, default: () => ({ morning: [], evening: [] }) },
  slots: { type: Object, default: () => ({}) },
  exceptions: { type: Array, default: () => [] },
})

// 0=الأحد .. 6=السبت — index MUST equal the Carbon dayOfWeek integer key.
const WEEKDAY_LABELS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']

// ── Weekly grid state: { 0: Set, 1: Set, ... 6: Set } ──────────────────────
const selected = reactive({})
for (let wd = 0; wd <= 6; wd++) {
  selected[wd] = new Set(props.slots?.[wd] ?? [])
}

function isOn(wd, time) {
  return selected[wd].has(time)
}

function toggle(wd, time) {
  const set = selected[wd]
  if (set.has(time)) {
    set.delete(time)
  } else {
    set.add(time)
  }
  // reassign so Vue reactivity tracks Set mutation
  selected[wd] = new Set(set)
}

const allTimes = computed(() => [...props.grid.morning, ...props.grid.evening])

function selectAll(wd) {
  selected[wd] = new Set(allTimes.value)
}

function clearDay(wd) {
  selected[wd] = new Set()
}

const scheduleForm = useForm({ slots: {} })

function saveSchedule() {
  const payload = {}
  for (let wd = 0; wd <= 6; wd++) {
    payload[wd] = [...selected[wd]]
  }
  scheduleForm
    .transform(() => ({ slots: payload }))
    .put(route('admin.doctors.schedule.save', props.doctor.id), {
      preserveScroll: true,
    })
}

// ── Exceptions ─────────────────────────────────────────────────────────────
function typeLabel(type) {
  return type === 'closed' ? 'مغلق' : 'مخصّص'
}

const exceptionForm = useForm({
  date: '',
  type: 'closed',
  slots: [],
  note: '',
})

const exceptionSelected = reactive(new Set())

function exceptionIsOn(time) {
  return exceptionSelected.has(time)
}

function toggleException(time) {
  if (exceptionSelected.has(time)) {
    exceptionSelected.delete(time)
  } else {
    exceptionSelected.add(time)
  }
}

function clearExceptionGrid() {
  exceptionSelected.clear()
}

function submitException() {
  exceptionForm
    .transform((data) => ({
      date: data.date,
      type: data.type,
      slots: data.type === 'custom' ? [...exceptionSelected] : [],
      note: data.note,
    }))
    .post(route('admin.doctors.exceptions.add', props.doctor.id), {
      preserveScroll: true,
      onSuccess: () => {
        exceptionForm.reset()
        exceptionForm.type = 'closed'
        clearExceptionGrid()
      },
    })
}

// Delete exception
const confirmDelete = ref(false)
const deleteTarget = ref(null)
const deleteError = ref('')

function askDelete(ex) {
  deleteTarget.value = ex
  deleteError.value = ''
  confirmDelete.value = true
}

function doDelete() {
  deleteError.value = ''
  useForm({}).delete(
    route('admin.doctors.exceptions.delete', [props.doctor.id, deleteTarget.value.id]),
    {
      preserveScroll: true,
      onSuccess: () => {
        confirmDelete.value = false
        deleteTarget.value = null
      },
      onError: (errors) => {
        deleteError.value = errors.delete ?? 'تعذّر حذف الاستثناء.'
        confirmDelete.value = true
      },
    },
  )
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-8">
      <PageHeader :title="`جدول الطبيب — ${doctor.name}`" />

      <!-- Weekly slot grid -->
      <FormSection title="الجدول الأسبوعي">
        <p v-if="scheduleForm.errors.schedule" class="text-sm text-danger">
          {{ scheduleForm.errors.schedule }}
        </p>

        <div class="space-y-4">
          <div
            v-for="wd in 7"
            :key="wd - 1"
            class="rounded-md border border-border-default p-4 space-y-3"
          >
            <div class="flex items-center justify-between gap-3">
              <h3 class="font-semibold text-text-primary">{{ WEEKDAY_LABELS[wd - 1] }}</h3>
              <div class="flex gap-2">
                <Button type="button" variant="ghost" size="sm" @click="selectAll(wd - 1)">
                  تحديد الكل
                </Button>
                <Button type="button" variant="ghost" size="sm" @click="clearDay(wd - 1)">
                  مسح
                </Button>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-xs font-medium text-text-tertiary">صباحية</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="time in grid.morning"
                  :key="`m-${wd - 1}-${time}`"
                  type="button"
                  :aria-pressed="isOn(wd - 1, time)"
                  :class="[
                    'rounded-md border px-3 py-1.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-brand',
                    isOn(wd - 1, time)
                      ? 'border-brand bg-brand text-white'
                      : 'border-border-default bg-surface-card text-text-secondary hover:border-brand',
                  ]"
                  @click="toggle(wd - 1, time)"
                >
                  {{ time }}
                </button>
              </div>
            </div>

            <div class="space-y-2">
              <p class="text-xs font-medium text-text-tertiary">مسائية</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="time in grid.evening"
                  :key="`e-${wd - 1}-${time}`"
                  type="button"
                  :aria-pressed="isOn(wd - 1, time)"
                  :class="[
                    'rounded-md border px-3 py-1.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-brand',
                    isOn(wd - 1, time)
                      ? 'border-brand bg-brand text-white'
                      : 'border-border-default bg-surface-card text-text-secondary hover:border-brand',
                  ]"
                  @click="toggle(wd - 1, time)"
                >
                  {{ time }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <Button type="button" :disabled="scheduleForm.processing" @click="saveSchedule">
            حفظ الجدول
          </Button>
        </div>
      </FormSection>

      <!-- Exceptions -->
      <FormSection title="استثناءات الجدول">
        <ul v-if="exceptions.length" class="divide-y divide-border-default rounded-md border border-border-default">
          <li
            v-for="ex in exceptions"
            :key="ex.id"
            class="flex items-center justify-between gap-3 p-3"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-text-primary" dir="ltr">{{ ex.date }}</p>
              <p class="text-xs text-text-secondary">
                {{ typeLabel(ex.type) }}
                <span v-if="ex.type === 'custom'"> — {{ ex.slots.length }} فترة</span>
                <span v-if="ex.note"> — {{ ex.note }}</span>
              </p>
            </div>
            <Button type="button" variant="outline" size="sm" class="text-danger" @click="askDelete(ex)">
              حذف
            </Button>
          </li>
        </ul>
        <p v-else class="text-sm text-text-secondary">لا توجد استثناءات.</p>

        <!-- Add exception -->
        <div class="rounded-md border border-border-default p-4 space-y-4">
          <h3 class="font-semibold text-text-primary">إضافة استثناء</h3>

          <FormGroup label="التاريخ" name="date" :error="exceptionForm.errors.date" required>
            <template #default="{ describedby }">
              <Input
                v-model="exceptionForm.date"
                type="date"
                dir="ltr"
                :aria-describedby="describedby"
                class="w-48"
              />
            </template>
          </FormGroup>

          <FormGroup label="النوع" name="type" :error="exceptionForm.errors.type" required>
            <template #default>
              <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="exceptionForm.type" type="radio" value="closed" class="h-4 w-4" />
                  <span class="text-sm">مغلق</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                  <input v-model="exceptionForm.type" type="radio" value="custom" class="h-4 w-4" />
                  <span class="text-sm">مخصّص</span>
                </label>
              </div>
            </template>
          </FormGroup>

          <div v-if="exceptionForm.type === 'custom'" class="space-y-3">
            <p v-if="exceptionForm.errors.slots" class="text-sm text-danger">
              {{ exceptionForm.errors.slots }}
            </p>
            <div class="space-y-2">
              <p class="text-xs font-medium text-text-tertiary">صباحية</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="time in grid.morning"
                  :key="`xm-${time}`"
                  type="button"
                  :aria-pressed="exceptionIsOn(time)"
                  :class="[
                    'rounded-md border px-3 py-1.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-brand',
                    exceptionIsOn(time)
                      ? 'border-brand bg-brand text-white'
                      : 'border-border-default bg-surface-card text-text-secondary hover:border-brand',
                  ]"
                  @click="toggleException(time)"
                >
                  {{ time }}
                </button>
              </div>
            </div>
            <div class="space-y-2">
              <p class="text-xs font-medium text-text-tertiary">مسائية</p>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="time in grid.evening"
                  :key="`xe-${time}`"
                  type="button"
                  :aria-pressed="exceptionIsOn(time)"
                  :class="[
                    'rounded-md border px-3 py-1.5 text-sm transition focus:outline-none focus:ring-2 focus:ring-brand',
                    exceptionIsOn(time)
                      ? 'border-brand bg-brand text-white'
                      : 'border-border-default bg-surface-card text-text-secondary hover:border-brand',
                  ]"
                  @click="toggleException(time)"
                >
                  {{ time }}
                </button>
              </div>
            </div>
          </div>

          <FormGroup label="ملاحظة" name="note" :error="exceptionForm.errors.note">
            <template #default="{ describedby }">
              <Input v-model="exceptionForm.note" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <div class="flex justify-end">
            <Button type="button" :disabled="exceptionForm.processing" @click="submitException">
              إضافة استثناء
            </Button>
          </div>
        </div>
      </FormSection>
    </div>

    <ConfirmModal
      :open="confirmDelete"
      title="حذف الاستثناء"
      :message="`هل أنت متأكد من حذف استثناء ${deleteTarget?.date ?? ''}؟`"
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
