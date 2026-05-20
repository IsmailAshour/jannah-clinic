<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="خدماتنا" description="استعرض الخدمات المتاحة." />
    </div>
  </ClientShell>
</template>
