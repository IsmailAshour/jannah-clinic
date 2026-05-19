<script setup>
import { computed } from 'vue'
const props = defineProps({
  label: String,
  name: { type: String, required: true },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: Boolean,
})
// Consumers MUST bind :aria-describedby="describedby" (and :id/:name) on the
// field placed in the default slot, so screen readers announce hint/error.
const hintId = computed(() => `${props.name}-hint`)
const errorId = computed(() => `${props.name}-error`)
const describedby = computed(() => props.error ? errorId.value : (props.hint ? hintId.value : undefined))
</script>
<template>
  <div class="space-y-2">
    <label :for="name" class="block text-sm font-medium text-text-primary">{{ label }}<span v-if="required" class="text-danger"> *</span></label>
    <slot :describedby="describedby" :error-id="errorId" :hint-id="hintId" />
    <p v-if="hint && !error" :id="hintId" class="text-xs text-text-tertiary">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
