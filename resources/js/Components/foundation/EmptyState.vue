<script setup>
defineProps({ title:String, description:{type:String,default:''} })
</script>
<template>
  <div role="status" class="flex flex-col items-center justify-center text-center py-16 px-6">
    <h3 class="text-lg font-semibold text-text-primary">{{ title }}</h3>
    <p v-if="description" class="text-sm text-text-secondary mt-1">{{ description }}</p>
    <div class="mt-4"><slot name="action" /></div>
  </div>
</template>
