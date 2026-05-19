<script setup>
defineProps({ label:String, name:String, error:{type:String,default:''}, hint:{type:String,default:''}, required:Boolean })
</script>
<template>
  <div class="space-y-2">
    <label :for="name" class="block text-sm font-medium text-text-primary">{{ label }}<span v-if="required" class="text-danger"> *</span></label>
    <slot />
    <p v-if="hint && !error" class="text-xs text-text-tertiary">{{ hint }}</p>
    <p v-if="error" class="text-xs text-danger">{{ error }}</p>
  </div>
</template>
