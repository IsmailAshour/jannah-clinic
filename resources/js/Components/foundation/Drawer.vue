<script setup>
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet'
defineProps({ open:Boolean, title:String })
defineEmits(['update:open'])
</script>
<template>
  <Sheet :open="open" @update:open="$emit('update:open',$event)">
    <SheetContent side="left" class="z-modal w-full max-w-md">
      <SheetHeader><SheetTitle>{{ title }}</SheetTitle></SheetHeader>
      <slot />
    </SheetContent>
  </Sheet>
</template>
