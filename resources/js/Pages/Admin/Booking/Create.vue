<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
import BookingWizard from '@/Components/booking/BookingWizard.vue'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  coverageAreas: { type: Array, default: () => [] },
  homeSurchargePct: { type: [String, Number], default: 0 },
  customers: { type: Array, default: () => [] },
})

const form = useForm({
  doctor: null,
  service: null,
  start: null,
  delivery_mode: 'center',
  coverage_area_id: null,
  address_text: null,
  location_note: null,
  customer_id: null,
  new_customer: null,
  payment_method: 'cash',
})

function handleSubmit(payload) {
  form.doctor = payload.doctor
  form.service = payload.service
  form.start = payload.start
  form.delivery_mode = payload.delivery_mode
  form.coverage_area_id = payload.coverage_area_id ?? null
  form.address_text = payload.address_text ?? null
  form.location_note = payload.location_note ?? null
  form.customer_id = payload.customer_id ?? null
  form.new_customer = payload.new_customer ?? null
  form.payment_method = payload.payment_method ?? 'cash'

  form.post(route('admin.booking.store'))
}
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 max-w-2xl mx-auto">
      <PageHeader title="حجز موعد نيابةً عن عميل" />

      <div v-if="form.errors.booking" class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger" role="alert">
        {{ form.errors.booking }}
      </div>

      <BookingWizard
        :doctors="doctors"
        :coverage-areas="coverageAreas"
        :availability-url="'/admin/availability'"
        :availability-days-url="'/admin/availability/days'"
        :home-surcharge-pct="homeSurchargePct"
        :customer-picker="true"
        :customers="customers"
        :errors="form.errors"
        @submit="handleSubmit"
      />
    </div>
  </AdminShell>
</template>
