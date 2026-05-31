<script setup>
import { useForm } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import BookingWizard from '@/Components/booking/BookingWizard.vue'

const props = defineProps({
  doctors: { type: Array, default: () => [] },
  coverageAreas: { type: Array, default: () => [] },
  homeSurchargePct: { type: [String, Number], default: 0 },
  loyaltyBalance: { type: Number, default: 0 },
})

const form = useForm({
  doctor: null,
  services: [],
  start: null,
  delivery_mode: 'center',
  coverage_area_id: null,
  address_text: null,
  location_note: null,
  lat: null,
  lng: null,
  whatsapp_phone: null,
  payment_method: 'cash',
})

function handleSubmit(payload) {
  form.doctor = payload.doctor
  form.services = payload.services ?? []
  form.start = payload.start
  form.delivery_mode = payload.delivery_mode
  form.coverage_area_id = payload.coverage_area_id ?? null
  form.address_text = payload.address_text ?? null
  form.location_note = payload.location_note ?? null
  form.lat = payload.lat ?? null
  form.lng = payload.lng ?? null
  form.whatsapp_phone = payload.whatsapp_phone ?? null
  form.payment_method = payload.payment_method ?? 'cash'

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
      <div v-if="form.errors.whatsapp_phone" class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger" role="alert">
        {{ form.errors.whatsapp_phone }}
      </div>
      <!-- Catch-all banner so any validation error we don't render inline
           still surfaces — silent failures make the page look frozen. -->
      <div
        v-if="Object.keys(form.errors).some(k => !['booking', 'whatsapp_phone'].includes(k))"
        class="mb-4 rounded-md bg-danger/10 border border-danger/20 p-4 text-sm text-danger space-y-1"
        role="alert"
      >
        <p class="font-bold">تعذّر إتمام الحجز — صحّح الأخطاء التالية:</p>
        <ul class="list-disc list-inside">
          <li v-for="(msg, key) in form.errors" :key="key">{{ msg }}</li>
        </ul>
      </div>

      <BookingWizard
        :doctors="doctors"
        :coverage-areas="coverageAreas"
        :availability-url="'/portal/availability'"
        :availability-days-url="'/portal/availability/days'"
        :home-surcharge-pct="homeSurchargePct"
        :customer-picker="false"
        :loyalty-balance="loyaltyBalance"
        :errors="form.errors"
        @submit="handleSubmit"
      />
    </div>
  </ClientShell>
</template>
