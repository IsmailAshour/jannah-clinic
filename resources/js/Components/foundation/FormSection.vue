<script setup>
defineProps({ title:String, description:{type:String,default:''} })
</script>
<template>
  <section class="bg-surface-card rounded-[var(--radius-lg)] shadow-[var(--shadow-sm)] p-6 space-y-4">
    <div><h2 class="text-lg font-semibold text-text-primary">{{ title }}</h2><p v-if="description" class="text-sm text-text-secondary">{{ description }}</p></div>
    <div class="space-y-4"><slot /></div>
  </section>
</template>
