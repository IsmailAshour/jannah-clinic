<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  doctors: { type: Array, default: () => [] },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="الأطبّاء" description="فريقنا الطبّي." />
    </div>
  </ClientShell>
</template>
