<script setup>
import { Link } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'

defineProps({ entry: { type: Object, required: true } })

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <Link href="/portal/medical-record" class="text-sm text-brand underline">← الرجوع</Link>
      <h1 class="text-lg font-semibold">زيارة بتاريخ {{ formatDate(entry.date) }}</h1>
      <p class="text-sm whitespace-pre-line">{{ entry.visible_summary }}</p>
      <section v-if="entry.prescriptions?.length">
        <h2 class="text-sm font-medium text-text-secondary mb-2">الوصفات</h2>
        <ul class="text-sm space-y-2">
          <li
            v-for="(p, i) in entry.prescriptions"
            :key="i"
            class="rounded-md border border-border-default bg-surface-card p-3"
          >
            <div class="font-medium">{{ p.medication_name }}</div>
            <div class="text-text-secondary">{{ p.dosage }} · {{ p.frequency }} · {{ p.duration }}</div>
            <div v-if="p.notes" class="text-text-secondary mt-1">{{ p.notes }}</div>
          </li>
        </ul>
      </section>
    </div>
  </ClientShell>
</template>
