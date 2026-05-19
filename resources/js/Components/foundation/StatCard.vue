<script setup>
defineProps({ title:String, value:[String,Number], trend:{type:String,default:''} })
</script>
<template>
  <div class="bg-surface-card rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] p-6">
    <p class="text-sm text-text-secondary">{{ title }}</p>
    <p class="text-3xl font-bold text-text-primary mt-2">{{ value }}</p>
    <p v-if="trend" class="text-xs text-success mt-1">{{ trend }}</p>
  </div>
</template>
