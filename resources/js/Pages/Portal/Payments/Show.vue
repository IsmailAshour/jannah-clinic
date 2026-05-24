<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import {
  AlertCircle, ArrowLeft, Check, CheckCircle2, Copy, FileImage, Landmark, RefreshCcw, Upload, X,
} from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  appointment: { type: Object, required: true },
  payment: { type: Object, required: true },
  bank: { type: Object, required: true },
})

const statusMap = {
  pending:        { label: 'بانتظار الدفع',     variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',    variant: 'info'    },
  paid:           { label: 'مدفوع',              variant: 'success' },
  rejected:       { label: 'مرفوض — أعد الرفع', variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد',  variant: 'warning' },
  refunded:       { label: 'مُسترَد',            variant: 'info'    },
}

const canUpload = computed(() => ['pending', 'rejected'].includes(props.payment.status))
const isRejected = computed(() => props.payment.status === 'rejected')
const isSubmitted = computed(() => props.payment.status === 'submitted')

const page = usePage()
const successFlash = computed(() => page.props?.flash?.success ?? null)

// File picker / preview
const form = useForm({ receipt: null })
const fileError = computed(() => form.errors.receipt ?? null)
const previewUrl = ref(null)
const previewKind = ref(null) // 'image' | 'pdf' | null
const dragOver = ref(false)
const localSizeError = ref(null)
const MAX_BYTES = 5 * 1024 * 1024

function clearSelection() {
  form.receipt = null
  previewUrl.value = null
  previewKind.value = null
  localSizeError.value = null
}

function acceptFile(file) {
  localSizeError.value = null
  if (!file) {
    clearSelection()
    return
  }
  if (file.size > MAX_BYTES) {
    localSizeError.value = `الملف كبير (${(file.size / 1024 / 1024).toFixed(1)}MB). الحد الأقصى 5MB.`
    clearSelection()
    return
  }
  form.receipt = file
  const isImage = file.type.startsWith('image/')
  previewKind.value = isImage ? 'image' : 'pdf'
  if (isImage) {
    const reader = new FileReader()
    reader.onload = (ev) => { previewUrl.value = ev.target.result }
    reader.readAsDataURL(file)
  } else {
    previewUrl.value = null
  }
}

function pickFile(e) {
  acceptFile(e.target.files?.[0] ?? null)
}
function onDrop(e) {
  e.preventDefault()
  dragOver.value = false
  acceptFile(e.dataTransfer.files?.[0] ?? null)
}

function submit() {
  if (!form.receipt) return
  form.post(`/portal/appointments/${props.appointment.id}/payment/upload`, {
    forceFormData: true,
    onSuccess: () => { clearSelection() },
  })
}

const copied = ref(null) // 'iban' | 'account' | null
async function copyToClipboard(value, key) {
  if (!value) return
  try {
    await navigator.clipboard.writeText(value)
    copied.value = key
    setTimeout(() => { copied.value = null }, 2000)
  } catch { /* clipboard blocked — user can select+copy manually */ }
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const currentReceipt = computed(() => props.payment.receipts?.[0] ?? null)
const currentReceiptUrl = computed(() => {
  if (!currentReceipt.value) return null
  return `/portal/appointments/${props.appointment.id}/payment/receipt/${currentReceipt.value.id}/file`
})

const currentStepHint = computed(() => {
  if (props.payment.status === 'pending') return 'لم نستلم الإيصال بعد — اتبع الخطوات أدناه.'
  if (props.payment.status === 'submitted') return 'استلمنا إيصالك — بانتظار مراجعته (عادة خلال يوم عمل).'
  if (props.payment.status === 'rejected') return 'الإيصال السابق لم يُقبل — راجع السبب وأعد الرفع.'
  if (props.payment.status === 'paid') return 'تم تأكيد دفعتك. شكرًا لك!'
  return null
})
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-5 max-w-2xl mx-auto">
      <!-- Back link + page title -->
      <header class="space-y-2">
        <a href="/portal/appointments" class="text-sm text-text-secondary hover:text-brand inline-flex items-center gap-1">
          <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
          <span>عودة لمواعيدي</span>
        </a>
        <h1 class="text-2xl font-extrabold text-text-primary">دفع الموعد</h1>
        <p class="text-sm text-text-secondary">
          {{ appointment.service?.name }} <span v-if="appointment.doctor?.user?.name">— {{ appointment.doctor.user.name }}</span>
        </p>
      </header>

      <!-- Amount hero card -->
      <section class="rounded-2xl shadow-md text-white p-5 bg-gradient-to-bl from-brand/95 via-brand to-brand/80">
        <p class="text-xs font-bold text-white/80">المبلغ المستحقّ</p>
        <p class="mt-1 text-4xl font-extrabold leading-tight">{{ payment.amount }} <span class="text-base font-bold text-white/85">₪</span></p>
        <p v-if="appointment.discount_amount" class="mt-1 text-xs text-warning font-bold">
          ✓ تم تطبيق خصم {{ appointment.discount_amount }} ₪
          <span v-if="appointment.discount_type === 'percent'">({{ appointment.discount_value }}%)</span>
          — السعر الأصلي {{ appointment.price_at_booking }} ₪
        </p>
        <div class="mt-3 flex items-center justify-between gap-2 flex-wrap">
          <StatusBadge
            :type="statusMap[payment.status]?.variant ?? 'info'"
            :label="statusMap[payment.status]?.label ?? payment.status"
          />
          <span v-if="payment.status === 'paid' && payment.verified_at" class="text-xs text-white/85">
            تم التأكيد {{ formatDate(payment.verified_at) }}
          </span>
        </div>
        <p v-if="currentStepHint" class="mt-3 text-sm text-white/90 leading-relaxed">{{ currentStepHint }}</p>
      </section>

      <!-- Success flash -->
      <div v-if="successFlash" role="status" class="rounded-md border border-success/30 bg-success/5 px-3 py-2.5 text-sm text-success inline-flex items-center gap-2">
        <CheckCircle2 class="w-4 h-4" aria-hidden="true" />
        {{ successFlash }}
      </div>

      <!-- Rejection callout (above steps, ties into step 3) -->
      <div v-if="isRejected" role="alert" class="rounded-2xl border-2 border-danger bg-danger/5 p-4 space-y-2">
        <div class="flex items-center gap-2">
          <AlertCircle class="w-5 h-5 text-danger shrink-0" aria-hidden="true" />
          <p class="text-sm font-bold text-danger">الإيصال السابق لم يُقبل</p>
        </div>
        <p v-if="payment.rejection_reason" class="text-sm text-text-primary leading-relaxed pr-7">
          <span class="font-semibold">السبب:</span> {{ payment.rejection_reason }}
        </p>
        <p class="text-xs text-text-secondary pr-7">انتقل للخطوة 3 وأعد رفع صورة واضحة للإيصال.</p>
      </div>

      <!-- ============ STEPS ============
           Steps 1 + 2 (bank info + photo guidance) are only useful while the
           customer still needs to send a receipt. Once status is submitted /
           paid / refund-pending / refunded we hide them — the customer's
           remaining concerns (view receipt, refund status) live below. -->

      <!-- Step 1: Transfer (bank info) -->
      <section v-if="canUpload" class="bg-surface-card rounded-2xl shadow-sm border border-border-default p-5 space-y-3">
        <div class="flex items-center gap-3">
          <span class="w-8 h-8 rounded-full bg-brand text-white grid place-items-center font-extrabold text-sm">١</span>
          <div class="flex-1">
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <Landmark class="w-4 h-4 text-brand" aria-hidden="true" />
              حوّل المبلغ إلى حساب العيادة
            </h2>
            <p class="text-xs text-text-secondary">استخدم البيانات أدناه من خلال تطبيق البنك أو الصرّاف. لا حاجة لزيارة الفرع.</p>
          </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-xs text-text-tertiary">البنك</dt>
            <dd class="text-text-primary font-medium">{{ bank.name || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-text-tertiary">اسم الحساب</dt>
            <dd class="text-text-primary font-medium">{{ bank.account_holder || '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-text-tertiary">رقم الحساب</dt>
            <dd class="flex items-center gap-2">
              <span dir="ltr" class="text-text-primary font-mono">{{ bank.account_number || '—' }}</span>
              <button
                v-if="bank.account_number"
                type="button"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-brand/10 text-brand hover:bg-brand/15 transition"
                @click="copyToClipboard(bank.account_number, 'account')"
              >
                <Copy class="w-3 h-3" aria-hidden="true" />
                {{ copied === 'account' ? 'نُسخ' : 'نسخ' }}
              </button>
            </dd>
          </div>
          <div>
            <dt class="text-xs text-text-tertiary">IBAN</dt>
            <dd class="flex items-center gap-2">
              <span dir="ltr" class="text-text-primary font-mono break-all">{{ bank.iban || '—' }}</span>
              <button
                v-if="bank.iban"
                type="button"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-bold bg-brand/10 text-brand hover:bg-brand/15 transition shrink-0"
                @click="copyToClipboard(bank.iban, 'iban')"
              >
                <Copy class="w-3 h-3" aria-hidden="true" />
                {{ copied === 'iban' ? 'نُسخ' : 'نسخ' }}
              </button>
            </dd>
          </div>
        </dl>

        <div class="text-xs text-text-secondary rounded-md bg-warning/5 border border-warning/30 p-2.5">
          <span class="font-semibold">المبلغ:</span> {{ payment.amount }} ₪ بالضبط. تأكّد من المبلغ قبل التحويل.
        </div>
      </section>

      <!-- Step 2 (merged): Capture + Upload — single section so the customer
           takes a photo and submits it without crossing card boundaries. -->
      <section v-if="canUpload" class="bg-surface-card rounded-2xl shadow-sm border border-border-default p-5 space-y-4">
        <div class="flex items-center gap-3">
          <span class="w-8 h-8 rounded-full bg-brand text-white grid place-items-center font-extrabold text-sm">٢</span>
          <div class="flex-1">
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <Upload class="w-4 h-4 text-brand" aria-hidden="true" />
              {{ isRejected ? 'أعد رفع صورة الإيصال' : 'صوّر الإيصال وارفعه' }}
            </h2>
            <p class="text-xs text-text-secondary">استعن بتطبيق البنك (شاشة "نجاح التحويل") أو صوّر الإيصال الورقي — التحقّق عادة خلال يوم عمل.</p>
          </div>
        </div>

        <!-- Guidance bullets folded into the same card -->
        <ul class="text-xs text-text-secondary list-disc list-inside space-y-1 ps-2 bg-info/5 border border-info/20 rounded-md p-3">
          <li>الصورة واضحة وكامل التفاصيل ظاهرة (المبلغ، التاريخ، رقم الحساب).</li>
          <li>الصيغة: JPG أو PNG أو PDF — الحجم الأقصى 5 ميغابايت.</li>
        </ul>

        <!-- Drop zone -->
        <label
          :class="[
            'block rounded-2xl border-2 border-dashed transition cursor-pointer text-center p-6',
            dragOver ? 'border-brand bg-brand/5' : 'border-border-default hover:border-brand/50 hover:bg-surface-page',
            (fileError || localSizeError) ? 'border-danger/50 bg-danger/5' : '',
          ]"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop="onDrop"
        >
          <!-- Preview state -->
          <div v-if="form.receipt" class="space-y-3">
            <img
              v-if="previewKind === 'image' && previewUrl"
              :src="previewUrl"
              alt="معاينة الإيصال"
              class="mx-auto max-h-56 rounded-md object-contain ring-1 ring-border-default"
            />
            <div v-else class="mx-auto w-20 h-24 rounded-md bg-brand/10 text-brand grid place-items-center">
              <FileImage class="w-10 h-10" aria-hidden="true" />
            </div>
            <div class="text-sm text-text-primary font-medium break-all">{{ form.receipt.name }}</div>
            <div class="text-xs text-text-tertiary">{{ (form.receipt.size / 1024).toFixed(1) }} KB</div>
            <button
              type="button"
              class="text-xs font-bold text-danger inline-flex items-center gap-1 hover:underline"
              @click.prevent="clearSelection"
            >
              <X class="w-3.5 h-3.5" aria-hidden="true" />
              إلغاء الاختيار
            </button>
          </div>

          <!-- Empty state -->
          <div v-else class="space-y-2">
            <div class="mx-auto w-12 h-12 rounded-full bg-brand/10 text-brand grid place-items-center">
              <Upload class="w-6 h-6" aria-hidden="true" />
            </div>
            <p class="text-sm font-bold text-text-primary">اسحب صورة الإيصال هنا</p>
            <p class="text-xs text-text-secondary">أو اضغط لاختيار ملف من جهازك</p>
            <p class="text-[11px] text-text-tertiary">JPG / PNG / PDF · حتى 5MB</p>
          </div>

          <input
            id="receipt"
            type="file"
            name="receipt"
            class="sr-only"
            accept="image/jpeg,image/png,application/pdf"
            @change="pickFile"
          />
        </label>

        <p v-if="localSizeError" class="text-sm text-danger font-medium inline-flex items-center gap-1">
          <AlertCircle class="w-4 h-4" aria-hidden="true" />
          {{ localSizeError }}
        </p>
        <p v-if="fileError" class="text-sm text-danger font-medium inline-flex items-center gap-1">
          <AlertCircle class="w-4 h-4" aria-hidden="true" />
          {{ fileError }}
        </p>

        <Button class="w-full h-12 text-base font-bold gap-2" :disabled="!form.receipt || form.processing" @click="submit">
          <component :is="isRejected ? RefreshCcw : Upload" class="w-4 h-4" aria-hidden="true" />
          <span>
            {{ form.processing ? 'جاري الرفع…' : (isRejected ? 'إعادة الرفع' : 'إرسال الإيصال') }}
          </span>
        </Button>
      </section>

      <!-- Already submitted: show the receipt the user sent -->
      <section v-else-if="isSubmitted && currentReceipt" class="bg-surface-card rounded-2xl shadow-sm border-2 border-info/30 p-5 space-y-3">
        <div class="flex items-center gap-3">
          <span class="w-8 h-8 rounded-full bg-info text-white grid place-items-center font-extrabold text-sm">٣</span>
          <div class="flex-1">
            <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
              <Check class="w-4 h-4 text-info" aria-hidden="true" />
              تم استلام إيصالك
            </h2>
            <p class="text-xs text-text-secondary">سنبلّغك فور إتمام التحقّق.</p>
          </div>
        </div>
        <a
          :href="currentReceiptUrl"
          target="_blank"
          rel="noopener"
          class="block rounded-md border border-border-default bg-surface-page p-3 text-sm text-text-primary hover:bg-surface-card transition"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="font-medium">عرض الإيصال المرفوع</span>
            <span class="text-xs text-text-tertiary">{{ formatDate(currentReceipt.created_at) }}</span>
          </div>
          <p class="text-xs text-text-tertiary mt-1" dir="ltr">{{ currentReceipt.mime_type }} · {{ (currentReceipt.file_size / 1024).toFixed(1) }} KB</p>
        </a>
      </section>

      <!-- Refunded info -->
      <section v-else-if="payment.status === 'refunded'" class="bg-surface-card rounded-2xl shadow-sm border border-info/30 p-5">
        <p class="text-sm text-text-primary"><span class="font-bold">تمّ تنفيذ الاسترداد.</span></p>
        <p v-if="payment.refund_reference" class="mt-1 text-xs text-text-secondary">
          مرجع الاسترداد: <span dir="ltr" class="font-mono">{{ payment.refund_reference }}</span>
        </p>
      </section>
    </div>
  </ClientShell>
</template>
