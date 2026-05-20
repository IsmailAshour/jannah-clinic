<script setup>
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
defineProps({
  feed: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="الإشعارات" description="مركز الإشعارات." />
    </div>
  </AdminShell>
</template>
