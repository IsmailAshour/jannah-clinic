<script setup>
import { Link } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { FileText, Download } from 'lucide-vue-next'

defineProps({ entry: { type: Object, required: true } })

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' })
}
function humanSize(bytes) {
  if (!bytes) return '—'
  const kb = bytes / 1024
  if (kb < 1024) return `${kb.toFixed(1)} KB`
  return `${(kb / 1024).toFixed(1)} MB`
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
      <section v-if="entry.attachments?.length">
        <h2 class="text-sm font-medium text-text-secondary mb-2">الملفّات المرفقة</h2>
        <ul class="space-y-2">
          <li
            v-for="att in entry.attachments"
            :key="att.id"
            class="flex items-center gap-3 rounded-md border border-border-default bg-surface-card p-3"
          >
            <FileText class="w-5 h-5 text-brand shrink-0" aria-hidden="true" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-text-primary truncate">{{ att.title || att.original_filename }}</p>
              <p class="text-xs text-text-tertiary">{{ humanSize(att.file_size) }}</p>
            </div>
            <a
              :href="att.file_url"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:underline"
            >
              <Download class="w-3.5 h-3.5" aria-hidden="true" />
              تنزيل
            </a>
          </li>
        </ul>
      </section>
    </div>
  </ClientShell>
</template>
