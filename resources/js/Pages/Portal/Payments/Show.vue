<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Upload, Copy } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup, FormSection, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  appointment: { type: Object, required: true },
  payment: { type: Object, required: true },
  bank: { type: Object, required: true },
})

const statusMap = {
  pending:        { label: 'بانتظار الدفع',    variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',   variant: 'info'    },
  paid:           { label: 'مدفوع',             variant: 'success' },
  rejected:       { label: 'مرفوض',             variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded:       { label: 'مُسترَد',           variant: 'info'    },
}

const canUpload = computed(() => ['pending', 'rejected'].includes(props.payment.status))

const form = useForm({ receipt: null })
const fileError = computed(() => form.errors.receipt ?? null)

function pickFile(e) {
  form.receipt = e.target.files[0] ?? null
}

function submit() {
  if (!form.receipt) return
  form.post(`/portal/appointments/${props.appointment.id}/payment/upload`, {
    forceFormData: true,
    onSuccess: () => { form.reset('receipt') },
  })
}

const copied = ref(false)
async function copyIban() {
  if (!props.bank.iban) return
  try {
    await navigator.clipboard.writeText(props.bank.iban)
    copied.value = true
    setTimeout(() => (copied.value = false), 2000)
  } catch {
    // clipboard blocked — user can still select manually
  }
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-6">
      <PageHeader title="دفع الموعد">
        <template #subtitle>
          {{ appointment.service?.name }} — د. {{ appointment.doctor?.user?.name }}
        </template>
      </PageHeader>

      <!-- Current status -->
      <FormSection title="حالة الدفع">
        <div class="flex items-center gap-3 flex-wrap">
          <StatusBadge
            :type="statusMap[payment.status]?.variant ?? 'info'"
            :label="statusMap[payment.status]?.label ?? payment.status"
          />
          <div class="text-2xl font-bold">{{ payment.amount }} ₪</div>
        </div>
        <p v-if="payment.status === 'rejected' && payment.rejection_reason"
           class="mt-3 rounded border border-danger bg-danger/10 p-3 text-sm text-text-primary">
          <span class="font-semibold">سبب الرفض: </span>{{ payment.rejection_reason }}
        </p>
        <p v-if="payment.status === 'paid' && payment.verified_at" class="mt-3 text-sm text-text-secondary">
          تم التحقّق في {{ formatDate(payment.verified_at) }}.
        </p>
        <p v-if="payment.status === 'refunded' && payment.refund_reference" class="mt-3 text-sm text-text-secondary">
          مرجع الاسترداد: <span dir="ltr" class="font-mono">{{ payment.refund_reference }}</span>
        </p>
      </FormSection>

      <!-- Bank info -->
      <FormSection title="بيانات الحساب البنكي" description="حوّل المبلغ ثم ارفع صورة وصل التحويل.">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-text-secondary">البنك</dt>
            <dd class="text-text-primary">{{ bank.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">اسم الحساب</dt>
            <dd class="text-text-primary">{{ bank.account_holder || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">رقم الحساب</dt>
            <dd dir="ltr" class="text-text-primary font-mono">{{ bank.account_number || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">IBAN</dt>
            <dd class="flex items-center gap-2 flex-wrap">
              <span dir="ltr" class="text-text-primary font-mono">{{ bank.iban || '—' }}</span>
              <Button v-if="bank.iban" size="sm" variant="outline" @click="copyIban">
                <Copy class="h-4 w-4" /> {{ copied ? 'تم النسخ' : 'نسخ' }}
              </Button>
            </dd>
          </div>
        </dl>
      </FormSection>

      <!-- Upload (only when actionable) -->
      <FormSection v-if="canUpload" title="رفع إيصال التحويل">
        <FormGroup label="ملف الإيصال (JPG/PNG/PDF — الحد الأقصى 5 ميغابايت)" name="receipt" :error="fileError" required>
          <template #default="{ describedby }">
            <input
              id="receipt"
              type="file"
              name="receipt"
              accept="image/jpeg,image/png,application/pdf"
              :aria-describedby="describedby"
              @change="pickFile"
            />
          </template>
        </FormGroup>
        <div class="mt-4">
          <Button :disabled="!form.receipt || form.processing" @click="submit">
            <Upload class="h-4 w-4" />
            <span>{{ payment.status === 'rejected' ? 'إعادة الرفع' : 'رفع الإيصال' }}</span>
          </Button>
        </div>
      </FormSection>

      <!-- Submitted: user sees what they sent -->
      <FormSection v-else-if="payment.status === 'submitted' && payment.receipts?.[0]" title="إيصالك المُرفَع">
        <p class="text-sm text-text-secondary mb-2">إيصالك بانتظار التحقّق من قبل الإدارة.</p>
        <p class="text-xs text-text-secondary">
          رُفع في {{ formatDate(payment.receipts[0].created_at) }} —
          {{ payment.receipts[0].mime_type }} —
          {{ (payment.receipts[0].file_size / 1024).toFixed(1) }} KB
        </p>
      </FormSection>
    </div>
  </ClientShell>
</template>
