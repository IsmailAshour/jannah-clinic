<script setup>
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  customer: { type: Object, required: true },
  balance: { type: Number, required: true },
  ledger: { type: Object, required: true },
})
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader :title="`نقاط ولاء — ${customer.name}`" :description="`الرصيد الحالي: ${balance}`" />
    </div>
  </AdminShell>
</template>
