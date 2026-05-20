<script setup>
import { router } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

defineProps({
  balance: { type: Number, required: true },
  summary: { type: Object, required: true },
  ledger: { type: Object, required: true },
  tab: { type: String, required: true },
})

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}

function setTab(t) {
  router.get('/portal/loyalty', { tab: t === 'all' ? undefined : t }, { preserveScroll: true })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="نقاطي" description="رصيدك وحركات النقاط." />

      <div class="grid grid-cols-2 gap-3">
        <div class="bg-surface-card rounded-lg shadow-sm p-4">
          <p class="text-xs text-text-secondary">الرصيد</p>
          <p class="text-3xl font-bold text-brand mt-1">{{ balance }}</p>
          <p class="text-xs text-text-tertiary mt-1">نقطة</p>
        </div>
        <div class="bg-surface-card rounded-lg shadow-sm p-4">
          <p class="text-xs text-text-secondary">منذ البداية</p>
          <p class="text-sm text-success mt-1">كسبت: +{{ summary.earned }}</p>
          <p class="text-sm text-danger">استبدلت: −{{ summary.redeemed }}</p>
        </div>
      </div>

      <div class="flex gap-2">
        <Button :variant="tab === 'all' ? 'default' : 'outline'" size="sm" @click="setTab('all')">الكل</Button>
        <Button :variant="tab === 'earn' ? 'default' : 'outline'" size="sm" @click="setTab('earn')">كسب</Button>
        <Button :variant="tab === 'redeem' ? 'default' : 'outline'" size="sm" @click="setTab('redeem')">استبدال</Button>
      </div>

      <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
        <li v-if="ledger.data.length === 0" class="p-6 text-center text-text-secondary">لا توجد حركات.</li>
        <li v-for="row in ledger.data" :key="row.id" class="p-3 flex items-center gap-3">
          <span :class="['text-sm font-bold w-14', row.points_delta > 0 ? 'text-success' : 'text-danger']">
            {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
          </span>
          <div class="flex-1 min-w-0">
            <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
            <div class="text-xs text-text-tertiary truncate">{{ row.notes || '—' }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">
            {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
          </div>
        </li>
      </ul>

      <div v-if="ledger.last_page > 1" class="flex justify-center gap-1">
        <Button
          v-for="link in ledger.links" :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        ><span v-html="link.label" /></Button>
      </div>
    </div>
  </ClientShell>
</template>
