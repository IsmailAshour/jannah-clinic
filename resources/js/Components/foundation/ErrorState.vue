<script setup>
defineProps({ message:{type:String,default:'حدث خطأ غير متوقع.'} })
</script>
<template>
  <div role="alert" class="flex flex-col items-center justify-center text-center py-16 px-6">
    <p class="text-danger font-medium">{{ message }}</p>
    <div class="mt-4"><slot name="action" /></div>
  </div>
</template>
