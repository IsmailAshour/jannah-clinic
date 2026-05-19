<script setup>
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
defineProps({ open:Boolean, title:String })
defineEmits(['update:open'])
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open',$event)">
    <DialogContent class="z-modal">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <slot />
      <div class="mt-4 flex justify-end gap-2"><slot name="footer" /></div>
    </DialogContent>
  </Dialog>
</template>
