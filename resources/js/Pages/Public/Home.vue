<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { Button } from '@/Components/ui/button'
import { iconForCategory, colorClassForCategory } from '@/lib/categoryIcons'

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')

defineProps({
  categories: { type: Array, default: () => [] },
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
      <section class="bg-gradient-to-bl from-brand/15 to-surface-card rounded-xl shadow-sm p-6 space-y-3">
        <h1 v-if="greetingName" class="text-2xl font-bold text-text-primary">أهلًا {{ greetingName }} 👋</h1>
        <h1 v-else class="text-2xl font-bold text-text-primary">أهلًا بك في {{ clinicName }}</h1>
        <p class="text-sm text-text-secondary">نهتمّ بصحّتك وجمالك — اختر فئة الخدمة لتبدأ.</p>
      </section>

      <!-- Next appointment (authed only) -->
      <section v-if="nextAppointment" class="bg-brand/10 border border-brand/30 rounded-lg p-4">
        <p class="text-sm font-semibold text-brand">موعدك القادم</p>
        <p class="text-sm text-text-primary mt-1">
          {{ formatDateTime(nextAppointment.start_at) }} — {{ nextAppointment.service?.name }}
          مع {{ nextAppointment.doctor?.user?.name }}
        </p>
      </section>

      <!-- Categories — iconic squares (the primary navigation into services) -->
      <section v-if="categories.length > 0" class="space-y-3">
        <h2 class="text-lg font-semibold text-text-primary">الفئات</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <Link
            v-for="c in categories"
            :key="c.id"
            :href="`/services?category=${c.id}`"
            class="bg-surface-card rounded-xl shadow-sm p-4 flex flex-col items-center gap-2 hover:shadow-md transition"
          >
            <div :class="['w-14 h-14 rounded-2xl flex items-center justify-center', colorClassForCategory(c)]">
              <component :is="iconForCategory(c)" class="w-7 h-7" aria-hidden="true" />
            </div>
            <p class="text-sm font-medium text-text-primary text-center">{{ c.name }}</p>
            <p class="text-xs text-text-tertiary">{{ c.services_count }} خدمة</p>
          </Link>
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

      <!-- Browse all services CTA -->
      <div class="flex justify-center pt-2">
        <Link href="/services">
          <Button variant="outline">عرض كل الخدمات</Button>
        </Link>
      </div>
    </div>
  </ClientShell>
</template>
