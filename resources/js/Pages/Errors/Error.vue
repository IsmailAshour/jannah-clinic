<script setup>
import { computed } from 'vue'
import { ErrorState } from '@/Components/foundation'
const props = defineProps({ status: { type: Number, default: 500 } })
const messages = {
  403: 'ليس لديك صلاحية للوصول إلى هذه الصفحة.',
  404: 'الصفحة غير موجودة.',
  500: 'حدث خطأ في الخادم. حاول لاحقًا.',
  503: 'الخدمة غير متاحة مؤقتًا.',
}
const message = computed(() => messages[props.status] ?? messages[500])
</script>
<template>
  <div class="min-h-screen grid place-items-center bg-surface-page p-6">
    <ErrorState :message="message" />
  </div>
</template>
