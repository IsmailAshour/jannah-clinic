<script setup>
import { computed, ref, watch } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { CalendarDays, Clock, Home, MapPin, RotateCw, Stethoscope, User as UserIcon, Video } from 'lucide-vue-next'
import { PageHeader, StatCard, MonthCalendar, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import AdminShell from '@/Layouts/AdminShell.vue'

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')

// ---------- Calendar state ----------
function pad(n) { return String(n).padStart(2, '0') }
function ymd(y, m, d) { return `${y}-${pad(m + 1)}-${pad(d)}` }
function todayYmd() {
  const t = new Date()
  return ymd(t.getFullYear(), t.getMonth(), t.getDate())
}

const selectedDate = ref(todayYmd())
const appointments = ref([])       // current month's appointments
const loading = ref(false)
const error = ref(false)
const monthRange = ref(null)       // { from, to } most recent fetch

const statusMap = {
  requested: { label: 'بانتظار التأكيد', variant: 'warning' },
  confirmed: { label: 'مؤكد', variant: 'success' },
  completed: { label: 'مكتمل', variant: 'info' },
}

// Days in this month that have at least one appointment — passed to MonthCalendar
// so dates without bookings are visually de-emphasised.
const daysWithAppointments = computed(() => {
  const set = new Set()
  for (const a of appointments.value) set.add(a.date)
  return [...set]
})

const appointmentsForSelectedDay = computed(
  () => appointments.value.filter((a) => a.date === selectedDate.value)
)

// ---------- Stats derived from the calendar feed (no extra round-trip) ----------
const stats = computed(() => {
  const today = todayYmd()
  const todayCount = appointments.value.filter((a) => a.date === today).length
  const monthCount = appointments.value.length
  const customers = new Set(appointments.value.map((a) => a.customer_name))
  const homeVisits = appointments.value.filter((a) => a.delivery_mode === 'home').length
  return {
    today: todayCount,
    month: monthCount,
    customers: customers.size,
    home: homeVisits,
  }
})

async function fetchMonth(range) {
  if (!range || !range.from || !range.to) return
  monthRange.value = range
  loading.value = true
  error.value = false
  try {
    const url = `/admin/dashboard/calendar?from=${range.from}&to=${range.to}`
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    appointments.value = Array.isArray(data) ? data : []
  } catch {
    error.value = true
    appointments.value = []
  } finally {
    loading.value = false
  }
}

function onMonthChange(range) { fetchMonth(range) }
function refresh() { fetchMonth(monthRange.value) }

function formatTime(iso) {
  if (!iso) return ''
  try { return new Date(iso).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }) }
  catch (_) { return iso }
}
function formatSelectedDateAr() {
  if (!selectedDate.value) return ''
  try {
    return new Date(selectedDate.value).toLocaleDateString('ar-SA', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  } catch (_) { return selectedDate.value }
}
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="لوحة التحكم" :eyebrow="clinicName" description="نظرة على المواعيد والنشاط هذا الشهر." />

      <!-- Stats derived from the loaded month -->
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard title="مواعيد اليوم" :value="stats.today" :trend="loading ? 'يتم التحميل…' : ''" trend-direction="neutral" />
        <StatCard title="مواعيد هذا الشهر" :value="stats.month" trend-direction="neutral" />
        <StatCard title="عملاء نشطون (الشهر)" :value="stats.customers" trend-direction="neutral" />
        <StatCard title="زيارات منزليّة (الشهر)" :value="stats.home" trend-direction="neutral" />
      </div>

      <!-- Calendar + day list — side by side on lg, stacked on smaller -->
      <div class="grid gap-4 lg:grid-cols-5">
        <!-- Calendar (3/5) -->
        <section class="lg:col-span-3 bg-surface-card rounded-2xl shadow-sm border border-border-default p-4 sm:p-5">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <CalendarDays class="w-4 h-4 text-brand" aria-hidden="true" />
              تقويم المواعيد
            </h2>
            <Button variant="outline" size="sm" class="gap-1.5" :disabled="loading" @click="refresh">
              <RotateCw :class="['w-3.5 h-3.5', loading ? 'animate-spin' : '']" aria-hidden="true" />
              <span>تحديث</span>
            </Button>
          </div>
          <MonthCalendar
            v-model="selectedDate"
            :available-days="daysWithAppointments"
            @month-change="onMonthChange"
          />
          <p v-if="error" class="mt-3 text-xs text-danger">تعذّر تحميل المواعيد لهذا الشهر — اضغط "تحديث".</p>
          <p v-else-if="!loading && appointments.length === 0" class="mt-3 text-xs text-text-tertiary">لا مواعيد في هذا الشهر.</p>
        </section>

        <!-- Selected day list (2/5) -->
        <section class="lg:col-span-2 bg-surface-card rounded-2xl shadow-sm border border-border-default p-4 sm:p-5">
          <header class="mb-3 pb-3 border-b border-border-default">
            <p class="text-xs font-bold text-brand">{{ appointmentsForSelectedDay.length }} موعد</p>
            <h2 class="text-base font-bold text-text-primary">{{ formatSelectedDateAr() }}</h2>
          </header>

          <div v-if="appointmentsForSelectedDay.length === 0" class="text-sm text-text-secondary text-center py-8">
            لا مواعيد في هذا اليوم.
          </div>

          <ul v-else class="space-y-3 max-h-[28rem] overflow-y-auto pe-1">
            <li
              v-for="a in appointmentsForSelectedDay"
              :key="a.id"
              class="rounded-xl border border-border-default p-3 space-y-1.5 hover:border-brand/40 transition"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="text-sm font-extrabold text-text-primary inline-flex items-center gap-1.5">
                  <Clock class="w-3.5 h-3.5 text-brand" aria-hidden="true" />
                  <span dir="ltr">{{ formatTime(a.start_at) }}</span>
                </p>
                <StatusBadge
                  :type="statusMap[a.status]?.variant ?? 'info'"
                  :label="statusMap[a.status]?.label ?? a.status"
                />
              </div>
              <p class="text-sm text-text-primary font-bold truncate">{{ a.service_name }}</p>
              <div class="text-xs text-text-secondary space-y-0.5">
                <p class="inline-flex items-center gap-1.5">
                  <UserIcon class="w-3 h-3" aria-hidden="true" />
                  {{ a.customer_name }}
                </p>
                <p class="inline-flex items-center gap-1.5">
                  <Stethoscope class="w-3 h-3" aria-hidden="true" />
                  {{ a.doctor_name }}
                </p>
                <p class="inline-flex items-center gap-1.5">
                  <component
                    :is="a.delivery_mode === 'home' ? Home : (a.delivery_mode === 'online' ? Video : MapPin)"
                    class="w-3 h-3"
                    aria-hidden="true"
                  />
                  {{ a.delivery_mode === 'home' ? 'منزليّة' : (a.delivery_mode === 'online' ? 'أونلاين' : 'في المركز') }}
                </p>
              </div>
            </li>
          </ul>

          <div class="mt-3 pt-3 border-t border-border-default">
            <Link href="/admin/appointments" class="text-xs font-bold text-brand hover:underline">
              عرض كل المواعيد ←
            </Link>
          </div>
        </section>
      </div>
    </div>
  </AdminShell>
</template>
