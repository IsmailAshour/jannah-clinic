<script setup>
import { ref } from 'vue'
import {
  FlexRender, getCoreRowModel, getFilteredRowModel, getPaginationRowModel,
  getSortedRowModel, useVueTable,
} from '@tanstack/vue-table'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table'
import { valueUpdater } from '@/Components/ui/table/utils'
import AdminDataTablePagination from './AdminDataTablePagination.vue'
import AdminDataTableViewOptions from './AdminDataTableViewOptions.vue'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  columns: { type: Array, required: true },
  data: { type: Array, required: true },
  filterColumn: { type: String, default: null },
  filterPlaceholder: { type: String, default: 'بحث…' },
  emptyText: { type: String, default: 'لا توجد سجلات.' },
  serverMeta: { type: Object, default: null },
  onPageChange: { type: Function, default: null },
})

const sorting = ref([])
const columnFilters = ref([])
const columnVisibility = ref({})
const rowSelection = ref({})

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: props.serverMeta ? undefined : getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: (u) => valueUpdater(u, sorting),
  onColumnFiltersChange: (u) => valueUpdater(u, columnFilters),
  onColumnVisibilityChange: (u) => valueUpdater(u, columnVisibility),
  onRowSelectionChange: (u) => valueUpdater(u, rowSelection),
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
})

defineExpose({ table })
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center py-4 gap-2">
      <slot name="toolbar" :table="table">
        <Input
          v-if="filterColumn"
          class="max-w-sm h-9"
          :placeholder="filterPlaceholder"
          :model-value="table.getColumn(filterColumn)?.getFilterValue() ?? ''"
          @update:model-value="(v) => table.getColumn(filterColumn)?.setFilterValue(v)"
        />
        <AdminDataTableViewOptions :table="table" class="ms-auto" />
      </slot>
    </div>
    <!-- Horizontal scroll on small screens so tables with many columns don't
         force the whole admin page to overflow viewport width. -->
    <div class="border rounded-md overflow-x-auto">
      <Table>
        <TableHeader>
          <TableRow v-for="hg in table.getHeaderGroups()" :key="hg.id">
            <TableHead
              v-for="h in hg.headers"
              :key="h.id"
              :class="['text-center', h.column.columnDef.meta?.headerClass]"
            >
              <FlexRender v-if="!h.isPlaceholder" :render="h.column.columnDef.header" :props="h.getContext()" />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="table.getRowModel().rows?.length">
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
            >
              <TableCell
                v-for="cell in row.getVisibleCells()"
                :key="cell.id"
                :class="cell.column.columnDef.meta?.cellClass"
              >
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <template v-else>
            <TableRow>
              <TableCell :colSpan="columns.length" class="h-24 text-center">{{ emptyText }}</TableCell>
            </TableRow>
          </template>
        </TableBody>
      </Table>
    </div>
    <AdminDataTablePagination :table="table" :server-meta="serverMeta" :on-page-change="onPageChange" />
  </div>
</template>
