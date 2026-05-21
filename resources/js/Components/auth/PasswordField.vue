<script setup>
import { ref } from 'vue'
import { Eye, EyeOff, Lock } from 'lucide-vue-next'
import { Input } from '@/Components/ui/input'

defineProps({
  id: { type: String, required: true },
  label: { type: String, required: true },
  autocomplete: { type: String, default: 'current-password' },
  error: { type: String, default: null },
  hint: { type: String, default: null },
  required: { type: Boolean, default: false },
})

const model = defineModel({ type: String, required: true })
const visible = ref(false)
</script>

<template>
  <div>
    <label :for="id" class="block text-sm font-semibold text-text-primary mb-1.5">
      {{ label }}
      <span v-if="required" class="text-danger" aria-hidden="true">*</span>
    </label>

    <div class="relative">
      <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
        <Lock class="h-4 w-4" aria-hidden="true" />
      </span>

      <Input
        :id="id"
        v-model="model"
        :type="visible ? 'text' : 'password'"
        :autocomplete="autocomplete"
        :aria-invalid="!!error"
        :aria-describedby="error ? `${id}-error` : (hint ? `${id}-hint` : undefined)"
        class="h-11 ps-9 pe-10"
        dir="ltr"
        :required="required"
      />

      <button
        type="button"
        :aria-label="visible ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
        :aria-pressed="visible"
        class="absolute top-1/2 -translate-y-1/2 end-2 inline-flex items-center justify-center h-7 w-7 rounded-md text-text-tertiary hover:text-text-primary hover:bg-surface-page transition focus:outline-none focus:ring-2 focus:ring-brand"
        @click="visible = !visible"
      >
        <component :is="visible ? EyeOff : Eye" class="h-4 w-4" aria-hidden="true" />
      </button>
    </div>

    <p v-if="hint && !error" :id="`${id}-hint`" class="mt-1.5 text-xs text-text-tertiary">{{ hint }}</p>
    <p v-if="error" :id="`${id}-error`" class="mt-1.5 text-xs text-danger font-medium">{{ error }}</p>
  </div>
</template>
