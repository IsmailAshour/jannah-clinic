<script setup>
import { Link } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { Button } from '@/Components/ui/button'

defineProps({
  featuredServices: { type: Array, default: () => [] },
  featuredDoctor: { type: Object, default: null },
  tip: { type: String, default: null },
  greetingName: { type: String, default: null },
  nextAppointment: { type: Object, default: null },
})

function formatDateTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-6">
      <!-- Hero -->
      <section class="bg-surface-card rounded-lg shadow-sm p-6 space-y-3">
        <h1 v-if="greetingName" class="text-2xl font-bold text-text-primary">أهلًا {{ greetingName }} 👋</h1>
        <h1 v-else class="text-2xl font-bold text-text-primary">أهلًا بك في عيادة جنّة</h1>
        <p class="text-sm text-text-secondary">نهتمّ بصحّتك وجمالك — احجز موعدك بسهولة.</p>
        <Link href="/services">
          <Button>تصفّح الخدمات</Button>
        </Link>
      </section>

      <!-- Next appointment (authed only) -->
      <section v-if="nextAppointment" class="bg-brand/10 border border-brand/30 rounded-lg p-4">
        <p class="text-sm font-semibold text-brand">موعدك القادم</p>
        <p class="text-sm text-text-primary mt-1">
          {{ formatDateTime(nextAppointment.start_at) }} — {{ nextAppointment.service?.name }}
          مع {{ nextAppointment.doctor?.user?.name }}
        </p>
      </section>

      <!-- Featured services -->
      <section v-if="featuredServices.length > 0" class="space-y-3">
        <h2 class="text-lg font-semibold text-text-primary">خدمات مميّزة</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <article v-for="s in featuredServices" :key="s.id" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
            <p class="text-xs text-text-tertiary">{{ s.category?.name }}</p>
            <h3 class="font-medium text-text-primary">{{ s.name }}</h3>
            <p class="text-sm text-text-secondary">{{ s.base_price }} ₪ · {{ s.duration_minutes }} دقيقة</p>
          </article>
        </div>
      </section>

      <!-- Featured doctor -->
      <section v-if="featuredDoctor" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
        <p class="text-xs text-text-tertiary">طبيب مميّز</p>
        <h3 class="font-medium text-text-primary">{{ featuredDoctor.user?.name }}</h3>
        <p class="text-sm text-text-secondary">{{ featuredDoctor.specialty || 'متعدّد التخصّصات' }}</p>
        <p class="text-xs text-text-tertiary">التقييم: {{ Number(featuredDoctor.rating_average).toFixed(1) }} ⭐</p>
      </section>

      <!-- Tip -->
      <section v-if="tip" class="bg-surface-card rounded-lg shadow-sm p-4">
        <p class="text-xs text-text-tertiary mb-1">نصيحة اليوم</p>
        <p class="text-sm text-text-primary">{{ tip }}</p>
      </section>
    </div>
  </ClientShell>
</template>
