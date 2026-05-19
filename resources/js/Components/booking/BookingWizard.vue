<script setup>
import { ref, computed, watch } from 'vue'
import { FormGroup, FormSection, PageStates, MonthCalendar } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  coverageAreas: { type: Array, default: () => [] },
  availabilityUrl: { type: String, required: true },
  availabilityDaysUrl: { type: String, required: true },
  homeSurchargePct: { type: [String, Number], default: 0 },
  customerPicker: { type: Boolean, default: false },
  customers: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['submit'])

// Step management
const step = ref(props.customerPicker ? 0 : 1)

// Customer picker state (admin only — step 0)
const customerMode = ref('existing') // 'existing' | 'new'
const selectedCustomerId = ref(null)
const newCustomerName = ref('')
const newCustomerEmail = ref('')
const newCustomerPhone = ref('')

// Step 1: delivery mode
const deliveryMode = ref('center')
const coverageAreaId = ref(null)
const addressText = ref('')
const locationNote = ref('')

// Step 2: doctor + service
const doctorId = ref(null)
const serviceId = ref(null)

const selectedDoctor = computed(() => props.doctors.find(d => d.id === doctorId.value) ?? null)

const filteredServices = computed(() => {
  if (!selectedDoctor.value) return []
  return selectedDoctor.value.services.filter(s => {
    if (deliveryMode.value === 'home') return s.home_service_enabled
    return true
  })
})

const selectedService = computed(() => {
  if (!serviceId.value || !selectedDoctor.value) return null
  return selectedDoctor.value.services.find(s => s.id === serviceId.value) ?? null
})

watch(doctorId, () => { serviceId.value = null })
watch(deliveryMode, () => { serviceId.value = null })

// Step 3: date + slot
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
  if (!doctorId.value || !serviceId.value || !calMonth.value) return
  daysLoading.value = true
  daysError.value = false
  try {
    const { from, to } = calMonth.value
    const url = `${props.availabilityDaysUrl}?doctor=${doctorId.value}&service=${serviceId.value}&from=${from}&to=${to}`
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
watch([doctorId, serviceId], () => {
  if (step.value === 3) fetchDays()
})

async function fetchSlots() {
  if (!doctorId.value || !serviceId.value || !selectedDate.value) return
  slotsLoading.value = true
  slotsEmpty.value = false
  slotsError.value = false
  slots.value = []
  selectedStart.value = null
  try {
    const url = `${props.availabilityUrl}?doctor=${doctorId.value}&service=${serviceId.value}&date=${selectedDate.value}`
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

watch(serviceId, () => {
  selectedDate.value = ''
  slots.value = []
  slotsEmpty.value = false
  slotsError.value = false
  selectedStart.value = null
  availableDays.value = []
  daysError.value = false
})

// Expose internals for testing
defineExpose({
  step, doctorId, serviceId, deliveryMode, selectedDate,
  slots, slotsEmpty, slotsLoading, slotsError, selectedStart,
  availableDays, daysLoading, daysError, calMonth,
  fetchSlots, fetchDays,
  // Test helper: call fetchSlots with pre-set values
  async fetchSlotsForTest(dId, sId, date) {
    doctorId.value = dId
    serviceId.value = sId
    selectedDate.value = date
    await fetchSlots()
  },
  // Test helper: set doctor/service + visible month, then fetch days
  async fetchDaysForTest(dId, sId, range) {
    doctorId.value = dId
    serviceId.value = sId
    calMonth.value = range
    await fetchDays()
  },
})

// Price preview
const previewPrice = computed(() => {
  if (!selectedService.value) return null
  const base = Number(selectedService.value.price_override ?? selectedService.value.base_price)
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
  return true
}

function canAdvanceStep2() {
  return !!doctorId.value && !!serviceId.value
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

function handleSubmit() {
  const payload = {
    doctor: doctorId.value,
    service: serviceId.value,
    start: selectedStart.value,
    delivery_mode: deliveryMode.value,
  }

  if (deliveryMode.value === 'home') {
    payload.coverage_area_id = coverageAreaId.value
    payload.address_text = addressText.value
    payload.location_note = locationNote.value || null
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
  <div class="space-y-6">
    <!-- Step indicator -->
    <div class="flex items-center gap-2 text-sm text-text-secondary">
      <span
        v-if="customerPicker"
        :class="step === 0 ? 'text-brand font-semibold' : 'text-text-tertiary'"
      >العميل</span>
      <span v-if="customerPicker" class="text-text-tertiary">·</span>
      <span :class="step === 1 ? 'text-brand font-semibold' : 'text-text-tertiary'">طريقة الخدمة</span>
      <span class="text-text-tertiary">·</span>
      <span :class="step === 2 ? 'text-brand font-semibold' : 'text-text-tertiary'">الطبيب والخدمة</span>
      <span class="text-text-tertiary">·</span>
      <span :class="step === 3 ? 'text-brand font-semibold' : 'text-text-tertiary'">الموعد</span>
    </div>

    <!-- Server booking error -->
    <div v-if="errors.booking" class="rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger" role="alert">
      {{ errors.booking }}
    </div>

    <!-- Step 0: Customer picker (admin only) -->
    <FormSection v-if="customerPicker && step === 0" title="العميل">
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

      <div v-if="customerMode === 'existing'">
        <FormGroup label="اختر العميل" name="customer_id" required>
          <template #default="{ describedby }">
            <select
              id="customer_id"
              v-model="selectedCustomerId"
              name="customer_id"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
            >
              <option value="" disabled selected>اختر عميلاً...</option>
              <option v-for="c in customers" :key="c.id" :value="c.id">
                {{ c.name }} — {{ c.phone || c.email }}
              </option>
            </select>
          </template>
        </FormGroup>
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
    </FormSection>

    <!-- Step 1: Delivery mode -->
    <FormSection v-if="step === 1" title="طريقة الخدمة">
      <div class="flex gap-6">
        <label class="flex items-center gap-2 cursor-pointer">
          <input v-model="deliveryMode" type="radio" value="center" class="h-4 w-4 accent-brand" />
          <span class="text-sm font-medium">في العيادة</span>
        </label>
        <label class="flex items-center gap-2 cursor-pointer" data-testid="home-radio">
          <input v-model="deliveryMode" type="radio" value="home" class="h-4 w-4 accent-brand" />
          <span class="text-sm font-medium">زيارة منزلية</span>
        </label>
      </div>

      <div v-if="deliveryMode === 'home'" class="space-y-4 mt-4" data-testid="home-fields">
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
      </div>
    </FormSection>

    <!-- Step 2: Doctor and service -->
    <FormSection v-if="step === 2" title="الطبيب والخدمة">
      <FormGroup label="الطبيب" name="doctor" required>
        <template #default="{ describedby }">
          <select
            id="doctor"
            v-model="doctorId"
            name="doctor"
            :aria-describedby="describedby"
            class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="" disabled selected>اختر الطبيب...</option>
            <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
          </select>
        </template>
      </FormGroup>

      <FormGroup label="الخدمة" name="service" required>
        <template #default="{ describedby }">
          <select
            id="service"
            v-model="serviceId"
            name="service"
            :aria-describedby="describedby"
            :disabled="!doctorId"
            class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand disabled:opacity-50"
          >
            <option value="" disabled selected>اختر الخدمة...</option>
            <option v-for="s in filteredServices" :key="s.id" :value="s.id">
              {{ s.name }} — {{ s.price_override ?? s.base_price }} ₪
            </option>
          </select>
        </template>
      </FormGroup>

      <div v-if="filteredServices.length === 0 && doctorId && deliveryMode === 'home'" class="text-sm text-text-secondary">
        لا توجد خدمات منزلية متاحة لهذا الطبيب.
      </div>
    </FormSection>

    <!-- Step 3: Date and time slot -->
    <FormSection v-if="step === 3" title="التاريخ والموعد">
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
        <p class="text-sm font-medium text-text-primary mb-3">الفترات المتاحة</p>

        <div v-if="slotsLoading" class="text-sm text-text-secondary">
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
            <div class="text-sm text-text-secondary py-4">لا فترات متاحة</div>
          </template>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="slot in slots"
              :key="slot.start"
              type="button"
              :class="[
                'rounded-md border px-4 py-2 text-sm transition-colors',
                selectedStart === slot.start
                  ? 'border-brand bg-brand text-white'
                  : 'border-border-default bg-surface-card text-text-primary hover:border-brand hover:text-brand',
              ]"
              @click="selectedStart = slot.start"
            >
              {{ slot.label }}
            </button>
          </div>
        </PageStates>
      </div>

      <!-- Price preview -->
      <div v-if="previewPrice" class="mt-4 rounded-md bg-surface-sunken p-4 text-sm space-y-1">
        <p class="font-medium text-text-primary">معاينة السعر (تقديرية)</p>
        <p class="text-text-secondary">السعر الأساسي: {{ previewPrice.base }} ₪</p>
        <p v-if="previewPrice.surcharge > 0" class="text-text-secondary">
          رسوم الزيارة المنزلية: {{ previewPrice.surcharge }} ₪
        </p>
        <p class="font-semibold text-text-primary">الإجمالي: {{ previewPrice.total }} ₪</p>
        <p class="text-xs text-text-tertiary">* هذا سعر تقديري. السعر النهائي يحتسبه الخادم عند تأكيد الحجز.</p>
      </div>
    </FormSection>

    <!-- Navigation buttons -->
    <div class="flex items-center justify-between">
      <Button
        v-if="step > (customerPicker ? 0 : 1)"
        type="button"
        variant="outline"
        @click="prevStep"
      >
        السابق
      </Button>
      <div v-else />

      <div class="flex gap-3">
        <Button
          v-if="step < 3"
          type="button"
          :disabled="
            (step === 0 && !canAdvanceStep0()) ||
            (step === 1 && !canAdvanceStep1()) ||
            (step === 2 && !canAdvanceStep2())
          "
          @click="nextStep"
        >
          التالي
        </Button>
        <Button
          v-else
          type="button"
          :disabled="!canAdvanceStep3()"
          @click="handleSubmit"
        >
          تأكيد الحجز
        </Button>
      </div>
    </div>
  </div>
</template>
