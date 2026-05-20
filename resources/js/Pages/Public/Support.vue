<script setup>
import { ref } from 'vue'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'

defineProps({
  faqs: { type: Array, default: () => [] },
  contact: { type: Object, default: () => ({}) },
})

const openIndex = ref(null)
function toggle(i) {
  openIndex.value = openIndex.value === i ? null : i
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الدعم" description="نحن هنا لمساعدتك." />

      <section v-if="contact.phone || contact.whatsapp || contact.address" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
        <p class="text-sm font-semibold text-text-primary">للتواصل</p>
        <p v-if="contact.phone" class="text-sm text-text-secondary">
          📞 <a :href="`tel:${contact.phone}`" dir="ltr" class="text-brand underline">{{ contact.phone }}</a>
        </p>
        <p v-if="contact.whatsapp" class="text-sm text-text-secondary">
          💬 <a :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="text-brand underline">واتساب</a>
        </p>
        <p v-if="contact.address" class="text-sm text-text-secondary">📍 {{ contact.address }}</p>
      </section>

      <section v-if="faqs.length > 0" class="space-y-2">
        <p class="text-sm font-semibold text-text-primary">أسئلة شائعة</p>
        <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
          <li v-for="(f, i) in faqs" :key="i">
            <button
              type="button"
              class="w-full p-4 flex items-center justify-between text-start hover:bg-surface-page transition"
              :aria-expanded="openIndex === i"
              @click="toggle(i)"
            >
              <span class="text-sm font-medium text-text-primary">{{ f.q }}</span>
              <span class="text-text-tertiary text-lg leading-none">{{ openIndex === i ? '−' : '+' }}</span>
            </button>
            <div v-if="openIndex === i" class="px-4 pb-4 text-sm text-text-secondary">
              {{ f.a }}
            </div>
          </li>
        </ul>
      </section>
    </div>
  </ClientShell>
</template>
