<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Eye } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, DataTable, PageStates, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  payments: { type: Object, default: () => ({ data: [], links: [] }) },
  filters: { type: Object, default: () => ({}) },
})

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? 'submitted')

function applyFilters() {
  router.get('/admin/payments', {
    q: q.value || undefined,
    status: status.value || undefined,
  }, { preserveScroll: true, replace: true })
}
function resetFilters() {
  q.value = ''
  status.value = 'submitted'
  applyFilters()
}

const statusMap = {
  pending:        { label: 'بانتظار الدفع',    variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',   variant: 'info'    },
  paid:           { label: 'مدفوع',             variant: 'success' },
  rejected:       { label: 'مرفوض',             variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded:       { label: 'مُسترَد',           variant: 'info'    },
}

const columns = [
  { key: 'customer', label: 'العميل' },
  { key: 'service',  label: 'الخدمة' },
  { key: 'doctor',   label: 'الطبيب' },
  { key: 'amount',   label: 'المبلغ' },
  { key: 'status',   label: 'الحالة' },
  { key: 'actions',  label: 'إجراءات', align: 'end' },
]
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="المدفوعات" />

      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (اسم/بريد/هاتف العميل)</label>
          <Input id="q" v-model="q" name="q" placeholder="ابحث..." class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            id="status"
            v-model="status"
            name="status"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="all">الكل</option>
            <option value="submitted">بانتظار التحقّق</option>
            <option value="pending">بانتظار الدفع</option>
            <option value="paid">مدفوع</option>
            <option value="rejected">مرفوض</option>
            <option value="refund_pending">بانتظار الاسترداد</option>
            <option value="refunded">مُسترَد</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <PageStates :is-empty="payments.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا مدفوعات تطابق الفلتر.</div>
        </template>
        <DataTable :columns="columns" :rows="payments.data">
          <template #cell-customer="{ row }">{{ row.appointment?.customer?.name ?? '—' }}</template>
          <template #cell-service="{ row }">{{ row.appointment?.service?.name ?? '—' }}</template>
          <template #cell-doctor="{ row }">{{ row.appointment?.doctor?.user?.name ?? '—' }}</template>
          <template #cell-amount="{ row }">{{ row.amount }} ₪</template>
          <template #cell-status="{ row }">
            <StatusBadge :type="statusMap[row.status]?.variant ?? 'info'" :label="statusMap[row.status]?.label ?? row.status" />
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Link :href="`/admin/payments/${row.id}`" class="inline-flex items-center gap-1 text-sm text-brand hover:underline">
                <Eye class="h-4 w-4" aria-hidden="true" />
                <span>عرض</span>
              </Link>
            </div>
          </template>
        </DataTable>

        <div v-if="payments.links && payments.links.length > 3" class="mt-4 flex gap-1 justify-center">
          <template v-for="link in payments.links" :key="link.label">
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
