<script setup>
import {
  DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent,
  DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Settings2 } from 'lucide-vue-next'

defineProps({ table: { type: Object, required: true } })
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="outline" size="sm" class="h-9">
        <Settings2 class="size-4 me-2" /> أعمدة
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[180px]">
      <DropdownMenuLabel>إظهار/إخفاء</DropdownMenuLabel>
      <DropdownMenuSeparator />
      <DropdownMenuCheckboxItem
        v-for="col in table.getAllColumns().filter((c) => c.getCanHide())"
        :key="col.id"
        :model-value="col.getIsVisible()"
        @update:model-value="(v) => col.toggleVisibility(!!v)"
      >
        {{ col.columnDef.meta?.label ?? col.id }}
      </DropdownMenuCheckboxItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
