<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, AuthGuardLink } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { iconForCategory, colorClassForCategory } from '@/lib/categoryIcons'

const props = defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const selectedCategory = computed(() => props.filters?.category ?? null)
const selectedCategoryObj = computed(() => {
  if (!selectedCategory.value) return null
  return props.categories.find((c) => String(c.id) === String(selectedCategory.value)) ?? null
})

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
      <!-- Category header — when one is selected -->
      <div v-if="selectedCategoryObj" class="bg-surface-card rounded-xl shadow-sm p-4 flex items-center gap-3">
        <div :class="['w-12 h-12 rounded-2xl flex items-center justify-center', colorClassForCategory(selectedCategoryObj)]">
          <component :is="iconForCategory(selectedCategoryObj)" class="w-6 h-6" aria-hidden="true" />
        </div>
        <div class="flex-1">
          <h1 class="text-lg font-bold text-text-primary">{{ selectedCategoryObj.name }}</h1>
          <p class="text-xs text-text-tertiary">{{ visibleServices.length }} خدمة</p>
        </div>
        <Button size="sm" variant="ghost" @click="filterByCategory(null)">عرض الكل</Button>
      </div>

      <PageHeader v-else title="خدماتنا" description="استعرض الخدمات المتاحة." />

      <!-- Category chips (when no selection) -->
      <div v-if="!selectedCategoryObj" class="flex flex-wrap gap-2">
        <Button
          v-for="c in categories"
          :key="c.id"
          variant="outline"
          size="sm"
          @click="filterByCategory(c.id)"
        >{{ c.name }}</Button>
      </div>

      <!-- Services grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="s in visibleServices"
          :key="s.id"
          class="bg-surface-card rounded-lg shadow-sm overflow-hidden flex flex-col hover:shadow-md transition"
        >
          <!-- Image or placeholder — links to detail page -->
          <Link :href="`/services/${s.id}`" class="block">
            <div
              v-if="s.image_path"
              class="w-full aspect-[16/9] bg-surface-page bg-cover bg-center"
              :style="{ backgroundImage: `url(/storage/${s.image_path})` }"
            />
            <div
              v-else
              :class="['w-full aspect-[16/9] flex items-center justify-center', colorClassForCategory(s.category)]"
            >
              <component :is="iconForCategory(s.category)" class="w-12 h-12 opacity-70" aria-hidden="true" />
            </div>
          </Link>

          <div class="p-4 space-y-2 flex-1 flex flex-col">
            <p class="text-xs text-text-tertiary">{{ s.category?.name }}</p>
            <Link :href="`/services/${s.id}`" class="font-medium text-text-primary hover:text-brand transition">{{ s.name }}</Link>
            <p v-if="s.description" class="text-sm text-text-secondary line-clamp-2">{{ s.description }}</p>
            <div class="flex items-center justify-between pt-2 mt-auto gap-2">
              <div>
                <p class="text-sm font-semibold text-brand">{{ s.base_price }} ₪</p>
                <p class="text-xs text-text-tertiary">{{ s.duration_minutes }} دقيقة</p>
              </div>
              <div class="flex items-center gap-2">
                <Link :href="`/services/${s.id}`" class="text-xs font-bold text-brand hover:underline">التفاصيل</Link>
                <AuthGuardLink
                  intent="booking"
                  :authed-href="`/portal/booking?service=${s.id}`"
                  :context="{ service: s.id }"
                >
                  <Button size="sm">احجز</Button>
                </AuthGuardLink>
              </div>
            </div>
          </div>
        </article>
      </div>

      <p v-if="visibleServices.length === 0" class="text-center text-text-secondary py-6">
        لا توجد خدمات مطابقة.
      </p>
    </div>
  </ClientShell>
</template>
