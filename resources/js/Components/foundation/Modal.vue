<script setup>
import { Dialog, DialogScrollContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
defineProps({ open:Boolean, title:String })
defineEmits(['update:open'])
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open',$event)">
    <!-- DialogScrollContent: overlay scrolls so tall forms never push the footer/submit button off-screen -->
    <DialogScrollContent class="z-modal">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <slot />
      <div class="mt-4 flex justify-end gap-2"><!-- justify-end = flow-relative (RTL-aware), not physical --><slot name="footer" /></div>
    </DialogScrollContent>
  </Dialog>
</template>
