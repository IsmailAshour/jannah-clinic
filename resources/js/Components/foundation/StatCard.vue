<script setup>
defineProps({
  title: String,
  value: [String, Number],
  trend: { type: String, default: '' },
  trendDirection: { type: String, default: 'neutral' }, // 'up' | 'down' | 'neutral'
})
const trendClass = {
  up: 'text-success',
  down: 'text-danger',
  neutral: 'text-text-tertiary',
}
</script>
<template>
  <div class="bg-surface-card rounded-lg shadow-sm p-6">
    <p class="text-sm text-text-secondary">{{ title }}</p>
    <p class="text-3xl font-bold text-text-primary mt-2">{{ value }}</p>
    <p v-if="trend" :class="['text-xs mt-1', trendClass[trendDirection] || trendClass.neutral]">{{ trend }}</p>
  </div>
</template>
