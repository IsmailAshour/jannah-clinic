<script setup>
const map={success:'bg-success/10 text-success',warning:'bg-warning/10 text-warning',danger:'bg-danger/10 text-danger',info:'bg-info/10 text-info'}
defineProps({ type:{type:String,default:'info'}, label:String })
</script>
<template>
  <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', map[type]||map.info]">{{ label }}<slot /></span>
</template>
