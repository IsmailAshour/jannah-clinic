<script setup>
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Check, X, RotateCcw, BadgeCheck } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, FormSection, StatusBadge, Modal, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({ payment: { type: Object, required: true } })

const isManager = (() => usePage().props?.auth?.user?.role === 'manager')()

const statusMap = {
  pending:        { label: 'بانتظار الدفع',    variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',   variant: 'info'    },
  paid:           { label: 'مدفوع',             variant: 'success' },
  rejected:       { label: 'مرفوض',             variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد', variant: 'warning' },
  refunded:       { label: 'مُسترَد',           variant: 'info'    },
}

// Latest receipt = first element (controller orders DESC by id).
const currentReceipt = computed(() => props.payment.receipts?.[0] ?? null)
const isImage = computed(() => currentReceipt.value?.mime_type?.startsWith('image/') ?? false)
const isPdf = computed(() => currentReceipt.value?.mime_type === 'application/pdf')
const receiptUrl = computed(() =>
  currentReceipt.value
    ? `/admin/payments/${props.payment.id}/receipts/${currentReceipt.value.id}/file`
    : null,
)

function verify() {
  router.post(`/admin/payments/${props.payment.id}/verify`, {}, { preserveScroll: true })
}

const showReject = ref(false)
const rejectForm = useForm({ reason: '' })
function submitReject() {
  rejectForm.post(`/admin/payments/${props.payment.id}/reject`, {
    preserveScroll: true,
    onSuccess: () => { showReject.value = false; rejectForm.reset('reason') },
  })
}

function markRefundPending() {
  router.post(`/admin/payments/${props.payment.id}/mark-refund-pending`, {}, { preserveScroll: true })
}

const showRefunded = ref(false)
const refundedForm = useForm({ reference: '' })
function submitRefunded() {
  refundedForm.post(`/admin/payments/${props.payment.id}/mark-refunded`, {
    preserveScroll: true,
    onSuccess: () => { showRefunded.value = false; refundedForm.reset('reference') },
  })
}

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader :title="`دفعة #${payment.id}`">
        <template #action>
          <div v-if="isManager" class="flex gap-2 flex-wrap">
            <Button v-if="payment.status === 'submitted'" @click="verify">
              <Check class="h-4 w-4" />
              <span>تحقّق</span>
            </Button>
            <Button v-if="payment.status === 'submitted'" variant="outline" class="text-danger" @click="showReject = true">
              <X class="h-4 w-4" />
              <span>رفض</span>
            </Button>
            <Button v-if="payment.status === 'paid' && payment.appointment?.status !== 'completed'" variant="outline" @click="markRefundPending">
              <RotateCcw class="h-4 w-4" />
              <span>وَسِم للاسترداد</span>
            </Button>
            <Button v-if="payment.status === 'refund_pending'" @click="showRefunded = true">
              <BadgeCheck class="h-4 w-4" />
              <span>سجّل تنفيذ الاسترداد</span>
            </Button>
          </div>
        </template>
      </PageHeader>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Receipt preview (the main job of this page) -->
        <div class="lg:col-span-2 space-y-4">
          <FormSection title="الإيصال الحالي">
            <div v-if="!currentReceipt" class="text-text-secondary text-sm p-4 text-center">
              لم يُرفع أي إيصال بعد.
            </div>
            <div v-else>
              <img
                v-if="isImage"
                :src="receiptUrl"
                alt="إيصال التحويل"
                class="max-h-[70vh] mx-auto rounded border border-border-default"
              />
              <iframe
                v-else-if="isPdf"
                :src="receiptUrl"
                class="w-full h-[70vh] rounded border border-border-default"
                title="إيصال التحويل (PDF)"
              />
              <a v-else :href="receiptUrl" target="_blank" class="text-brand underline text-sm">
                فتح الملف في تبويب جديد
              </a>
              <p class="mt-2 text-xs text-text-secondary">
                رفعه {{ currentReceipt.uploader?.name ?? '—' }} —
                {{ currentReceipt.mime_type }} —
                {{ (currentReceipt.file_size / 1024).toFixed(1) }} KB
              </p>
            </div>
          </FormSection>

          <FormSection v-if="payment.receipts && payment.receipts.length > 1" title="سجل المحاولات">
            <ul class="space-y-2 text-sm">
              <li
                v-for="r in payment.receipts"
                :key="r.id"
                class="flex justify-between items-start gap-3 border-b border-border-default pb-2"
              >
                <span class="flex-1">
                  <a
                    :href="`/admin/payments/${payment.id}/receipts/${r.id}/file`"
                    target="_blank"
                    class="text-brand underline"
                  >عرض</a>
                  — {{ r.uploader?.name ?? '—' }}
                  ({{ formatDateTime(r.created_at) }})
                </span>
                <StatusBadge
                  :type="r.status === 'rejected' ? 'danger' : 'info'"
                  :label="r.status === 'rejected' ? `مرفوض: ${r.rejection_reason ?? ''}` : 'مرفوع'"
                />
              </li>
            </ul>
          </FormSection>
        </div>

        <!-- Summary side panel -->
        <div class="space-y-4">
          <FormSection title="ملخص الموعد">
            <dl class="text-sm space-y-3">
              <div>
                <dt class="text-text-secondary">العميل</dt>
                <dd>{{ payment.appointment?.customer?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-text-secondary">الهاتف</dt>
                <dd dir="ltr">{{ payment.appointment?.customer?.phone ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-text-secondary">الخدمة</dt>
                <dd>{{ payment.appointment?.service?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-text-secondary">الطبيب</dt>
                <dd>{{ payment.appointment?.doctor?.user?.name ?? '—' }}</dd>
              </div>
              <div>
                <dt class="text-text-secondary">المبلغ</dt>
                <dd class="text-lg font-bold">{{ payment.amount }} ₪</dd>
              </div>
              <div>
                <dt class="text-text-secondary">حالة الدفع</dt>
                <dd>
                  <StatusBadge
                    :type="statusMap[payment.status]?.variant ?? 'info'"
                    :label="statusMap[payment.status]?.label ?? payment.status"
                  />
                </dd>
              </div>
              <div v-if="payment.verified_at">
                <dt class="text-text-secondary">تحقّق</dt>
                <dd>
                  {{ payment.verifier?.name ?? '—' }} — {{ formatDateTime(payment.verified_at) }}
                </dd>
              </div>
              <div v-if="payment.refunded_at">
                <dt class="text-text-secondary">استرداد</dt>
                <dd>
                  {{ payment.refunder?.name ?? '—' }} — {{ formatDateTime(payment.refunded_at) }}
                  <span v-if="payment.refund_reference" dir="ltr" class="font-mono text-xs block">{{ payment.refund_reference }}</span>
                </dd>
              </div>
              <div v-if="payment.status === 'rejected' && payment.rejection_reason">
                <dt class="text-text-secondary">سبب الرفض</dt>
                <dd class="text-danger">{{ payment.rejection_reason }}</dd>
              </div>
            </dl>
          </FormSection>
        </div>
      </div>
    </div>

    <!-- Reject modal -->
    <Modal :open="showReject" title="رفض الإيصال" @update:open="showReject = $event">
      <form class="space-y-4" @submit.prevent="submitReject">
        <FormGroup label="سبب الرفض" name="reason" :error="rejectForm.errors.reason" required>
          <template #default="{ describedby }">
            <textarea
              id="reason"
              v-model="rejectForm.reason"
              name="reason"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showReject = false">إلغاء</Button>
        <Button class="bg-danger text-white" :disabled="rejectForm.processing || !rejectForm.reason.trim()" @click="submitReject">رفض</Button>
      </template>
    </Modal>

    <!-- Mark refunded modal -->
    <Modal :open="showRefunded" title="سجّل تنفيذ الاسترداد" @update:open="showRefunded = $event">
      <form class="space-y-4" @submit.prevent="submitRefunded">
        <FormGroup label="مرجع التحويل (اختياري)" name="reference" :error="refundedForm.errors.reference">
          <template #default="{ describedby }">
            <Input id="reference" v-model="refundedForm.reference" name="reference" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showRefunded = false">إلغاء</Button>
        <Button :disabled="refundedForm.processing" @click="submitRefunded">تسجيل</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
