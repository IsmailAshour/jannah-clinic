<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  faqs: { type: Array, default: () => [] },
  contact: { type: Object, default: () => ({}) },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الدعم" description="نحن هنا لمساعدتك." />
    </div>
  </ClientShell>
</template>
