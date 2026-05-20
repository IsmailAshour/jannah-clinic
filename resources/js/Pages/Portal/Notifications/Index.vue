<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  feed: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الإشعارات" description="مركز الإشعارات." />
    </div>
  </ClientShell>
</template>
