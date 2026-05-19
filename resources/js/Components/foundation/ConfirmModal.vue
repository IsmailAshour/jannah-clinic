<script setup>
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
defineProps({ open:Boolean, title:{type:String,default:'تأكيد'}, message:String, confirmText:{type:String,default:'تأكيد'}, cancelText:{type:String,default:'إلغاء'} })
const emit=defineEmits(['update:open','confirm'])
</script>
<template>
  <Dialog :open="open" @update:open="$emit('update:open',$event)">
    <DialogContent class="z-modal" role="alertdialog">
      <DialogHeader><DialogTitle>{{ title }}</DialogTitle></DialogHeader>
      <p class="text-sm text-text-secondary">{{ message }}</p>
      <div class="mt-4 flex justify-end gap-2"><!-- justify-end is RTL-aware (flexbox flow-relative) -->
        <Button variant="outline" @click="emit('update:open',false)">{{ cancelText }}</Button>
        <Button class="bg-danger text-white" @click="emit('confirm'); emit('update:open',false)">{{ confirmText }}</Button>
      </div>
    </DialogContent>
  </Dialog>
</template>
