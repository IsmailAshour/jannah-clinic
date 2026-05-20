<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  featuredServices: { type: Array, default: () => [] },
  featuredDoctor: { type: Object, default: null },
  tip: { type: String, default: null },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="عيادة جنّة" description="نهتمّ بصحّتك وجمالك." />
    </div>
  </ClientShell>
</template>
