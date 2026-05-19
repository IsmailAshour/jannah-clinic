<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
  // Set of selectable 'YYYY-MM-DD' strings
  availableDays: { type: Array, default: () => [] },
  // Currently selected 'YYYY-MM-DD' or ''
  modelValue: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'select', 'monthChange'])

const AR_MONTHS = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
]
// Weekday headers ordered Sunday..Saturday (JS getDay()), Arabic short labels
const AR_WEEKDAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']

function pad(n) {
  return String(n).padStart(2, '0')
}

function ymd(y, m, d) {
  return `${y}-${pad(m + 1)}-${pad(d)}`
}

function todayYmd() {
  const t = new Date()
  return ymd(t.getFullYear(), t.getMonth(), t.getDate())
}

const today = todayYmd()

// Visible month anchor — initialise to the selected date's month or current month
const initial = props.modelValue ? new Date(`${props.modelValue}T00:00:00`) : new Date()
const viewYear = ref(initial.getFullYear())
const viewMonth = ref(initial.getMonth()) // 0-based

const availableSet = computed(() => new Set(props.availableDays))

const monthLabel = computed(() => `${AR_MONTHS[viewMonth.value]} ${viewYear.value}`)

// Build the 6x7 grid (leading/trailing blanks as null)
const weeks = computed(() => {
  const firstDow = new Date(viewYear.value, viewMonth.value, 1).getDay() // 0=Sun
  const daysInMonth = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
  const cells = []
  for (let i = 0; i < firstDow; i++) cells.push(null)
  for (let d = 1; d <= daysInMonth; d++) {
    const value = ymd(viewYear.value, viewMonth.value, d)
    cells.push({
      day: d,
      value,
      isPast: value < today,
      isAvailable: availableSet.value.has(value),
      isSelected: value === props.modelValue,
    })
  }
  while (cells.length % 7 !== 0) cells.push(null)
  const out = []
  for (let i = 0; i < cells.length; i += 7) out.push(cells.slice(i, i + 7))
  return out
})

function monthRange() {
  const from = ymd(viewYear.value, viewMonth.value, 1)
  const last = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
  const to = ymd(viewYear.value, viewMonth.value, last)
  return { from, to }
}

function emitMonthChange() {
  emit('monthChange', monthRange())
}

function prevMonth() {
  if (viewMonth.value === 0) {
    viewMonth.value = 11
    viewYear.value--
  } else {
    viewMonth.value--
  }
}

function nextMonth() {
  if (viewMonth.value === 11) {
    viewMonth.value = 0
    viewYear.value++
  } else {
    viewMonth.value++
  }
}

onMounted(emitMonthChange)
watch([viewYear, viewMonth], emitMonthChange)

function pick(cell) {
  if (!cell || cell.isPast || !cell.isAvailable) return
  emit('update:modelValue', cell.value)
  emit('select', cell.value)
}

defineExpose({ viewYear, viewMonth, prevMonth, nextMonth, monthRange })
</script>

<template>
  <div
    dir="rtl"
    class="rounded-lg border border-border-default bg-surface-card p-4 select-none"
    data-testid="month-calendar"
  >
    <div class="flex items-center justify-between mb-3">
      <button
        type="button"
        class="rounded-md border border-border-default px-3 py-1 text-sm text-text-secondary hover:border-brand hover:text-brand"
        aria-label="الشهر السابق"
        data-testid="cal-prev"
        @click="prevMonth"
      >
        ‹
      </button>
      <span class="text-sm font-semibold text-text-primary">{{ monthLabel }}</span>
      <button
        type="button"
        class="rounded-md border border-border-default px-3 py-1 text-sm text-text-secondary hover:border-brand hover:text-brand"
        aria-label="الشهر التالي"
        data-testid="cal-next"
        @click="nextMonth"
      >
        ›
      </button>
    </div>

    <div class="grid grid-cols-7 gap-1 mb-1">
      <div
        v-for="w in AR_WEEKDAYS"
        :key="w"
        class="text-center text-xs font-medium text-text-tertiary py-1"
      >
        {{ w }}
      </div>
    </div>

    <div class="grid grid-cols-7 gap-1">
      <template v-for="(week, wi) in weeks" :key="wi">
        <template v-for="(cell, ci) in week" :key="ci">
          <div v-if="!cell" />
          <button
            v-else
            type="button"
            :disabled="cell.isPast || !cell.isAvailable"
            :data-date="cell.value"
            :data-available="cell.isAvailable ? 'true' : 'false'"
            :class="[
              'h-9 rounded-md text-sm transition-colors',
              cell.isSelected
                ? 'bg-brand text-white'
                : (cell.isPast || !cell.isAvailable)
                  ? 'cursor-not-allowed text-text-tertiary opacity-40'
                  : 'border border-border-default bg-surface-card text-text-primary hover:border-brand hover:text-brand',
            ]"
            @click="pick(cell)"
          >
            {{ cell.day }}
          </button>
        </template>
      </template>
    </div>
  </div>
</template>
