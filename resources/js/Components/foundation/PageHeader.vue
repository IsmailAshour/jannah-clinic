<script setup>
defineProps({ title:String, eyebrow:{type:String,default:''}, description:{type:String,default:''} })
</script>
<template>
  <header class="flex items-start justify-between gap-4 pb-6">
    <div class="min-w-0">
      <p v-if="eyebrow" class="text-xs font-medium text-text-tertiary">{{ eyebrow }}</p>
      <h1 class="text-2xl font-bold text-text-primary">{{ title }}</h1>
      <p v-if="description" class="text-sm text-text-secondary mt-1">{{ description }}</p>
    </div>
    <div class="shrink-0"><slot name="action" /></div>
  </header>
</template>
