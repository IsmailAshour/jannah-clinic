<script setup>
defineProps({
  loading: { type: Boolean, default: false },
  error: { type: [String, null], default: null },
  isEmpty: { type: Boolean, default: false },
})
</script>
<template>
  <div role="region" aria-live="polite">
    <slot v-if="loading" name="loading"><div class="animate-pulse text-text-tertiary p-6">جارٍ التحميل…</div></slot>
    <slot v-else-if="error" name="error" :message="error"><div class="text-danger p-6">{{ error }}</div></slot>
    <slot v-else-if="isEmpty" name="empty"><div class="text-text-secondary p-6">لا توجد بيانات.</div></slot>
    <slot v-else />
  </div>
</template>
