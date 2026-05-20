<script setup>
import { router } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

defineProps({
  customer: { type: Object, required: true },
  balance: { type: Number, required: true },
  ledger: { type: Object, required: true },
})

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6">
      <PageHeader :title="`نقاط ولاء — ${customer.name}`" :description="`الرصيد الحالي: ${balance}`">
        <template #action>
          <Button variant="outline" @click="router.visit(`/admin/customers/${customer.id}`)">
            عودة لصفحة العميل
          </Button>
        </template>
      </PageHeader>

      <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
        <li v-if="ledger.data.length === 0" class="p-6 text-center text-text-secondary">
          لا توجد حركات.
        </li>
        <li v-for="row in ledger.data" :key="row.id" class="p-4 flex items-center gap-3">
          <span :class="['text-sm font-bold w-20', row.points_delta > 0 ? 'text-success' : 'text-danger']">
            {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
            <div class="text-xs text-text-tertiary truncate">{{ row.notes || '—' }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            رصيد: {{ row.balance_after }}
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
          </div>
        </li>
      </ul>

      <div v-if="ledger.last_page > 1" class="flex justify-center gap-2">
        <Button
          v-for="link in ledger.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        ><span v-html="link.label" /></Button>
      </div>
    </div>
  </AdminShell>
</template>
