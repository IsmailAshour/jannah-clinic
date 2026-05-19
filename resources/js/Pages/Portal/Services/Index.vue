<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, PageStates } from '@/Components/foundation'

const props = defineProps({ categories: { type: Array, default: () => [] } })

const hasServices = props.categories.some(c => c.services && c.services.length > 0)
</script>

<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="خدماتنا" />

      <PageStates :is-empty="!hasServices">
        <template #empty>
          <div class="text-text-secondary p-6">لا توجد خدمات متاحة حالياً.</div>
        </template>

        <div class="space-y-8">
          <section v-for="cat in categories" :key="cat.id" v-show="cat.services && cat.services.length > 0">
            <h2 class="mb-3 text-lg font-semibold text-text-primary">{{ cat.name }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
              <div
                v-for="service in cat.services"
                :key="service.id"
                class="rounded-lg border border-border-default bg-surface-card p-4 space-y-1"
              >
                <p class="font-medium text-text-primary">{{ service.name }}</p>
                <p class="text-sm text-text-secondary">{{ service.base_price }} ₪</p>
                <p class="text-xs text-text-tertiary">{{ service.duration_minutes }} دقيقة</p>
              </div>
            </div>
          </section>
        </div>
      </PageStates>
    </div>
  </ClientShell>
</template>
