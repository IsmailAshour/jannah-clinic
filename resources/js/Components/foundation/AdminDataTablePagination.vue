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
  <!-- Layout:
       Mobile (< md): three stacked rows — selection count, then pagination
         controls (rows-per-page + page label + arrows wrapping as needed).
       Desktop (≥ md): single row with selection count on the start side and
         all controls grouped at the end.
  -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 px-2 py-4">
    <!-- Selection count — hidden when zero on mobile to save vertical space. -->
    <div
      class="text-xs sm:text-sm text-muted-foreground text-center md:text-start"
      :class="{ 'hidden md:block': table.getFilteredSelectedRowModel().rows.length === 0 }"
    >
      {{ table.getFilteredSelectedRowModel().rows.length }} من
      {{ serverMeta?.total ?? table.getFilteredRowModel().rows.length }} مُحدَّد.
    </div>

    <div class="flex flex-wrap items-center justify-center md:justify-end gap-x-3 gap-y-2 md:gap-6">
      <div v-if="!serverMeta" class="flex items-center gap-1.5 text-xs sm:text-sm">
        <label class="font-medium whitespace-nowrap">صفوف:</label>
        <select
          class="h-8 rounded-md border bg-background px-1.5 text-xs sm:text-sm"
          :value="table.getState().pagination.pageSize"
          @change="(e) => table.setPageSize(Number(e.target.value))"
        >
          <option v-for="n in [10, 20, 30, 50]" :key="n" :value="n">{{ n }}</option>
        </select>
      </div>
      <div class="text-xs sm:text-sm font-medium whitespace-nowrap">
        {{ pageInfo().current }} / {{ pageInfo().last }}
      </div>
      <!--
        Pagination arrows follow reading-direction motion:
        LTR (Western)  → First=<< Prev=< Next=> Last=>>
        RTL (Arabic)   → First=>> Prev=> Next=< Last=<<
        Each button paints both glyphs and toggles visibility on the html[dir] root.
      -->
      <div class="flex items-center gap-0.5 sm:gap-1">
        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="pageInfo().current === 1" @click="go(1)">
          <ChevronsLeft class="size-4 rtl:hidden" /><ChevronsRight class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="pageInfo().current === 1" @click="go(pageInfo().current - 1)">
          <ChevronLeft class="size-4 rtl:hidden" /><ChevronRight class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().current + 1)">
          <ChevronRight class="size-4 rtl:hidden" /><ChevronLeft class="size-4 hidden rtl:block" />
        </Button>
        <Button variant="outline" size="icon" class="h-8 w-8" :disabled="pageInfo().current === pageInfo().last" @click="go(pageInfo().last)">
          <ChevronsRight class="size-4 rtl:hidden" /><ChevronsLeft class="size-4 hidden rtl:block" />
        </Button>
      </div>
    </div>
  </div>
</template>
