<script setup>
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  balance: { type: Number, required: true },
  summary: { type: Object, required: true },
  ledger: { type: Object, required: true },
  tab: { type: String, required: true },
})
</script>
<template>
  <ClientShell>
    <div class="p-4">
      <PageHeader title="نقاطي" :description="`الرصيد: ${balance}`" />
    </div>
  </ClientShell>
</template>
