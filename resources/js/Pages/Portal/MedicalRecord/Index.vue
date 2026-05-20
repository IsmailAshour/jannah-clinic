<script setup>
import { Link } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'

defineProps({
  medicalProfile: { type: Object, default: () => ({ chronic_conditions: null, allergies: null }) },
  entries: { type: Object, default: () => ({ data: [] }) },
})

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <h1 class="text-lg font-semibold">سجلي الطبي</h1>

      <section
        v-if="medicalProfile.chronic_conditions || medicalProfile.allergies"
        class="rounded-md border border-border-default bg-surface-card p-4"
      >
        <h2 class="text-sm font-medium text-text-secondary mb-2">معلومات صحية ثابتة</h2>
        <dl class="text-sm space-y-1">
          <div v-if="medicalProfile.chronic_conditions">
            <dt class="inline font-medium">الأمراض المزمنة:</dt>
            <dd class="inline ms-1 whitespace-pre-line">{{ medicalProfile.chronic_conditions }}</dd>
          </div>
          <div v-if="medicalProfile.allergies">
            <dt class="inline font-medium">الحساسية:</dt>
            <dd class="inline ms-1 whitespace-pre-line">{{ medicalProfile.allergies }}</dd>
          </div>
        </dl>
      </section>

      <section class="space-y-3">
        <h2 class="text-sm font-medium text-text-secondary">الزيارات السابقة</h2>
        <p v-if="!entries.data?.length" class="text-sm text-text-tertiary">لا توجد زيارات مسجّلة بعد.</p>
        <article
          v-for="e in entries.data"
          :key="e.id"
          class="rounded-md border border-border-default bg-surface-card p-4"
        >
          <header class="flex items-center justify-between mb-2">
            <time class="text-sm text-text-secondary">{{ formatDate(e.date) }}</time>
            <Link :href="`/portal/medical-record/entries/${e.id}`" class="text-sm text-brand underline">عرض كامل</Link>
          </header>
          <p class="text-sm whitespace-pre-line">{{ e.visible_summary }}</p>
          <ul v-if="e.prescriptions?.length" class="mt-2 text-sm list-disc list-inside">
            <li v-for="(p, i) in e.prescriptions" :key="i">
              {{ p.medication_name }} — {{ p.dosage }} · {{ p.frequency }} · {{ p.duration }}
            </li>
          </ul>
        </article>
      </section>
    </div>
  </ClientShell>
</template>
