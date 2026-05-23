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

// Mobile-card classifier: picks one "primary" cell (the heading), one
// "actions" cell (rendered at the row's start in the card), one optional
// "select" checkbox cell, and everything else becomes label/value pairs.
// Consumers can hint via column meta:
//   meta.primary: true   → forces this column to be the card heading
//   meta.hideOnMobile: true → drops the column from the card entirely
function isActions(col) {
  return col.id === 'actions'
}
function isSelect(col) {
  return col.id === 'select'
}
function primaryCell(row) {
  const cells = row.getVisibleCells()
  // 1. explicit opt-in via meta.primary
  const explicit = cells.find((c) => c.column.columnDef.meta?.primary)
  if (explicit) return explicit
  // 2. fallback: first visible non-select, non-actions cell
  return cells.find((c) => !isActions(c.column) && !isSelect(c.column)) ?? null
}
function actionsCell(row) {
  return row.getVisibleCells().find((c) => isActions(c.column)) ?? null
}
function secondaryCells(row) {
  const primaryId = primaryCell(row)?.id
  return row.getVisibleCells().filter((c) => {
    if (c.id === primaryId) return false
    if (isActions(c.column)) return false
    if (isSelect(c.column)) return false
    if (c.column.columnDef.meta?.hideOnMobile) return false
    return true
  })
}

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
    <!-- Mobile card-view (< md). Each row becomes a stacked card with the
         primary column as the heading, actions in the top-end corner, and
         remaining columns as compact label/value pairs. -->
    <div class="md:hidden space-y-2">
      <template v-if="table.getRowModel().rows?.length">
        <div
          v-for="row in table.getRowModel().rows"
          :key="row.id"
          :data-state="row.getIsSelected() ? 'selected' : undefined"
          class="rounded-lg border border-border-default bg-surface-card p-3 space-y-2"
        >
          <!-- Heading row: primary cell + actions -->
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0 text-sm font-bold text-text-primary">
              <FlexRender
                v-if="primaryCell(row)"
                :render="primaryCell(row).column.columnDef.cell"
                :props="primaryCell(row).getContext()"
              />
            </div>
            <div v-if="actionsCell(row)" class="shrink-0">
              <FlexRender
                :render="actionsCell(row).column.columnDef.cell"
                :props="actionsCell(row).getContext()"
              />
            </div>
          </div>
          <!-- Label/value pairs for the remaining columns -->
          <dl v-if="secondaryCells(row).length" class="space-y-1 text-xs border-t border-border-default pt-2">
            <div
              v-for="cell in secondaryCells(row)"
              :key="cell.id"
              class="flex items-baseline justify-between gap-3"
            >
              <dt class="text-text-tertiary shrink-0">{{ cell.column.columnDef.meta?.label ?? '' }}</dt>
              <dd class="text-text-primary text-end min-w-0 truncate">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </dd>
            </div>
          </dl>
        </div>
      </template>
      <template v-else>
        <div class="rounded-lg border border-border-default bg-surface-card p-6 text-center text-text-tertiary text-sm">
          {{ emptyText }}
        </div>
      </template>
    </div>

    <!-- Desktop table (≥ md). Horizontal scroll kept as a safety net in case
         a single cell is unusually wide. -->
    <div class="hidden md:block border rounded-md overflow-x-auto">
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
