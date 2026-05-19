<script setup>
import { useForm } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import BookingWizard from '@/Components/booking/BookingWizard.vue'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  coverageAreas: { type: Array, default: () => [] },
  homeSurchargePct: { type: [String, Number], default: 0 },
})

const form = useForm({
  doctor: null,
  service: null,
  start: null,
  delivery_mode: 'center',
  coverage_area_id: null,
  address_text: null,
  location_note: null,
})

function handleSubmit(payload) {
  form.doctor = payload.doctor
  form.service = payload.service
  form.start = payload.start
  form.delivery_mode = payload.delivery_mode
  form.coverage_area_id = payload.coverage_area_id ?? null
  form.address_text = payload.address_text ?? null
  form.location_note = payload.location_note ?? null

  form.post(route('portal.booking.store'))
}
</script>

<template>
  <ClientShell>
    <div class="p-4 max-w-2xl mx-auto">
      <PageHeader title="حجز موعد" />

      <div v-if="form.errors.booking" class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger" role="alert">
        {{ form.errors.booking }}
      </div>

      <BookingWizard
        :doctors="doctors"
        :coverage-areas="coverageAreas"
        :availability-url="'/portal/availability'"
        :home-surcharge-pct="homeSurchargePct"
        :customer-picker="false"
        :errors="form.errors"
        @submit="handleSubmit"
      />
    </div>
  </ClientShell>
</template>
