<script setup>
import { Button } from '@/Components/ui/button'
import { ChevronsRight, ChevronsLeft, ChevronRight, ChevronLeft } from 'lucide-vue-next'

const props = defineProps({
  table: { type: Object, required: true },
  serverMeta: { type: Object, default: null },
  onPageChange: { type: Function, default: null },
})

function go(page) {
  if (props.serverMeta && props.onPageChange) {
    props.onPageChange(page)
  } else {
    props.table.setPageIndex(page - 1)
  }
}

function pageInfo() {
  if (props.serverMeta) {
    return { current: props.serverMeta.current_page, last: props.serverMeta.last_page }
  }
  return {
    current: props.table.getState().pagination.pageIndex + 1,
    last: Math.max(1, props.table.getPageCount()),
  }
}
</script>

<template>
  <div class="flex items-center justify-between px-2 py-4">
    <div class="flex-1 text-sm text-muted-foreground">
      {{ table.getFilteredSelectedRowModel().rows.length }} من
      {{ serverMeta?.total ?? table.getFilteredRowModel().rows.length }} مُحدَّد.
    </div>
    <div class="flex items-center gap-6">
      <div class="flex items-center gap-2" v-if="!serverMeta">
        <label class="text-sm font-medium">صفوف لكل صفحة</label>
        <select
          class="h-8 rounded-md border bg-background px-2 text-sm"
          :value="table.getState().pagination.pageSize"
          @change="(e) => table.setPageSize(Number(e.target.value))"
        >
          <option v-for="n in [10, 20, 30, 50]" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <div class="text-sm font-medium">صفحة {{ pageInfo().current }} من {{ pageInfo().last }}</div>
      <!--
        Pagination arrows follow reading-direction motion:
        LTR (Western)  → First=<< Prev=< Next=> Last=>>
        RTL (Arabic)   → First=>> Prev=> Next=< Last=<<
        Each button paints both glyphs and toggles visibility on the html[dir] root.
      -->
      <div class="flex items-center gap-1">
        <Button variant="outline" size="icon" :disabled="pageInfo().current === 1" @click="go(1)">
          <ChevronsLeft class="size-4 rtl:hidden" /><ChevronsRight class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === 1" @click="go(pageInfo().current - 1)">
          <ChevronLeft class="size-4 rtl:hidden" /><ChevronRight class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().current + 1)">
          <ChevronRight class="size-4 rtl:hidden" /><ChevronLeft class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().last)">
          <ChevronsRight class="size-4 rtl:hidden" /><ChevronsLeft class="size-4 hidden rtl:block" />
        </Button>
      </div>
    </div>
  </div>
</template>
