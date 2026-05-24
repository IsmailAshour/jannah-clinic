<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ArrowLeft, ArrowRight, CalendarDays, Check, Clock, Home, MapPin, MessageCircle, Search, Stethoscope, User as UserIcon, Video, X } from 'lucide-vue-next'
import { FormGroup, PageStates, MonthCalendar, PaymentMethodPicker } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import LocationPickerMap from '@/Components/booking/LocationPickerMap.vue'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  coverageAreas: { type: Array, default: () => [] },
  availabilityUrl: { type: String, required: true },
  availabilityDaysUrl: { type: String, required: true },
  homeSurchargePct: { type: [String, Number], default: 0 },
  customerPicker: { type: Boolean, default: false },
  customerSearchUrl: { type: String, default: '/admin/customers/search' },
  // Optional preset list (legacy); the picker now searches via AJAX. Kept
  // for backwards-compat with any test or call-site that still passes it.
  customers: { type: Array, default: () => [] },
  loyaltyBalance: { type: Number, default: 0 },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submit'])

// Step management
const step = ref(props.customerPicker ? 0 : 1)

// Customer picker state (admin only — step 0)
const customerMode = ref('existing') // 'existing' | 'new'
const selectedCustomerId = ref(null)
const selectedCustomer = ref(null)   // full object — kept so summary + UI can show name
const newCustomerName = ref('')
const newCustomerEmail = ref('')
const newCustomerPhone = ref('')

// AJAX customer search (replaces the bulk <select>). Debounced so we don't
// flood the endpoint while the admin types.
const customerQuery = ref('')
const customerResults = ref([])
const customerSearchLoading = ref(false)
const customerSearchError = ref(false)
let customerSearchTimer = null
let customerSearchSeq = 0

async function runCustomerSearch() {
  const mySeq = ++customerSearchSeq
  customerSearchLoading.value = true
  customerSearchError.value = false
  try {
    const url = `${props.customerSearchUrl}?q=${encodeURIComponent(customerQuery.value)}`
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    // Discard out-of-order responses — last request always wins.
    if (mySeq !== customerSearchSeq) return
    customerResults.value = Array.isArray(data) ? data : []
  } catch {
    if (mySeq !== customerSearchSeq) return
    customerSearchError.value = true
    customerResults.value = []
  } finally {
    if (mySeq === customerSearchSeq) customerSearchLoading.value = false
  }
}

watch(customerQuery, () => {
  if (customerSearchTimer) clearTimeout(customerSearchTimer)
  customerSearchTimer = setTimeout(runCustomerSearch, 250)
})

// Reset the picker when switching from 'existing' → 'new' (and vice versa).
watch(customerMode, (m) => {
  if (m !== 'existing') {
    selectedCustomerId.value = null
    selectedCustomer.value = null
  }
})

function pickCustomer(c) {
  selectedCustomerId.value = c.id
  selectedCustomer.value = c
  customerResults.value = []
  customerQuery.value = ''
}

function clearSelectedCustomer() {
  selectedCustomerId.value = null
  selectedCustomer.value = null
  customerQuery.value = ''
  // Re-open the empty search so the admin can pick a different one.
  runCustomerSearch()
}

// Step 1: delivery mode
const deliveryMode = ref('center')
const coverageAreaId = ref(null)
const addressText = ref('')
const locationNote = ref('')
const homeLocation = ref(null) // { lat, lng } | null — picked on the map
const whatsappPhone = ref('')  // for online mode — defaults from current customer's phone

// Pre-fill the WhatsApp field from whichever phone we know:
//  - admin mode: the picked customer (or the new-customer phone the admin is typing)
//  - portal mode: the logged-in user's own phone (shared via Inertia)
const initialPhoneFromAuth = (() => {
  try {
    const u = usePage().props?.auth?.user
    return u?.phone ?? ''
  } catch { return '' }
})()
if (initialPhoneFromAuth) whatsappPhone.value = initialPhoneFromAuth

watch(selectedCustomer, (c) => {
  if (deliveryMode.value === 'online' && c?.phone) {
    whatsappPhone.value = c.phone
  }
})
watch(newCustomerPhone, (p) => {
  if (deliveryMode.value === 'online' && customerMode.value === 'new' && p) {
    whatsappPhone.value = p
  }
})

// Step 2: doctor + services (multi-select)
const doctorId = ref(null)
// Multi-service: array of selected service IDs in user-chosen order
const selectedServiceIds = ref([])

// Legacy single-service alias — getter returns the first selected ID,
// setter wraps a single value into the array. Keeps existing test code
// using wrapper.vm.serviceId working.
const serviceId = computed({
  get: () => selectedServiceIds.value[0] ?? null,
  set: (v) => { selectedServiceIds.value = v === null || v === undefined ? [] : [v] },
})

const selectedDoctor = computed(() => props.doctors.find(d => d.id === doctorId.value) ?? null)

const filteredServices = computed(() => {
  if (!selectedDoctor.value) return []
  return selectedDoctor.value.services.filter(s => {
    if (deliveryMode.value === 'home') return s.home_service_enabled
    if (deliveryMode.value === 'online') return s.online_service_enabled
    return true
  })
})

const selectedServices = computed(() => {
  if (!selectedDoctor.value) return []
  return selectedServiceIds.value
    .map(id => selectedDoctor.value.services.find(s => s.id === id))
    .filter(Boolean)
})

// Back-compat for templates that referenced the single "selectedService" —
// resolves to the first one in the selection.
const selectedService = computed(() => selectedServices.value[0] ?? null)

function toggleService(id) {
  const idx = selectedServiceIds.value.indexOf(id)
  if (idx === -1) selectedServiceIds.value = [...selectedServiceIds.value, id]
  else selectedServiceIds.value = selectedServiceIds.value.filter(x => x !== id)
}

// Total duration across all picked services — used by the calendar/slot
// fetch on the URL side (server sums internally too, this is just for
// summary display).
const totalDurationMinutes = computed(() => {
  return selectedServices.value.reduce((acc, s) => acc + (Number(s.duration_minutes) || 0), 0)
})

// When the doctor or the delivery mode changes, drop any services that
// no longer belong to the new doctor / aren't eligible for the new mode.
watch(doctorId, () => { selectedServiceIds.value = [] })
watch(deliveryMode, () => {
  if (selectedServiceIds.value.length === 0) return
  const eligibleIds = new Set(filteredServices.value.map(s => s.id))
  selectedServiceIds.value = selectedServiceIds.value.filter(id => eligibleIds.has(id))
})

// servicesQuery is a URLSearchParams fragment "services[]=1&services[]=2"
// used by the availability + days endpoints when multiple services are
// picked. The backend also accepts the legacy "service=ID" (which we no
// longer emit) for transitional safety.
function servicesQuery() {
  return selectedServiceIds.value
    .map(id => `services%5B%5D=${id}`)
    .join('&')
}

// Step 3: date + slot
const paymentMethod = ref('cash')

// Admin-only staff discount (customerPicker === true). discount_type may
// be 'percent' or 'fixed'; discount_value is the raw figure (10 = "10%"
// or 50 = "50₪"). When the toggle is off, all three are nulled so the
// payload never carries stale discount data.
const discountEnabled = ref(false)
const discountType = ref('percent')
const discountValue = ref('')
const discountReason = ref('')
const selectedDate = ref('')
const slots = ref([])
const slotsLoading = ref(false)
const slotsEmpty = ref(false)
const slotsError = ref(false)
const selectedStart = ref(null)

// Available days (calendar gating)
const availableDays = ref([])
const daysLoading = ref(false)
const daysError = ref(false)
const calMonth = ref(null) // { from, to } of the visible month

async function fetchDays() {
  if (!doctorId.value || selectedServiceIds.value.length === 0 || !calMonth.value) return
  daysLoading.value = true
  daysError.value = false
  try {
    const { from, to } = calMonth.value
    const url = `${props.availabilityDaysUrl}?doctor=${doctorId.value}&${servicesQuery()}&from=${from}&to=${to}`
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    availableDays.value = Array.isArray(data) ? data : []
  } catch {
    daysError.value = true
    availableDays.value = []
  } finally {
    daysLoading.value = false
  }
}

function onCalendarMonthChange(range) {
  calMonth.value = range
  fetchDays()
}

function onCalendarSelect(date) {
  selectedDate.value = date
}

// Refresh days when entering step 3 or changing doctor/service
watch(step, (s) => {
  if (s === 3) fetchDays()
})
watch([doctorId, selectedServiceIds], () => {
  if (step.value === 3) fetchDays()
}, { deep: true })

async function fetchSlots() {
  if (!doctorId.value || selectedServiceIds.value.length === 0 || !selectedDate.value) return
  slotsLoading.value = true
  slotsEmpty.value = false
  slotsError.value = false
  slots.value = []
  selectedStart.value = null
  try {
    const url = `${props.availabilityUrl}?doctor=${doctorId.value}&${servicesQuery()}&date=${selectedDate.value}`
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    slots.value = data
    slotsEmpty.value = data.length === 0
  } catch {
    slotsError.value = true
    slots.value = []
    slotsEmpty.value = false
  } finally {
    slotsLoading.value = false
  }
}

watch(selectedDate, fetchSlots)

watch(selectedServiceIds, () => {
  selectedDate.value = ''
  slots.value = []
  slotsEmpty.value = false
  slotsError.value = false
  selectedStart.value = null
  availableDays.value = []
  daysError.value = false
}, { deep: true })

// Expose internals for testing
defineExpose({
  step, doctorId, serviceId, selectedServiceIds, deliveryMode, selectedDate,
  slots, slotsEmpty, slotsLoading, slotsError, selectedStart,
  availableDays, daysLoading, daysError, calMonth,
  fetchSlots, fetchDays,
  // Test helper: call fetchSlots with pre-set values. There's a chain of
  // dependent watchers (doctorId resets selectedServiceIds; selectedServiceIds
  // resets selectedDate; etc), so each ref is set then awaited before the
  // next is touched.
  async fetchSlotsForTest(dId, sId, date) {
    doctorId.value = dId
    await nextTick()
    serviceId.value = sId
    await nextTick()
    selectedDate.value = date
    await fetchSlots()
  },
  async fetchDaysForTest(dId, sId, range) {
    doctorId.value = dId
    await nextTick()
    serviceId.value = sId
    await nextTick()
    calMonth.value = range
    await fetchDays()
  },
})

// Discount amount preview — mirrors BookingService::book() math so the
// receptionist sees the same ₪ figure the backend will compute. Always
// clamped at the gross total — a 200₪ fixed discount on a 150₪ visit
// caps at 150 (free visit, never negative).
const discountAmountPreview = computed(() => {
  if (!discountEnabled.value || !previewPrice.value) return 0
  const v = parseFloat(discountValue.value)
  if (!Number.isFinite(v) || v <= 0) return 0
  const total = previewPrice.value.total
  const amount = discountType.value === 'percent'
    ? Math.round((total * v) / 100)
    : Math.round(v)
  return Math.min(amount, total)
})

// Price preview — sum of all selected services + home surcharge.
const previewPrice = computed(() => {
  if (selectedServices.value.length === 0) return null
  const base = selectedServices.value.reduce(
    (acc, s) => acc + Number(s.price_override ?? s.base_price),
    0,
  )
  if (deliveryMode.value === 'home') {
    const surcharge = Math.round(base * Number(props.homeSurchargePct) / 100)
    return { base, surcharge, total: base + surcharge }
  }
  return { base, surcharge: 0, total: base }
})

// Navigation

function canAdvanceStep0() {
  if (customerMode.value === 'existing') return !!selectedCustomerId.value
  return !!newCustomerName.value && (!!newCustomerEmail.value || !!newCustomerPhone.value)
}

function canAdvanceStep1() {
  if (deliveryMode.value === 'home') {
    return !!coverageAreaId.value && !!addressText.value
  }
  if (deliveryMode.value === 'online') {
    return !!whatsappPhone.value && whatsappPhone.value.replace(/\D+/g, '').length >= 8
  }
  return true
}

function canAdvanceStep2() {
  return !!doctorId.value && selectedServiceIds.value.length > 0
}

function canAdvanceStep3() {
  return !!selectedStart.value
}

function nextStep() {
  step.value++
}

function prevStep() {
  step.value--
}

// Clinic name comes from HandleInertiaRequests::share; used as subtitle under
// the 'في العيادة' delivery option so the customer sees exactly which branch.
const inertiaPage = usePage()
const clinicName = computed(() => inertiaPage.props?.clinic?.name ?? 'العيادة')
const clinicAddress = computed(() => {
  const addr = inertiaPage.props?.clinic?.address
  return addr && addr.trim() !== '' ? addr : clinicName.value
})

const TEAM_ROLE_LABEL = {
  doctor: 'طبيب',
  nurse: 'ممرّض',
  physiotherapist: 'أخصّائي علاج طبيعي',
}
function teamRoleLabel(d) { return TEAM_ROLE_LABEL[d.team_role] ?? 'طبيب' }

// --- Stepper presentation ---
const stepConfig = computed(() => {
  const arr = []
  if (props.customerPicker) {
    arr.push({ id: 0, label: 'العميل', icon: UserIcon, desc: 'اختر العميل أو سجّل جديدًا.' })
  }
  arr.push(
    { id: 1, label: 'طريقة الخدمة', icon: Home, desc: 'في العيادة أم زيارة منزلية؟' },
    { id: 2, label: 'الطبيب والخدمة', icon: Stethoscope, desc: 'اختر مقدّم الرعاية والخدمة.' },
    { id: 3, label: 'الموعد', icon: CalendarDays, desc: 'حدّد اليوم والوقت المناسب.' },
  )
  return arr
})

const selectedCustomerLabel = computed(() => {
  if (customerMode.value === 'new') return newCustomerName.value || 'عميل جديد'
  if (selectedCustomer.value) return selectedCustomer.value.name
  // Fallback (legacy customers prop) in case a caller still passes preset list.
  const c = props.customers.find(c => c.id === selectedCustomerId.value)
  return c ? c.name : null
})

const selectedCoverageArea = computed(() => props.coverageAreas.find(a => a.id === coverageAreaId.value) ?? null)

function deliveryLabel(m) {
  if (m === 'home') return 'زيارة منزلية'
  if (m === 'online') return 'موعد أونلاين'
  return 'في المركز'
}

function deliveryIcon(m) {
  if (m === 'home') return Home
  if (m === 'online') return Video
  return MapPin
}

function formatSelectedDate(d) {
  if (!d) return ''
  try {
    return new Date(d).toLocaleDateString('ar-SA', { weekday: 'long', day: 'numeric', month: 'long' })
  } catch (_) { return d }
}

function formatSelectedTime(iso) {
  if (!iso) return ''
  try {
    return new Date(iso).toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
  } catch (_) { return iso }
}

function handleSubmit() {
  const payload = {
    doctor: doctorId.value,
    services: [...selectedServiceIds.value],
    start: selectedStart.value,
    delivery_mode: deliveryMode.value,
    payment_method: paymentMethod.value,
  }

  if (deliveryMode.value === 'home') {
    payload.coverage_area_id = coverageAreaId.value
    payload.address_text = addressText.value
    payload.location_note = locationNote.value || null
    if (homeLocation.value) {
      payload.lat = homeLocation.value.lat
      payload.lng = homeLocation.value.lng
    }
  }

  if (deliveryMode.value === 'online') {
    payload.whatsapp_phone = whatsappPhone.value
  }

  // Discount is admin-only (customerPicker) + cash-only. When the toggle
  // is off or the value is invalid the payload omits the fields entirely
  // so the backend treats it as "no discount" and stores NULLs.
  if (props.customerPicker && discountEnabled.value && paymentMethod.value === 'cash') {
    const v = parseFloat(discountValue.value)
    if (Number.isFinite(v) && v > 0) {
      payload.discount_type = discountType.value
      payload.discount_value = v
      payload.discount_reason = discountReason.value?.trim() || null
    }
  }

  if (props.customerPicker) {
    if (customerMode.value === 'existing') {
      payload.customer_id = selectedCustomerId.value
    } else {
      payload.new_customer = {
        name: newCustomerName.value,
        email: newCustomerEmail.value || null,
        phone: newCustomerPhone.value || null,
      }
    }
  }

  emit('submit', payload)
}
</script>

<template>
  <div class="space-y-5">
    <!-- Stepper: numbered circles with connector lines, active + done states -->
    <ol class="flex items-start gap-1 sm:gap-2 overflow-x-auto px-1 pb-1" aria-label="خطوات الحجز">
      <li
        v-for="(s, i) in stepConfig"
        :key="s.id"
        class="flex-1 min-w-0 flex items-start"
        :aria-current="step === s.id ? 'step' : undefined"
      >
        <div class="flex flex-col items-center gap-1.5 flex-1 min-w-0">
          <div class="flex items-center w-full">
            <!-- Connector before (hidden for first) -->
            <span
              v-if="i > 0"
              :class="['flex-1 h-0.5 transition-colors', step > stepConfig[i - 1].id ? 'bg-brand' : 'bg-border-default']"
              aria-hidden="true"
            />
            <!-- Step circle -->
            <span
              :class="[
                'shrink-0 grid place-items-center w-9 h-9 rounded-full border-2 font-extrabold text-sm transition-all',
                step > s.id
                  ? 'bg-brand border-brand text-white shadow-sm'
                  : step === s.id
                    ? 'bg-brand/10 border-brand text-brand ring-4 ring-brand/15'
                    : 'bg-surface-card border-border-default text-text-tertiary',
              ]"
            >
              <Check v-if="step > s.id" class="w-4 h-4" aria-hidden="true" />
              <component v-else :is="s.icon" class="w-4 h-4" aria-hidden="true" />
            </span>
            <!-- Connector after (hidden for last) -->
            <span
              v-if="i < stepConfig.length - 1"
              :class="['flex-1 h-0.5 transition-colors', step > s.id ? 'bg-brand' : 'bg-border-default']"
              aria-hidden="true"
            />
          </div>
          <span :class="['text-[11px] sm:text-xs font-bold text-center truncate w-full', step >= s.id ? 'text-brand' : 'text-text-tertiary']">
            {{ s.label }}
          </span>
        </div>
      </li>
    </ol>

    <!-- Active step descriptor -->
    <div class="bg-brand/5 border-2 border-brand/15 rounded-2xl p-4">
      <p class="text-xs font-bold text-brand">الخطوة {{ stepConfig.findIndex(s => s.id === step) + 1 }} من {{ stepConfig.length }}</p>
      <p class="mt-0.5 text-sm text-text-primary">{{ stepConfig.find(s => s.id === step)?.desc }}</p>
    </div>

    <!-- Server booking error -->
    <div v-if="errors.booking" class="rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger" role="alert">
      {{ errors.booking }}
    </div>

    <!-- Step 0: Customer picker (admin only) -->
    <section v-if="customerPicker && step === 0" class="bg-surface-card rounded-2xl border border-border-default p-5 space-y-4 shadow-sm">
      <div class="flex gap-4 mb-4">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="customerMode" type="radio" value="existing" class="h-4 w-4 accent-brand" />
          <span class="text-sm">عميل موجود</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="customerMode" type="radio" value="new" class="h-4 w-4 accent-brand" />
          <span class="text-sm">عميل جديد</span>
        </label>
      </div>

      <div v-if="customerMode === 'existing'" class="space-y-3">
        <!-- Selected customer card — replaces the search input once a pick is made. -->
        <div
          v-if="selectedCustomer"
          class="flex items-center gap-3 rounded-2xl border-2 border-brand bg-brand/5 ring-2 ring-brand/15 p-3"
        >
          <div class="w-10 h-10 rounded-full bg-brand text-white grid place-items-center font-extrabold text-sm shrink-0">
            {{ Array.from(selectedCustomer.name ?? 'ع')[0] }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-text-primary truncate">{{ selectedCustomer.name }}</p>
            <p class="text-xs text-text-tertiary truncate" dir="ltr">
              {{ selectedCustomer.phone || selectedCustomer.email || '—' }}
            </p>
          </div>
          <button
            type="button"
            class="inline-flex items-center gap-1 text-xs font-bold text-danger hover:underline shrink-0"
            @click="clearSelectedCustomer"
          >
            <X class="w-3.5 h-3.5" aria-hidden="true" />
            تغيير
          </button>
        </div>

        <!-- AJAX search input + results — visible only while no customer is picked. -->
        <div v-else class="space-y-2">
          <label for="customer_search" class="block text-sm font-bold text-text-primary">ابحث عن العميل
            <span class="text-danger" aria-hidden="true">*</span>
          </label>
          <div class="relative">
            <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
              <Search class="w-4 h-4" aria-hidden="true" />
            </span>
            <Input
              id="customer_search"
              v-model="customerQuery"
              placeholder="ابحث بالاسم، البريد، أو رقم الجوّال…"
              class="h-11 ps-9"
              autocomplete="off"
              @focus="runCustomerSearch"
            />
          </div>

          <p v-if="customerSearchLoading" class="text-xs text-text-secondary px-1">جاري البحث…</p>
          <p v-else-if="customerSearchError" class="text-xs text-danger px-1">تعذّر البحث، حاول مرة أخرى.</p>
          <p
            v-else-if="customerQuery && customerResults.length === 0"
            class="text-xs text-text-secondary px-1"
          >لا نتائج مطابقة — جرّب رقمًا أو بريدًا آخر.</p>

          <ul v-if="customerResults.length > 0" class="rounded-xl border border-border-default divide-y divide-border-default bg-surface-card max-h-72 overflow-y-auto">
            <li
              v-for="c in customerResults"
              :key="c.id"
            >
              <button
                type="button"
                class="w-full text-start px-3 py-2.5 hover:bg-brand/5 transition flex items-center gap-3"
                @click="pickCustomer(c)"
              >
                <span class="w-8 h-8 rounded-full bg-brand/10 text-brand grid place-items-center font-bold text-sm shrink-0">
                  {{ Array.from(c.name ?? 'ع')[0] }}
                </span>
                <span class="flex-1 min-w-0">
                  <span class="block text-sm font-bold text-text-primary truncate">{{ c.name }}</span>
                  <span class="block text-xs text-text-tertiary truncate" dir="ltr">{{ c.phone || c.email || '—' }}</span>
                </span>
              </button>
            </li>
          </ul>

          <p v-if="!customerQuery && customerResults.length === 0 && !customerSearchLoading" class="text-xs text-text-tertiary px-1">
            ابدأ بكتابة جزء من اسم العميل أو رقم جواله.
          </p>
        </div>
      </div>

      <div v-else class="space-y-4">
        <FormGroup label="الاسم" name="new_customer_name" required>
          <template #default="{ describedby }">
            <Input
              id="new_customer_name"
              v-model="newCustomerName"
              name="new_customer_name"
              :aria-describedby="describedby"
              placeholder="اسم العميل"
            />
          </template>
        </FormGroup>
        <FormGroup label="البريد الإلكتروني" name="new_customer_email">
          <template #default="{ describedby }">
            <Input
              id="new_customer_email"
              v-model="newCustomerEmail"
              type="email"
              name="new_customer_email"
              :aria-describedby="describedby"
              placeholder="example@example.com"
            />
          </template>
        </FormGroup>
        <FormGroup label="رقم الهاتف" name="new_customer_phone">
          <template #default="{ describedby }">
            <Input
              id="new_customer_phone"
              v-model="newCustomerPhone"
              name="new_customer_phone"
              :aria-describedby="describedby"
              placeholder="05xxxxxxxx"
              dir="ltr"
            />
          </template>
        </FormGroup>
        <p class="text-xs text-text-tertiary">يجب توفير بريد إلكتروني أو رقم هاتف على الأقل.</p>
      </div>
    </section>

    <!-- Step 1: Delivery mode -->
    <section v-if="step === 1" class="bg-surface-card rounded-2xl border border-border-default p-5 space-y-4 shadow-sm">
      <div class="grid grid-cols-3 gap-3">
        <label
          :class="[
            'cursor-pointer rounded-2xl border-2 p-4 text-center transition',
            deliveryMode === 'center' ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-border-default hover:border-brand/40',
          ]"
        >
          <input v-model="deliveryMode" type="radio" value="center" class="sr-only" />
          <div class="mx-auto w-12 h-12 rounded-full bg-brand/10 text-brand grid place-items-center mb-2">
            <MapPin class="w-6 h-6" aria-hidden="true" />
          </div>
          <p class="text-sm font-bold text-text-primary">في المركز</p>
          <p class="text-xs text-text-tertiary mt-0.5 line-clamp-2 leading-relaxed">{{ clinicAddress }}</p>
        </label>
        <label
          data-testid="home-radio"
          :class="[
            'cursor-pointer rounded-2xl border-2 p-4 text-center transition',
            deliveryMode === 'home' ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-border-default hover:border-brand/40',
          ]"
        >
          <input v-model="deliveryMode" type="radio" value="home" class="sr-only" />
          <div class="mx-auto w-12 h-12 rounded-full bg-brand/10 text-brand grid place-items-center mb-2">
            <Home class="w-6 h-6" aria-hidden="true" />
          </div>
          <p class="text-sm font-bold text-text-primary">زيارة منزلية</p>
          <p class="text-xs text-text-tertiary mt-0.5">نأتي إليك</p>
        </label>
        <label
          data-testid="online-radio"
          :class="[
            'cursor-pointer rounded-2xl border-2 p-4 text-center transition',
            deliveryMode === 'online' ? 'border-brand bg-brand/5 ring-2 ring-brand/20' : 'border-border-default hover:border-brand/40',
          ]"
        >
          <input v-model="deliveryMode" type="radio" value="online" class="sr-only" />
          <div class="mx-auto w-12 h-12 rounded-full bg-brand/10 text-brand grid place-items-center mb-2">
            <Video class="w-6 h-6" aria-hidden="true" />
          </div>
          <p class="text-sm font-bold text-text-primary">أونلاين</p>
          <p class="text-xs text-text-tertiary mt-0.5">عبر واتساب</p>
        </label>
      </div>

      <div v-if="deliveryMode === 'online'" class="space-y-3 pt-2 border-t border-border-default" data-testid="online-fields">
        <FormGroup label="رقم واتساب للتواصل" name="whatsapp_phone" :error="errors?.whatsapp_phone" required>
          <template #default="{ describedby }">
            <Input
              id="whatsapp_phone"
              v-model="whatsappPhone"
              name="whatsapp_phone"
              :aria-describedby="describedby"
              placeholder="05xxxxxxxx"
              dir="ltr"
            />
          </template>
        </FormGroup>
        <p class="text-xs text-text-tertiary inline-flex items-center gap-1.5">
          <MessageCircle class="w-3.5 h-3.5 text-success" aria-hidden="true" />
          سيتواصل معك الطبيب على هذا الرقم عبر واتساب وقت الموعد.
        </p>
      </div>

      <div v-if="deliveryMode === 'home'" class="space-y-4 pt-2 border-t border-border-default" data-testid="home-fields">
        <FormGroup label="منطقة التغطية" name="coverage_area_id" required>
          <template #default="{ describedby }">
            <select
              id="coverage_area_id"
              v-model="coverageAreaId"
              name="coverage_area_id"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
            >
              <option value="" disabled selected>اختر المنطقة...</option>
              <option v-for="area in coverageAreas" :key="area.id" :value="area.id">
                {{ area.name }}
              </option>
            </select>
          </template>
        </FormGroup>

        <FormGroup label="العنوان التفصيلي" name="address_text" required>
          <template #default="{ describedby }">
            <Input
              id="address_text"
              v-model="addressText"
              name="address_text"
              :aria-describedby="describedby"
              placeholder="الشارع، المبنى، الطابق..."
            />
          </template>
        </FormGroup>

        <FormGroup label="ملاحظة للسائق" name="location_note">
          <template #default="{ describedby }">
            <Input
              id="location_note"
              v-model="locationNote"
              name="location_note"
              :aria-describedby="describedby"
              placeholder="أي ملاحظة إضافية (اختياري)"
            />
          </template>
        </FormGroup>

        <FormGroup label="موقع الزيارة على الخريطة" name="home_location">
          <template #default>
            <LocationPickerMap v-model="homeLocation" />
          </template>
        </FormGroup>
      </div>
    </section>

    <!-- Step 2: Doctor and service -->
    <section v-if="step === 2" class="bg-surface-card rounded-2xl border border-border-default p-5 space-y-4 shadow-sm">
      <div>
        <label class="block text-sm font-bold text-text-primary mb-2">
          الطبيب أو مقدّم الخدمة
          <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <p v-if="doctors.length === 0" class="text-sm text-text-secondary py-3">لا يوجد أطبّاء متاحون للحجز حاليًا.</p>
        <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <button
            v-for="d in doctors"
            :key="d.id"
            type="button"
            :aria-pressed="doctorId === d.id"
            :class="[
              'group text-start rounded-2xl border-2 overflow-hidden transition shadow-sm',
              doctorId === d.id
                ? 'border-brand ring-4 ring-brand/15 bg-brand/5'
                : 'border-border-default bg-surface-card hover:border-brand/40 hover:shadow-md',
            ]"
            @click="doctorId = d.id"
          >
            <!-- Avatar / fallback initial -->
            <div
              v-if="d.image_path"
              class="w-full aspect-square bg-cover bg-center"
              :style="{ backgroundImage: `url(/storage/${d.image_path})` }"
              role="img"
              :aria-label="d.name"
            />
            <div v-else class="w-full aspect-square grid place-items-center bg-brand/10 text-brand text-3xl font-extrabold">
              {{ Array.from(d.name ?? 'ط')[0] }}
            </div>
            <!-- Meta -->
            <div class="p-2.5 space-y-0.5">
              <span class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-brand/10 text-brand">
                {{ teamRoleLabel(d) }}
              </span>
              <p class="text-sm font-extrabold text-text-primary truncate">{{ d.name }}</p>
              <p class="text-[11px] text-text-secondary truncate">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
            </div>
          </button>
        </div>
      </div>

      <FormGroup label="الخدمات" name="services" required>
        <template #default>
          <p class="text-xs text-text-tertiary mb-2">يمكنك اختيار أكثر من خدمة في الموعد ذاته — المدّة والسعر يَتجمَّعان.</p>
          <ul v-if="doctorId && filteredServices.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <li v-for="s in filteredServices" :key="s.id" data-testid="service-option">
              <label
                :class="[
                  'flex items-start gap-2 p-3 rounded-lg border-2 cursor-pointer transition',
                  selectedServiceIds.includes(s.id)
                    ? 'border-brand bg-brand/5 ring-2 ring-brand/15'
                    : 'border-border-default hover:border-brand/40',
                ]"
              >
                <input
                  type="checkbox"
                  :value="s.id"
                  :checked="selectedServiceIds.includes(s.id)"
                  class="h-4 w-4 mt-0.5"
                  @change="toggleService(s.id)"
                />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-bold text-text-primary truncate">{{ s.name }}</p>
                  <p class="text-xs text-text-tertiary">
                    {{ s.price_override ?? s.base_price }} ₪ · {{ s.duration_minutes }} د
                  </p>
                </div>
              </label>
            </li>
          </ul>
          <p v-else-if="doctorId" class="text-sm text-text-secondary">
            اختر الطبيب أوّلًا لعرض خدماته.
          </p>
          <div v-if="selectedServiceIds.length > 0" class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-brand bg-brand/5 px-3 py-1.5 rounded-md">
            <span>{{ selectedServiceIds.length }} خدمة</span>
            <span aria-hidden="true">·</span>
            <span>المدّة الكاملة: {{ totalDurationMinutes }} دقيقة</span>
          </div>
        </template>
      </FormGroup>

      <div v-if="filteredServices.length === 0 && doctorId && deliveryMode === 'home'" class="rounded-md bg-warning/5 border border-warning/30 px-3 py-2 text-sm text-warning">
        لا توجد خدمات منزلية متاحة لهذا الطبيب. اختر طبيبًا آخر أو ارجع للخطوة السابقة لاختيار "في المركز".
      </div>
      <div v-if="filteredServices.length === 0 && doctorId && deliveryMode === 'online'" class="rounded-md bg-warning/5 border border-warning/30 px-3 py-2 text-sm text-warning">
        لا توجد خدمات أونلاين متاحة لهذا الطبيب. اختر طبيبًا آخر أو ارجع للخطوة السابقة.
      </div>
    </section>

    <!-- Step 3: Date and time slot -->
    <section v-if="step === 3" class="bg-surface-card rounded-2xl border border-border-default p-5 space-y-4 shadow-sm">
      <FormGroup label="تاريخ الموعد" name="booking_date" required>
        <template #default>
          <div
            v-if="daysError"
            class="rounded-md bg-danger/10 border border-danger/20 p-3 text-sm text-danger"
            role="alert"
          >
            تعذّر تحميل الأيام المتاحة، حاول مرة أخرى.
          </div>
          <MonthCalendar
            v-model="selectedDate"
            :available-days="availableDays"
            @select="onCalendarSelect"
            @month-change="onCalendarMonthChange"
          />
          <p
            v-if="!daysLoading && !daysError && availableDays.length === 0"
            class="mt-2 text-sm text-text-secondary"
          >
            لا أيام متاحة هذا الشهر
          </p>
        </template>
      </FormGroup>

      <div v-if="selectedDate">
        <p class="text-sm font-bold text-text-primary mb-2 inline-flex items-center gap-1.5">
          <Clock class="w-4 h-4 text-brand" aria-hidden="true" />
          الفترات المتاحة في {{ formatSelectedDate(selectedDate) }}
        </p>

        <div v-if="slotsLoading" class="text-sm text-text-secondary py-3">
          جارٍ تحميل الفترات...
        </div>

        <div
          v-else-if="slotsError"
          class="rounded-md bg-danger/10 border border-danger/20 p-3 text-sm text-danger"
          role="alert"
        >
          تعذّر تحميل الفترات، حاول مرة أخرى.
        </div>

        <PageStates v-else :is-empty="slotsEmpty">
          <template #empty>
            <div class="text-sm text-text-secondary py-4 text-center">لا فترات متاحة في هذا اليوم — جرّب يومًا آخر.</div>
          </template>
          <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
            <button
              v-for="slot in slots"
              :key="slot.start"
              type="button"
              :class="[
                'rounded-xl border-2 px-3 py-2.5 text-sm font-bold transition',
                selectedStart === slot.start
                  ? 'border-brand bg-brand text-white shadow-sm'
                  : 'border-border-default bg-surface-card text-text-primary hover:border-brand hover:text-brand',
              ]"
              :dir="'ltr'"
              @click="selectedStart = slot.start"
            >
              {{ slot.label }}
            </button>
          </div>
        </PageStates>
      </div>

      <!-- Price preview -->
      <div v-if="previewPrice" class="rounded-xl bg-brand/5 ring-1 ring-brand/15 p-4 text-sm space-y-1">
        <p class="font-bold text-brand">معاينة السعر</p>
        <div class="flex items-center justify-between">
          <span class="text-text-secondary">السعر الأساسي</span>
          <span class="font-bold text-text-primary">{{ previewPrice.base }} ₪</span>
        </div>
        <div v-if="previewPrice.surcharge > 0" class="flex items-center justify-between">
          <span class="text-text-secondary">رسوم الزيارة المنزلية</span>
          <span class="font-bold text-text-primary">{{ previewPrice.surcharge }} ₪</span>
        </div>
        <div class="flex items-center justify-between pt-2 border-t border-brand/20">
          <span class="font-bold text-text-primary">الإجمالي</span>
          <span :class="['text-lg font-extrabold', discountAmountPreview > 0 ? 'text-text-secondary line-through' : 'text-brand']">
            {{ previewPrice.total }} ₪
          </span>
        </div>
        <div v-if="discountAmountPreview > 0" class="flex items-center justify-between">
          <span class="text-text-secondary">الخصم</span>
          <span class="font-bold text-danger">- {{ discountAmountPreview }} ₪</span>
        </div>
        <div v-if="discountAmountPreview > 0" class="flex items-center justify-between pt-2 border-t border-brand/20">
          <span class="font-bold text-text-primary">المبلغ المستحقّ</span>
          <span class="text-lg font-extrabold text-brand">{{ previewPrice.total - discountAmountPreview }} ₪</span>
        </div>
        <p class="text-[11px] text-text-tertiary pt-1">* السعر النهائي يحتسبه النظام عند تأكيد الحجز.</p>
      </div>

      <!-- Staff discount — admin booking only. Cash-only (loyalty-points
           payments don't stack with a discount). Toggle reveals the
           type/value/reason inputs and the price block above updates
           live with the resolved ₪ amount. -->
      <div
        v-if="customerPicker && paymentMethod === 'cash'"
        class="rounded-xl border-2 border-warning/30 bg-warning/5 p-4 space-y-3"
      >
        <label class="flex items-center gap-2 cursor-pointer">
          <input
            v-model="discountEnabled"
            type="checkbox"
            class="w-4 h-4 rounded border-2 border-warning text-warning focus:ring-warning"
          />
          <span class="text-sm font-bold text-text-primary">تطبيق خصم على هذا الحجز</span>
        </label>

        <div v-if="discountEnabled" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <label class="flex flex-col gap-1">
              <span class="text-xs font-bold text-text-secondary">نوع الخصم</span>
              <select
                v-model="discountType"
                class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
              >
                <option value="percent">نسبة (%)</option>
                <option value="fixed">مبلغ (₪)</option>
              </select>
            </label>
            <label class="flex flex-col gap-1">
              <span class="text-xs font-bold text-text-secondary">
                القيمة {{ discountType === 'percent' ? '(%)' : '(₪)' }}
              </span>
              <Input
                v-model="discountValue"
                type="number"
                step="0.01"
                min="0.01"
                :max="discountType === 'percent' ? 100 : undefined"
                placeholder="0"
              />
            </label>
          </div>
          <label class="flex flex-col gap-1">
            <span class="text-xs font-bold text-text-secondary">السبب (اختياري)</span>
            <Input
              v-model="discountReason"
              type="text"
              maxlength="500"
              placeholder="عرض موسمي، خصم عائلي، ..."
            />
          </label>
          <p v-if="errors.discount_value" class="text-xs text-danger">{{ errors.discount_value }}</p>
          <p v-if="errors.discount_type" class="text-xs text-danger">{{ errors.discount_type }}</p>
        </div>
      </div>

      <PaymentMethodPicker
        v-model="paymentMethod"
        :loyalty-enabled="selectedService?.loyalty_enabled ?? false"
        :loyalty-redemption-points="selectedService?.loyalty_redemption_points ?? 0"
        :loyalty-balance="loyaltyBalance"
      />
    </section>

    <!-- Live summary — visible card-stack once selections start landing -->
    <aside
      v-if="step > (customerPicker ? 0 : 1) && (selectedCustomerLabel || deliveryMode || selectedDoctor || selectedService || selectedStart)"
      class="bg-surface-card rounded-2xl border-2 border-brand/15 overflow-hidden shadow-sm"
    >
      <div class="bg-brand/5 px-4 py-2.5 border-b border-brand/15 inline-flex items-center gap-2 w-full">
        <Check class="w-4 h-4 text-brand" aria-hidden="true" />
        <p class="text-sm font-bold text-brand">ملخّص الحجز</p>
      </div>
      <ul class="divide-y divide-border-default text-sm">
        <li v-if="customerPicker && selectedCustomerLabel" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <UserIcon class="w-3.5 h-3.5" aria-hidden="true" />
            العميل
          </span>
          <span class="font-bold text-text-primary text-end">{{ selectedCustomerLabel }}</span>
        </li>
        <li class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <component :is="deliveryIcon(deliveryMode)" class="w-3.5 h-3.5" aria-hidden="true" />
            طريقة الخدمة
          </span>
          <span class="font-bold text-text-primary text-end">{{ deliveryLabel(deliveryMode) }}</span>
        </li>
        <li v-if="deliveryMode === 'online' && whatsappPhone" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <MessageCircle class="w-3.5 h-3.5 text-success" aria-hidden="true" />
            رقم واتساب
          </span>
          <span class="font-bold text-text-primary text-end" dir="ltr">{{ whatsappPhone }}</span>
        </li>
        <li v-if="selectedCoverageArea" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <MapPin class="w-3.5 h-3.5" aria-hidden="true" />
            المنطقة
          </span>
          <span class="font-bold text-text-primary text-end">{{ selectedCoverageArea.name }}</span>
        </li>
        <li v-if="deliveryMode === 'home' && homeLocation" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <MapPin class="w-3.5 h-3.5" aria-hidden="true" />
            الإحداثيات
          </span>
          <span class="font-bold text-text-primary text-end" dir="ltr">
            {{ homeLocation.lat.toFixed(5) }}, {{ homeLocation.lng.toFixed(5) }}
          </span>
        </li>
        <li v-if="selectedDoctor" class="px-4 py-2.5 flex items-center justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <Stethoscope class="w-3.5 h-3.5" aria-hidden="true" />
            مقدّم الخدمة
          </span>
          <span class="inline-flex items-center gap-2">
            <span
              v-if="selectedDoctor.image_path"
              class="w-7 h-7 rounded-full bg-cover bg-center"
              :style="{ backgroundImage: `url(/storage/${selectedDoctor.image_path})` }"
              aria-hidden="true"
            />
            <span class="font-bold text-text-primary text-end">{{ selectedDoctor.name }}</span>
          </span>
        </li>
        <li v-if="selectedService" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <CalendarDays class="w-3.5 h-3.5" aria-hidden="true" />
            الخدمة
          </span>
          <span class="font-bold text-text-primary text-end">{{ selectedService.name }}</span>
        </li>
        <li v-if="selectedStart" class="px-4 py-2.5 flex items-start justify-between gap-3">
          <span class="inline-flex items-center gap-1.5 text-text-secondary">
            <Clock class="w-3.5 h-3.5" aria-hidden="true" />
            الموعد
          </span>
          <span class="font-bold text-text-primary text-end" dir="ltr">
            {{ formatSelectedDate(selectedStart) }} · {{ formatSelectedTime(selectedStart) }}
          </span>
        </li>
        <li v-if="previewPrice" class="px-4 py-3 flex items-center justify-between gap-3 bg-brand/5">
          <span class="text-text-primary font-bold">الإجمالي</span>
          <span class="text-lg font-extrabold text-brand">{{ previewPrice.total }} ₪</span>
        </li>
      </ul>
    </aside>

    <!-- Navigation buttons -->
    <div class="flex items-center justify-between gap-3">
      <Button
        v-if="step > (customerPicker ? 0 : 1)"
        type="button"
        variant="outline"
        class="gap-1.5"
        @click="prevStep"
      >
        <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
        <span>السابق</span>
      </Button>
      <div v-else />

      <Button
        v-if="step < 3"
        type="button"
        class="gap-1.5"
        :disabled="
          (step === 0 && !canAdvanceStep0()) ||
          (step === 1 && !canAdvanceStep1()) ||
          (step === 2 && !canAdvanceStep2())
        "
        @click="nextStep"
      >
        <span>التالي</span>
        <ArrowRight class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
      </Button>
      <Button
        v-else
        type="button"
        class="gap-1.5"
        :disabled="!canAdvanceStep3()"
        @click="handleSubmit"
      >
        <Check class="w-4 h-4" aria-hidden="true" />
        <span>تأكيد الحجز</span>
      </Button>
    </div>
  </div>
</template>
