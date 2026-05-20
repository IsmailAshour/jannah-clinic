<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'

defineProps({
  doctors: { type: Array, default: () => [] },
})
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الأطبّاء" description="فريقنا الطبّي." />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="d in doctors"
          :key="d.id"
          class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2"
        >
          <h3 class="font-medium text-text-primary">{{ d.user?.name }}</h3>
          <p class="text-sm text-text-secondary">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
          <p v-if="d.bio" class="text-xs text-text-tertiary line-clamp-2">{{ d.bio }}</p>
          <p class="text-xs text-text-tertiary">⭐ {{ Number(d.rating_average).toFixed(1) }}</p>
        </article>
      </div>

      <p v-if="doctors.length === 0" class="text-center text-text-secondary py-6">
        لا يوجد أطبّاء حاليًا.
      </p>
    </div>
  </ClientShell>
</template>
