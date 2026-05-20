<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const selectedCategory = computed(() => props.filters?.category ?? null)

function filterByCategory(catId) {
  router.get('/services', catId ? { category: catId } : {}, { preserveScroll: true })
}

const visibleServices = computed(() => {
  if (!selectedCategory.value) return props.services
  return props.services.filter((s) => String(s.category_id) === String(selectedCategory.value))
})
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="خدماتنا" description="استعرض الخدمات المتاحة." />

      <div class="flex flex-wrap gap-2">
        <Button
          :variant="!selectedCategory ? 'default' : 'outline'"
          size="sm"
          @click="filterByCategory(null)"
        >الكل</Button>
        <Button
          v-for="c in categories"
          :key="c.id"
          :variant="String(selectedCategory) === String(c.id) ? 'default' : 'outline'"
          size="sm"
          @click="filterByCategory(c.id)"
        >{{ c.name }}</Button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="s in visibleServices"
          :key="s.id"
          class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2"
        >
          <p class="text-xs text-text-tertiary">{{ s.category?.name }}</p>
          <h3 class="font-medium text-text-primary">{{ s.name }}</h3>
          <p class="text-sm text-text-secondary line-clamp-2">{{ s.description || '' }}</p>
          <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-brand">{{ s.base_price }} ₪</p>
            <p class="text-xs text-text-tertiary">{{ s.duration_minutes }} دقيقة</p>
          </div>
        </article>
      </div>

      <p v-if="visibleServices.length === 0" class="text-center text-text-secondary py-6">
        لا توجد خدمات مطابقة.
      </p>
    </div>
  </ClientShell>
</template>
