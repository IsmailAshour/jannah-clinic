<script setup>
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet'
defineProps({
  open: Boolean,
  title: String,
  // RTL-only app: default to the start edge (visually right in RTL).
  // shadcn Sheet `side` is physical (no logical start/end); 'right' = RTL start.
  side: { type: String, default: 'right' },
})
defineEmits(['update:open'])
</script>
<template>
  <Sheet :open="open" @update:open="$emit('update:open',$event)">
    <SheetContent :side="side" class="z-modal w-full max-w-md">
      <SheetHeader><SheetTitle>{{ title }}</SheetTitle></SheetHeader>
      <slot />
    </SheetContent>
  </Sheet>
</template>
