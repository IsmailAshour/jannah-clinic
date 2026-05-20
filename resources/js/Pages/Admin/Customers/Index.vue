<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Eye } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  DataTable,
  PageStates,
  StatusBadge,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  customers: { type: Object, default: () => ({ data: [], links: [] }) },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? '')

function applyFilters() {
  router.get(
    '/admin/customers',
    {
      q: q.value || undefined,
      status: status.value || undefined,
    },
    { preserveScroll: true, replace: true }
  )
}

function resetFilters() {
  q.value = ''
  status.value = ''
  applyFilters()
}

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'phone', label: 'الهاتف' },
  { key: 'email', label: 'البريد' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="العملاء" />

      <!-- Filter bar -->
      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (الاسم، البريد، الهاتف)</label>
          <Input id="q" v-model="q" name="q" placeholder="ابحث..." class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            id="status"
            v-model="status"
            name="status"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <PageStates :is-empty="customers.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا يوجد عملاء.</div>
        </template>

        <DataTable :columns="columns" :rows="customers.data">
          <template #cell-name="{ row }">{{ row.name }}</template>
          <template #cell-phone="{ row }">{{ row.phone || '—' }}</template>
          <template #cell-email="{ row }">{{ row.email || '—' }}</template>
          <template #cell-status="{ row }">
            <StatusBadge
              :type="row.is_active ? 'success' : 'danger'"
              :label="row.is_active ? 'نشط' : 'غير نشط'"
            />
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Link :href="`/admin/customers/${row.id}`" class="inline-flex items-center gap-1 text-sm text-brand hover:underline">
                <Eye class="h-4 w-4" aria-hidden="true" />
                <span>عرض</span>
              </Link>
            </div>
          </template>
        </DataTable>

        <!-- Pagination links -->
        <div v-if="customers.links && customers.links.length > 3" class="mt-4 flex gap-1 justify-center">
          <template v-for="link in customers.links" :key="link.label">
            <component
              :is="link.url ? 'a' : 'span'"
              :href="link.url ?? undefined"
              class="px-3 py-1 text-sm rounded-md border border-border-default"
              :class="link.active ? 'bg-brand text-white' : 'bg-surface-card text-text-secondary hover:bg-surface-sunken'"
              v-html="link.label"
            />
          </template>
        </div>
      </PageStates>
    </div>
  </AdminShell>
</template>
