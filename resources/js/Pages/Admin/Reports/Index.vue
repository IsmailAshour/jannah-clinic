<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { ArrowLeft, BarChart3, CalendarRange, Home, MapPin, Stethoscope, TrendingDown, TrendingUp, Users, Video } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, StatCard } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  range: { type: String, default: 'month' },
  rangeLabel: { type: String, default: 'هذا الشهر' },
  from: { type: String, default: '' },
  to: { type: String, default: '' },
  stats: {
    type: Object,
    default: () => ({ total_revenue: '0', total_appointments: 0, new_customers: 0, no_show_rate: 0, completed: 0, no_show: 0 }),
  },
  monthlyRevenue: { type: Array, default: () => [] },
  statusCounts: { type: Object, default: () => ({}) },
  deliveryBreakdown: { type: Object, default: () => ({ home: 0, center: 0, online: 0 }) },
  topServices: { type: Array, default: () => [] },
  topDoctors: { type: Array, default: () => [] },
})

// Range pills + custom date inputs
const presets = [
  { id: 'month',      label: 'هذا الشهر' },
  { id: 'last_month', label: 'الشهر الماضي' },
  { id: '3m',         label: 'آخر 90 يوم' },
  { id: '6m',         label: 'آخر 180 يوم' },
  { id: 'year',       label: 'هذه السنة' },
]

const customFrom = ref(props.from)
const customTo = ref(props.to)
const isCustom = computed(() => props.range === 'custom')

function pickPreset(id) {
  router.get('/admin/reports', { range: id }, { preserveScroll: true, preserveState: false })
}

function applyCustom() {
  if (!customFrom.value || !customTo.value) return
  router.get('/admin/reports', { range: 'custom', from: customFrom.value, to: customTo.value }, { preserveScroll: true, preserveState: false })
}

watch(() => props.range, () => {
  customFrom.value = props.from
  customTo.value = props.to
})

// Bar chart scale — max bar width = 100%; min width 4% so a non-zero amount still shows a sliver
const maxMonthly = computed(() => Math.max(1, ...props.monthlyRevenue.map(m => Number(m.amount) || 0)))
const monthlyBars = computed(() => props.monthlyRevenue.map(m => {
  const v = Number(m.amount) || 0
  const pct = v <= 0 ? 0 : Math.max(4, Math.round((v / maxMonthly.value) * 100))
  return { ...m, value: v, pct }
}))

const maxServiceCount = computed(() => Math.max(1, ...props.topServices.map(s => s.count)))
const maxDoctorCount = computed(() => Math.max(1, ...props.topDoctors.map(d => d.count)))

// Status labels
const STATUS = {
  requested:   { label: 'بانتظار التأكيد', color: 'bg-warning' },
  confirmed:   { label: 'مؤكَّد',           color: 'bg-success' },
  completed:   { label: 'مكتمل',           color: 'bg-info' },
  cancelled:   { label: 'ملغى',            color: 'bg-danger/70' },
  rejected:    { label: 'مرفوض',           color: 'bg-danger' },
  no_show:     { label: 'لم يحضر',         color: 'bg-warning/70' },
  rescheduled: { label: 'أُعيد جدولته',    color: 'bg-info/70' },
}
const statusRows = computed(() => Object.entries(props.statusCounts).map(([k, count]) => ({
  key: k,
  count,
  label: STATUS[k]?.label ?? k,
  color: STATUS[k]?.color ?? 'bg-text-tertiary',
})))
const totalAppts = computed(() => statusRows.value.reduce((s, r) => s + r.count, 0))

const deliveryTotal = computed(() => {
  return (props.deliveryBreakdown.home ?? 0) + (props.deliveryBreakdown.center ?? 0) + (props.deliveryBreakdown.online ?? 0)
})

function pctOf(value) {
  const total = deliveryTotal.value
  if (total === 0) return 0
  return Math.round(((value ?? 0) / total) * 100)
}

function formatMoney(v) {
  const n = Number(v) || 0
  return n.toLocaleString('ar', { maximumFractionDigits: 2 })
}
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="التقارير والتحليلات" :description="`الفترة: ${rangeLabel}`" />

      <!-- Range pills + custom -->
      <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-2">
          <CalendarRange class="w-4 h-4 text-brand" aria-hidden="true" />
          <button
            v-for="p in presets"
            :key="p.id"
            type="button"
            :class="[
              'px-3 py-1.5 rounded-full text-sm font-bold transition',
              range === p.id
                ? 'bg-brand text-white shadow-sm'
                : 'bg-surface-page text-text-secondary hover:text-text-primary hover:bg-brand/5',
            ]"
            @click="pickPreset(p.id)"
          >{{ p.label }}</button>
          <button
            type="button"
            :class="[
              'px-3 py-1.5 rounded-full text-sm font-bold transition',
              isCustom
                ? 'bg-brand text-white shadow-sm'
                : 'bg-surface-page text-text-secondary hover:text-text-primary hover:bg-brand/5',
            ]"
            @click="pickPreset('custom')"
          >مخصّصة</button>
        </div>

        <div v-if="isCustom" class="flex flex-wrap items-end gap-2 pt-2 border-t border-border-default">
          <div class="space-y-1">
            <label class="text-xs font-semibold text-text-secondary block">من</label>
            <Input v-model="customFrom" type="date" dir="ltr" class="h-9" />
          </div>
          <div class="space-y-1">
            <label class="text-xs font-semibold text-text-secondary block">إلى</label>
            <Input v-model="customTo" type="date" dir="ltr" class="h-9" />
          </div>
          <Button size="sm" class="h-9" :disabled="!customFrom || !customTo" @click="applyCustom">تطبيق</Button>
        </div>
      </section>

      <!-- Top-line stats -->
      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          title="إجمالي الإيرادات"
          :value="`${formatMoney(stats.total_revenue)} ₪`"
          :trend="`دفعات مكتملة في ${rangeLabel}`"
          trend-direction="up"
        />
        <StatCard
          title="عدد المواعيد"
          :value="stats.total_appointments"
          :trend="`${stats.completed} مكتمل · ${stats.no_show} لم يحضر`"
          trend-direction="neutral"
        />
        <StatCard
          title="عملاء جدد"
          :value="stats.new_customers"
          :trend="stats.new_customers > 0 ? 'انضمّوا في هذه الفترة' : 'لا انضمامات بعد'"
          :trend-direction="stats.new_customers > 0 ? 'up' : 'neutral'"
        />
        <StatCard
          title="نسبة عدم الحضور"
          :value="`${stats.no_show_rate}٪`"
          :trend="stats.no_show_rate > 15 ? 'مرتفعة — يُنصح بتذكير العملاء' : 'ضمن الحدّ المقبول'"
          :trend-direction="stats.no_show_rate > 15 ? 'down' : 'up'"
        />
      </section>

      <!-- Revenue trend + appointments status -->
      <section class="grid gap-4 lg:grid-cols-5">
        <!-- Monthly revenue bars (3/5) -->
        <div class="lg:col-span-3 bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-4">
          <header>
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <BarChart3 class="w-4 h-4 text-brand" aria-hidden="true" />
              الإيرادات الشهريّة
            </h2>
            <p class="text-xs text-text-secondary mt-0.5">إجمالي المدفوع لكل شهر داخل الفترة المحدّدة.</p>
          </header>

          <ul v-if="monthlyBars.length > 0" class="space-y-2.5">
            <li v-for="m in monthlyBars" :key="m.month" class="space-y-1">
              <div class="flex items-center justify-between text-xs">
                <span class="text-text-secondary font-medium">{{ m.label }}</span>
                <span class="text-text-primary font-bold">{{ formatMoney(m.amount) }} ₪</span>
              </div>
              <div class="h-2.5 bg-surface-page rounded-full overflow-hidden">
                <div
                  class="h-full rounded-full transition-all bg-gradient-to-l from-brand to-brand/70"
                  :style="{ width: m.pct + '%' }"
                  :aria-label="`${m.label}: ${formatMoney(m.amount)} شيكل`"
                />
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-text-tertiary text-center py-6">لا إيرادات في هذه الفترة.</p>
        </div>

        <!-- Status breakdown (2/5) -->
        <div class="lg:col-span-2 bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-4">
          <header>
            <h2 class="text-base font-bold text-text-primary">حالات المواعيد</h2>
            <p class="text-xs text-text-secondary mt-0.5">توزيع المواعيد حسب الحالة.</p>
          </header>

          <ul v-if="totalAppts > 0" class="space-y-2.5">
            <li v-for="row in statusRows" :key="row.key" class="space-y-1">
              <div class="flex items-center justify-between text-xs">
                <span class="text-text-secondary font-medium inline-flex items-center gap-1.5">
                  <span :class="['w-2 h-2 rounded-full', row.color]" aria-hidden="true" />
                  {{ row.label }}
                </span>
                <span class="text-text-primary font-bold">{{ row.count }}</span>
              </div>
              <div class="h-1.5 bg-surface-page rounded-full overflow-hidden">
                <div
                  :class="['h-full rounded-full transition-all', row.color]"
                  :style="{ width: (totalAppts > 0 ? Math.max(2, Math.round((row.count / totalAppts) * 100)) : 0) + '%' }"
                />
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-text-tertiary text-center py-6">لا مواعيد في هذه الفترة.</p>
        </div>
      </section>

      <!-- Delivery split + Top services + Top doctors -->
      <section class="grid gap-4 lg:grid-cols-3">
        <!-- Delivery split -->
        <div class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-3">
          <header>
            <h2 class="text-base font-bold text-text-primary">طريقة تقديم الخدمة</h2>
            <p class="text-xs text-text-secondary mt-0.5">في المركز، المنزليّة، أونلاين.</p>
          </header>
          <div class="grid grid-cols-3 gap-2 py-3">
            <div class="text-center">
              <div class="w-14 h-14 mx-auto rounded-full bg-brand/10 text-brand grid place-items-center mb-1">
                <MapPin class="w-7 h-7" aria-hidden="true" />
              </div>
              <p class="text-2xl font-extrabold text-text-primary">{{ deliveryBreakdown.center }}</p>
              <p class="text-xs text-text-secondary">في المركز ({{ pctOf(deliveryBreakdown.center) }}٪)</p>
            </div>
            <div class="text-center">
              <div class="w-14 h-14 mx-auto rounded-full bg-warning/10 text-warning grid place-items-center mb-1">
                <Home class="w-7 h-7" aria-hidden="true" />
              </div>
              <p class="text-2xl font-extrabold text-text-primary">{{ deliveryBreakdown.home }}</p>
              <p class="text-xs text-text-secondary">منزليّة ({{ pctOf(deliveryBreakdown.home) }}٪)</p>
            </div>
            <div class="text-center">
              <div class="w-14 h-14 mx-auto rounded-full bg-success/10 text-success grid place-items-center mb-1">
                <Video class="w-7 h-7" aria-hidden="true" />
              </div>
              <p class="text-2xl font-extrabold text-text-primary">{{ deliveryBreakdown.online ?? 0 }}</p>
              <p class="text-xs text-text-secondary">أونلاين ({{ pctOf(deliveryBreakdown.online) }}٪)</p>
            </div>
          </div>
        </div>

        <!-- Top services -->
        <div class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-3">
          <header>
            <h2 class="text-base font-bold text-text-primary">أعلى الخدمات حجزًا</h2>
            <p class="text-xs text-text-secondary mt-0.5">أفضل 5 خدمات في هذه الفترة.</p>
          </header>

          <ul v-if="topServices.length > 0" class="space-y-2">
            <li v-for="(s, i) in topServices" :key="s.id" class="space-y-1">
              <div class="flex items-center justify-between text-xs">
                <span class="text-text-primary font-bold truncate">{{ i + 1 }}. {{ s.name }}</span>
                <span class="text-text-tertiary shrink-0">{{ s.count }} حجز · {{ formatMoney(s.revenue) }} ₪</span>
              </div>
              <div class="h-1.5 bg-surface-page rounded-full overflow-hidden">
                <div class="h-full rounded-full bg-brand" :style="{ width: Math.max(4, Math.round((s.count / maxServiceCount) * 100)) + '%' }" />
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-text-tertiary text-center py-6">لا بيانات.</p>
        </div>

        <!-- Top team members -->
        <div class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-3">
          <header>
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <Stethoscope class="w-4 h-4 text-brand" aria-hidden="true" />
              أعلى الأطبّاء حجزًا
            </h2>
            <p class="text-xs text-text-secondary mt-0.5">أفضل 5 أعضاء في الفريق.</p>
          </header>

          <ul v-if="topDoctors.length > 0" class="space-y-2">
            <li v-for="(d, i) in topDoctors" :key="d.id" class="space-y-1">
              <div class="flex items-center justify-between text-xs">
                <span class="text-text-primary font-bold truncate">{{ i + 1 }}. {{ d.name }}</span>
                <span class="text-text-tertiary shrink-0">{{ d.count }} موعد</span>
              </div>
              <div class="h-1.5 bg-surface-page rounded-full overflow-hidden">
                <div class="h-full rounded-full bg-info" :style="{ width: Math.max(4, Math.round((d.count / maxDoctorCount) * 100)) + '%' }" />
              </div>
            </li>
          </ul>
          <p v-else class="text-sm text-text-tertiary text-center py-6">لا بيانات.</p>
        </div>
      </section>
    </div>
  </AdminShell>
</template>
