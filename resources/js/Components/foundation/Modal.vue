<script setup>
import { computed } from 'vue'
import { Dialog, DialogScrollContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'

const props = defineProps({
  open: Boolean,
  title: String,
  // 'sm' | 'md' | 'lg' (default — matches existing call-sites) | 'xl' | '2xl'
  size: { type: String, default: 'lg' },
})
defineEmits(['update:open'])

const sizeClass = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
  '2xl': 'max-w-3xl',
}[props.size] ?? 'max-w-lg'))
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open',$event)">
    <!-- DialogScrollContent: overlay scrolls so tall forms never push the footer/submit button off-screen -->
    <DialogScrollContent :class="['z-modal', sizeClass]">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <slot />
      <div class="mt-4 flex justify-end gap-2"><!-- justify-end = flow-relative (RTL-aware), not physical --><slot name="footer" /></div>
    </DialogScrollContent>
  </Dialog>
</template>
