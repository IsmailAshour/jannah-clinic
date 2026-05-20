<script setup>
import { ArrowUpDown, ArrowDown, ArrowUp } from 'lucide-vue-next'
import { Button } from '@/Components/ui/button'

defineProps({
  column: { type: Object, required: true },
  title: { type: String, required: true },
})
</script>

<template>
  <Button
    v-if="column.getCanSort()"
    variant="ghost"
    class="-mx-3 h-8"
    @click="column.toggleSorting(column.getIsSorted() === 'asc')"
  >
    {{ title }}
    <ArrowUp v-if="column.getIsSorted() === 'asc'" class="size-4 ms-2" />
    <ArrowDown v-else-if="column.getIsSorted() === 'desc'" class="size-4 ms-2" />
    <ArrowUpDown v-else class="size-4 ms-2 opacity-50" />
  </Button>
  <span v-else>{{ title }}</span>
</template>
