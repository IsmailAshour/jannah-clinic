<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import {
  AlertCircle, ArrowLeft, BadgeCheck, BadgeX, Calendar, Check, Clock,
  CreditCard, FileText, Home, ImagePlus, Mail, MapPin, MessageCircle, NotebookPen, Pencil,
  Phone, Pill, Plus, Receipt, RotateCcw, Stethoscope, Trash2, User as UserIcon, Video, X,
} from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { StatusBadge, Modal, FormGroup, ConfirmModal } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  appointment: { type: Object, required: true },
  payment: { type: Object, default: null },
  photos: { type: Array, default: () => [] },
  medicalEntry: { type: Object, default: null },
  canWriteMedical: { type: Boolean, default: false },
  canViewMedical: { type: Boolean, default: false },
})

const page = usePage()
const userRole = computed(() => page.props?.auth?.user?.role ?? null)
const isManager = computed(() => userRole.value === 'manager')
const canEditPhotos = computed(() => ['manager', 'doctor'].includes(userRole.value))

const whatsappUrl = computed(() => {
  const phone = (props.appointment.whatsapp_phone || '').replace(/\D+/g, '')
  if (!phone) return '#'
  const doctorName = props.appointment.doctor?.name || ''
  const text = `السلام عليكم، معك ${doctorName} من عيادة جنّة. أتواصل معك للموعد المحدد.`
  return `https://wa.me/${phone}?text=${encodeURIComponent(text)}`
})

const apptStatusMap = {
  requested:   { label: 'بانتظار التأكيد', variant: 'warning' },
  confirmed:   { label: 'مؤكد',            variant: 'success' },
  completed:   { label: 'مكتمل',           variant: 'info'    },
  cancelled:   { label: 'ملغى',            variant: 'danger'  },
  rejected:    { label: 'مرفوض',           variant: 'danger'  },
  no_show:     { label: 'لم يحضر',         variant: 'warning' },
  rescheduled: { label: 'أُعيد جدولته',    variant: 'info'    },
}
const payStatusMap = {
  pending:        { label: 'بانتظار الدفع',      variant: 'warning' },
  submitted:      { label: 'بانتظار التحقّق',     variant: 'info'    },
  paid:           { label: 'مدفوع',               variant: 'success' },
  rejected:       { label: 'مرفوض',               variant: 'danger'  },
  refund_pending: { label: 'بانتظار الاسترداد',   variant: 'warning' },
  refunded:       { label: 'مُسترَدّ',            variant: 'info'    },
}
function formatDateTime(dt) {
  if (!dt) return ''
  return new Date(dt).toLocaleString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const allowedTransitions = computed(() => {
  switch (props.appointment.status) {
    case 'requested': return ['confirmed', 'rejected', 'cancelled']
    case 'confirmed': return ['completed', 'cancelled', 'no_show']
    default:          return []
  }
})
const transitionLabels = {
  confirmed: 'تأكيد الموعد', rejected: 'رفض الموعد', cancelled: 'إلغاء الموعد',
  completed: 'وسم كمكتمل',  no_show: 'لم يحضر',
}
const transitionVariants = {
  confirmed: 'default', completed: 'default', no_show: 'outline',
  rejected: 'destructive', cancelled: 'destructive',
}

const cancelModal = ref({ open: false, status: '' })
const cancelForm = useForm({ status: '', reason: '' })
function openTransition(status) {
  if (['cancelled', 'rejected'].includes(status)) {
    cancelModal.value = { open: true, status }
    cancelForm.reset()
    cancelForm.status = status
    return
  }
  router.post(`/admin/appointments/${props.appointment.id}/transition`, { status }, { preserveScroll: true })
}
function submitCancel() {
  cancelForm.post(`/admin/appointments/${props.appointment.id}/transition`, {
    preserveScroll: true,
    onSuccess: () => { cancelModal.value.open = false },
  })
}

// Payment actions
const rejectModalOpen = ref(false)
const rejectForm = useForm({ rejection_reason: '' })
function verifyPayment() {
  if (!props.payment) return
  router.post(`/admin/payments/${props.payment.id}/verify`, {}, { preserveScroll: true })
}
function openRejectModal() { rejectForm.reset(); rejectModalOpen.value = true }
function submitReject() {
  rejectForm.post(`/admin/payments/${props.payment.id}/reject`, {
    preserveScroll: true,
    onSuccess: () => { rejectModalOpen.value = false },
  })
}
const refundForm = useForm({ refund_reference: '' })
const refundModalOpen = ref(false)
function openRefundModal() { refundForm.reset(); refundModalOpen.value = true }
function markRefundPending() {
  router.post(`/admin/payments/${props.payment.id}/mark-refund-pending`, {}, { preserveScroll: true })
}
function submitRefunded() {
  refundForm.post(`/admin/payments/${props.payment.id}/mark-refunded`, {
    preserveScroll: true,
    onSuccess: () => { refundModalOpen.value = false },
  })
}

// Photo upload
const photoModalOpen = ref(false)
const photoForm = useForm({ kind: 'before', photo: null, caption: '' })
const photoPreview = ref(null)
const photoSizeError = ref(null)
function openPhotoModal() {
  photoForm.reset(); photoForm.kind = 'before'
  photoPreview.value = null; photoSizeError.value = null
  photoModalOpen.value = true
}
function onPhotoChange(e) {
  const file = e.target.files?.[0] ?? null
  photoForm.photo = file
  photoSizeError.value = null
  if (file) {
    if (file.size > 8 * 1024 * 1024) {
      photoSizeError.value = `الصورة كبيرة (${(file.size / 1024 / 1024).toFixed(1)}MB). الحدّ الأقصى 8MB.`
      photoForm.photo = null; e.target.value = ''; photoPreview.value = null
      return
    }
    const r = new FileReader()
    r.onload = (ev) => { photoPreview.value = ev.target.result }
    r.readAsDataURL(file)
  } else { photoPreview.value = null }
}
function submitPhoto() {
  if (!photoForm.photo) return
  photoForm.post(`/admin/appointments/${props.appointment.id}/photos`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { photoModalOpen.value = false; photoPreview.value = null },
  })
}
const confirmDelete = ref(false)
const deleteTargetId = ref(null)
function askDeletePhoto(id) { deleteTargetId.value = id; confirmDelete.value = true }
function doDeletePhoto() {
  router.delete(`/admin/appointments/${props.appointment.id}/photos/${deleteTargetId.value}`, {
    preserveScroll: true,
    onSuccess: () => { confirmDelete.value = false; deleteTargetId.value = null },
  })
}

const beforePhotos = computed(() => props.photos.filter(p => p.kind === 'before'))
const afterPhotos = computed(() => props.photos.filter(p => p.kind === 'after'))
const latestReceipt = computed(() => props.payment?.receipts?.[0] ?? null)
const receiptIsImage = computed(() => latestReceipt.value && latestReceipt.value.mime_type.startsWith('image/'))
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-4 max-w-7xl mx-auto">
      <Link href="/admin/appointments" class="text-sm text-text-secondary hover:text-text-primary inline-flex items-center gap-1">
        <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
        <span>كل المواعيد</span>
      </Link>

      <!-- Compact hero strip -->
      <header class="bg-surface-card rounded-2xl ring-1 ring-border-default p-4 sm:p-5 flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
          <p class="text-xs font-bold text-brand">موعد #{{ appointment.id }}</p>
          <h1 class="text-xl sm:text-2xl font-extrabold text-text-primary truncate">{{ appointment.service.name }}</h1>
          <p class="text-sm text-text-secondary mt-0.5 inline-flex items-center gap-1.5">
            <Calendar class="w-3.5 h-3.5" aria-hidden="true" />
            {{ formatDateTime(appointment.start_at) }} · {{ appointment.service.duration_minutes }} د
          </p>
        </div>
        <StatusBadge :type="apptStatusMap[appointment.status]?.variant ?? 'info'" :label="apptStatusMap[appointment.status]?.label ?? appointment.status" />
      </header>

      <p v-if="appointment.cancellation_reason" class="rounded-md bg-danger/10 border border-danger/30 px-3 py-2 text-sm text-danger">
        <span class="font-bold">سبب الإلغاء:</span> {{ appointment.cancellation_reason }}
      </p>

      <!-- ============ 2-COL LAYOUT ============ -->
      <!-- Body (right in RTL): medical + photos. Sidebar (left in RTL): status, payment, customer, doctor. -->
      <div class="grid gap-4 lg:grid-cols-3">
        <!-- ============ BODY (2/3) ============ -->
        <div class="lg:col-span-2 space-y-4">
          <!-- Medical record -->
          <section v-if="canViewMedical" class="bg-surface-card rounded-2xl ring-1 ring-border-default overflow-hidden">
            <header class="px-5 py-3 border-b border-border-default flex items-center justify-between gap-2 bg-surface-page/40">
              <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
                <NotebookPen class="w-4 h-4 text-brand" aria-hidden="true" />
                السجل الطبي
              </h2>
              <div class="flex items-center gap-2">
                <Link
                  v-if="medicalEntry && canWriteMedical"
                  :href="`/admin/medical-entries/${medicalEntry.id}/edit`"
                  class="inline-flex items-center gap-1.5 text-xs font-bold text-brand hover:underline"
                >
                  <Pencil class="w-3.5 h-3.5" aria-hidden="true" />
                  <span>تعديل</span>
                </Link>
                <Link
                  v-else-if="!medicalEntry && canWriteMedical"
                  :href="`/admin/appointments/${appointment.id}/medical-entry/create`"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-brand text-white text-xs font-bold hover:bg-brand-hover transition"
                >
                  <Plus class="w-3.5 h-3.5" aria-hidden="true" />
                  <span>إضافة سجل</span>
                </Link>
              </div>
            </header>

            <div class="p-5 space-y-4">
              <div v-if="!medicalEntry" class="text-sm text-text-secondary text-center py-3">
                <FileText class="w-8 h-8 mx-auto text-brand/30 mb-2" aria-hidden="true" />
                <p>
                  لم يُضَف سجل طبي بعد.
                  <span v-if="!canWriteMedical">سيقوم الطبيب بإضافته.</span>
                </p>
              </div>

              <template v-else>
                <div class="flex items-center gap-2 text-xs text-text-tertiary flex-wrap">
                  <span class="inline-flex items-center gap-1">
                    <UserIcon class="w-3 h-3" aria-hidden="true" />
                    {{ medicalEntry.author_name || 'الطبيب' }}
                  </span>
                  <span aria-hidden="true">·</span>
                  <span dir="ltr">{{ formatDateTime(medicalEntry.created_at) }}</span>
                  <template v-if="medicalEntry.updated_at !== medicalEntry.created_at">
                    <span aria-hidden="true">·</span>
                    <span>آخر تعديل <span dir="ltr">{{ formatDateTime(medicalEntry.updated_at) }}</span></span>
                  </template>
                </div>

                <div>
                  <p class="text-xs font-bold text-text-secondary mb-1.5">الخلاصة الظاهرة للعميل</p>
                  <div class="rounded-md bg-info/5 border border-info/20 p-3 text-sm text-text-primary leading-relaxed whitespace-pre-wrap">{{ medicalEntry.visible_summary }}</div>
                </div>

                <div v-if="medicalEntry.staff_notes">
                  <p class="text-xs font-bold text-text-secondary mb-1.5 inline-flex items-center gap-1.5">
                    ملاحظات داخليّة
                    <span class="text-[10px] font-bold text-warning bg-warning/10 border border-warning/30 rounded-full px-1.5">سرّيّة</span>
                  </p>
                  <div class="rounded-md bg-warning/5 border border-warning/20 p-3 text-sm text-text-primary leading-relaxed whitespace-pre-wrap">{{ medicalEntry.staff_notes }}</div>
                </div>

                <div v-if="medicalEntry.prescriptions.length > 0">
                  <p class="text-xs font-bold text-text-secondary mb-1.5 inline-flex items-center gap-1.5">
                    <Pill class="w-3 h-3" aria-hidden="true" />
                    الأدوية الموصوفة ({{ medicalEntry.prescriptions.length }})
                  </p>
                  <ul class="space-y-2">
                    <li v-for="p in medicalEntry.prescriptions" :key="p.id" class="rounded-md border border-border-default p-3 text-sm">
                      <p class="font-bold text-text-primary">{{ p.medication_name }}</p>
                      <div class="mt-1 grid grid-cols-1 sm:grid-cols-3 gap-x-3 gap-y-1 text-xs text-text-secondary">
                        <p><span class="font-bold text-text-tertiary">الجرعة:</span> {{ p.dosage }}</p>
                        <p><span class="font-bold text-text-tertiary">التكرار:</span> {{ p.frequency }}</p>
                        <p><span class="font-bold text-text-tertiary">المدّة:</span> {{ p.duration }}</p>
                      </div>
                      <p v-if="p.notes" class="mt-1.5 text-xs text-text-secondary"><span class="font-bold text-text-tertiary">ملاحظات:</span> {{ p.notes }}</p>
                    </li>
                  </ul>
                </div>
              </template>
            </div>
          </section>

          <!-- Before/After photos -->
          <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-4">
            <header class="flex items-center justify-between">
              <h2 class="text-base font-bold text-text-primary">صور قبل/بعد الجلسة</h2>
              <Button v-if="canEditPhotos" size="sm" variant="outline" class="gap-1.5" @click="openPhotoModal">
                <ImagePlus class="w-3.5 h-3.5" aria-hidden="true" />
                <span>إضافة</span>
              </Button>
            </header>
            <div v-if="photos.length === 0" class="text-sm text-text-tertiary text-center py-4">لا صور مرفوعة بعد.</div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <p class="text-xs font-bold text-text-secondary mb-2 inline-flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-warning" aria-hidden="true" /> قبل ({{ beforePhotos.length }})
                </p>
                <ul v-if="beforePhotos.length > 0" class="grid grid-cols-3 gap-2">
                  <li v-for="p in beforePhotos" :key="p.id" class="relative group">
                    <a :href="p.file_url" target="_blank" rel="noopener" class="block aspect-square rounded-md overflow-hidden ring-1 ring-border-default">
                      <img :src="p.file_url" :alt="p.caption || 'قبل'" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                    <button v-if="canEditPhotos" type="button" class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-danger text-white grid place-items-center shadow opacity-0 group-hover:opacity-100 transition" aria-label="حذف" @click.prevent="askDeletePhoto(p.id)">
                      <Trash2 class="w-3 h-3" aria-hidden="true" />
                    </button>
                  </li>
                </ul>
                <p v-else class="text-[11px] text-text-tertiary">لا صور قبل.</p>
              </div>
              <div>
                <p class="text-xs font-bold text-text-secondary mb-2 inline-flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-success" aria-hidden="true" /> بعد ({{ afterPhotos.length }})
                </p>
                <ul v-if="afterPhotos.length > 0" class="grid grid-cols-3 gap-2">
                  <li v-for="p in afterPhotos" :key="p.id" class="relative group">
                    <a :href="p.file_url" target="_blank" rel="noopener" class="block aspect-square rounded-md overflow-hidden ring-1 ring-border-default">
                      <img :src="p.file_url" :alt="p.caption || 'بعد'" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                    <button v-if="canEditPhotos" type="button" class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-danger text-white grid place-items-center shadow opacity-0 group-hover:opacity-100 transition" aria-label="حذف" @click.prevent="askDeletePhoto(p.id)">
                      <Trash2 class="w-3 h-3" aria-hidden="true" />
                    </button>
                  </li>
                </ul>
                <p v-else class="text-[11px] text-text-tertiary">لا صور بعد.</p>
              </div>
            </div>
          </section>
        </div>

        <!-- ============ SIDEBAR (1/3) ============ -->
        <aside class="space-y-4">
          <!-- Status & actions -->
          <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-3">
            <h3 class="text-sm font-bold text-text-primary">حالة الموعد</h3>
            <p class="text-xs text-text-secondary">إجراءات تغيير حالة هذا الموعد:</p>
            <div v-if="allowedTransitions.length > 0" class="flex flex-col gap-2">
              <Button
                v-for="s in allowedTransitions"
                :key="s"
                :variant="transitionVariants[s] ?? 'default'"
                size="sm"
                class="w-full justify-center gap-1.5"
                @click="openTransition(s)"
              >
                <BadgeCheck v-if="s === 'confirmed' || s === 'completed'" class="w-3.5 h-3.5" aria-hidden="true" />
                <BadgeX v-else-if="s === 'rejected' || s === 'cancelled'" class="w-3.5 h-3.5" aria-hidden="true" />
                <span>{{ transitionLabels[s] }}</span>
              </Button>
            </div>
            <p v-else class="text-xs text-text-tertiary">لا إجراءات متاحة في الحالة الحاليّة.</p>
          </section>

          <!-- Payment block: receipt preview + meta + actions, all in one card.
               Sits directly below "حالة الموعد" so receipt review + status flips
               happen in the same visual lane. -->
          <section v-if="payment" class="bg-surface-card rounded-2xl ring-1 ring-border-default overflow-hidden">
            <header class="px-4 py-3 border-b border-border-default flex items-center justify-between gap-2 bg-surface-page/40">
              <h3 class="text-sm font-bold text-text-primary inline-flex items-center gap-1.5">
                <CreditCard class="w-4 h-4 text-brand" aria-hidden="true" />
                الدفع — {{ payment.amount }} ₪
              </h3>
              <StatusBadge :type="payStatusMap[payment.status]?.variant ?? 'info'" :label="payStatusMap[payment.status]?.label ?? payment.status" />
            </header>

            <div class="p-4 space-y-3">
              <p class="text-xs font-bold text-text-secondary inline-flex items-center gap-1.5">
                <Receipt class="w-3.5 h-3.5 text-brand" aria-hidden="true" />
                إيصال التحويل
              </p>
              <div v-if="latestReceipt">
                <a v-if="receiptIsImage" :href="latestReceipt.file_url" target="_blank" rel="noopener" class="block rounded-md overflow-hidden ring-1 ring-border-default hover:ring-brand transition">
                  <img :src="latestReceipt.file_url" alt="إيصال التحويل" class="w-full max-h-72 object-contain bg-surface-page" />
                </a>
                <a v-else :href="latestReceipt.file_url" target="_blank" rel="noopener" class="block rounded-md border-2 border-dashed border-border-default p-4 text-center hover:border-brand transition">
                  <p class="text-xs font-bold text-text-primary">تنزيل الإيصال (PDF)</p>
                </a>
                <p class="mt-1.5 text-[11px] text-text-tertiary">رُفع: {{ formatDateTime(latestReceipt.created_at) }}</p>
              </div>
              <div v-else class="rounded-md border-2 border-dashed border-border-default p-4 text-center text-xs text-text-secondary">
                لم يرفع العميل إيصال التحويل بعد.
              </div>

              <p v-if="payment.rejection_reason" class="rounded-md bg-danger/10 border border-danger/30 px-2.5 py-1.5 text-[11px] text-danger">
                <span class="font-bold">سبب الرفض:</span> {{ payment.rejection_reason }}
              </p>
              <p v-if="payment.verified_at" class="text-[11px] text-success font-medium inline-flex items-center gap-1">
                <Check class="w-3 h-3" aria-hidden="true" /> تم التحقّق {{ formatDateTime(payment.verified_at) }}
              </p>
              <p v-if="payment.refund_reference" class="text-[11px] text-text-secondary">
                مرجع الاسترداد: <span dir="ltr" class="font-mono">{{ payment.refund_reference }}</span>
              </p>

              <!-- Action buttons -->
              <div class="pt-2 border-t border-border-default space-y-2">
                <template v-if="payment.status === 'submitted' && isManager">
                  <Button class="w-full gap-1.5" size="sm" @click="verifyPayment">
                    <BadgeCheck class="w-4 h-4" aria-hidden="true" />
                    <span>الموافقة على الإيصال</span>
                  </Button>
                  <Button variant="destructive" class="w-full gap-1.5" size="sm" @click="openRejectModal">
                    <BadgeX class="w-4 h-4" aria-hidden="true" />
                    <span>رفض الإيصال</span>
                  </Button>
                </template>
                <template v-else-if="payment.status === 'paid' && isManager">
                  <Button variant="outline" class="w-full gap-1.5" size="sm" @click="markRefundPending">
                    <RotateCcw class="w-4 h-4" aria-hidden="true" />
                    <span>بدء الاسترداد</span>
                  </Button>
                </template>
                <template v-else-if="payment.status === 'refund_pending' && isManager">
                  <Button class="w-full gap-1.5" size="sm" @click="openRefundModal">
                    <Check class="w-4 h-4" aria-hidden="true" />
                    <span>وسم كمُسترَدّ</span>
                  </Button>
                </template>
                <p v-else class="text-[11px] text-text-secondary text-center">
                  {{ payment.status === 'pending' ? 'بانتظار رفع الإيصال.' :
                     payment.status === 'rejected' ? 'الإيصال مرفوض — العميل يحتاج إعادة الرفع.' :
                     payment.status === 'paid' ? 'الدفع مكتمل.' :
                     payment.status === 'refunded' ? 'تم الاسترداد.' :
                     'لا إجراءات متاحة الآن.' }}
                </p>
              </div>
            </div>
          </section>
          <section v-else class="bg-surface-card rounded-2xl ring-1 ring-border-default p-4">
            <p class="text-xs text-text-secondary">لا دفعة مرتبطة (نقدًا أو بنقاط الولاء).</p>
          </section>

          <!-- Customer card -->
          <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-2.5">
            <h3 class="text-sm font-bold text-text-primary inline-flex items-center gap-1.5">
              <UserIcon class="w-4 h-4 text-brand" aria-hidden="true" />
              العميل
            </h3>
            <Link :href="`/admin/customers/${appointment.customer.id}`" class="block text-sm font-bold text-brand hover:underline truncate">
              {{ appointment.customer.name }}
            </Link>
            <p v-if="appointment.customer.phone" class="text-xs text-text-secondary inline-flex items-center gap-1.5">
              <Phone class="w-3 h-3" aria-hidden="true" />
              <a :href="`tel:${appointment.customer.phone}`" dir="ltr" class="text-brand">{{ appointment.customer.phone }}</a>
            </p>
            <p v-if="appointment.customer.email" class="text-xs text-text-secondary inline-flex items-center gap-1.5">
              <Mail class="w-3 h-3" aria-hidden="true" />
              <a :href="`mailto:${appointment.customer.email}`" dir="ltr" class="text-brand truncate">{{ appointment.customer.email }}</a>
            </p>
          </section>

          <!-- Doctor card -->
          <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-2.5">
            <h3 class="text-sm font-bold text-text-primary inline-flex items-center gap-1.5">
              <Stethoscope class="w-4 h-4 text-brand" aria-hidden="true" />
              مقدّم الخدمة
            </h3>
            <p class="text-sm font-bold text-text-primary truncate">{{ appointment.doctor.name }}</p>
            <p v-if="appointment.doctor.specialty" class="text-xs text-text-secondary">{{ appointment.doctor.specialty }}</p>
            <Link :href="`/admin/doctors/${appointment.doctor.id}/day`" class="text-xs font-bold text-brand hover:underline inline-flex items-center gap-1">
              عرض جدول اليوم ←
            </Link>
          </section>

          <!-- Delivery / address -->
          <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-5 space-y-2.5">
            <h3 class="text-sm font-bold text-text-primary inline-flex items-center gap-1.5">
              <component
                :is="appointment.delivery_mode === 'home' ? Home : (appointment.delivery_mode === 'online' ? Video : MapPin)"
                class="w-4 h-4 text-brand"
                aria-hidden="true"
              />
              {{ appointment.delivery_mode === 'home' ? 'زيارة منزليّة' : (appointment.delivery_mode === 'online' ? 'موعد أونلاين' : 'في المركز') }}
            </h3>
            <p class="text-sm">السعر: <span class="font-bold text-brand">{{ appointment.price_at_booking }} ₪</span></p>
            <p v-if="appointment.service_address" class="text-xs text-text-secondary leading-relaxed">{{ appointment.service_address.address_text }}</p>
            <p v-if="appointment.service_address?.location_note" class="text-xs text-text-tertiary leading-relaxed">{{ appointment.service_address.location_note }}</p>
            <a
              v-if="appointment.service_address?.lat != null && appointment.service_address?.lng != null"
              :href="`https://www.openstreetmap.org/?mlat=${appointment.service_address.lat}&mlon=${appointment.service_address.lng}#map=17/${appointment.service_address.lat}/${appointment.service_address.lng}`"
              target="_blank"
              rel="noopener"
              class="inline-flex items-center gap-1 text-xs font-bold text-brand hover:underline"
            >
              <MapPin class="w-3.5 h-3.5" aria-hidden="true" />
              فتح الموقع في الخريطة
            </a>
            <div v-if="appointment.delivery_mode === 'online' && appointment.whatsapp_phone" class="space-y-2">
              <p class="text-xs text-text-secondary">رقم واتساب المريض: <span dir="ltr" class="font-bold">{{ appointment.whatsapp_phone }}</span></p>
              <a
                :href="whatsappUrl"
                target="_blank"
                rel="noopener"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-[#25D366] text-white px-3 py-2 text-sm font-bold hover:bg-[#1ebe5d] transition"
              >
                <MessageCircle class="w-4 h-4" aria-hidden="true" />
                تواصل عبر واتساب
              </a>
            </div>
          </section>
        </aside>
      </div>
    </div>

    <!-- Cancel / reject appointment modal -->
    <Modal :open="cancelModal.open" :title="cancelModal.status === 'cancelled' ? 'إلغاء الموعد' : 'رفض الموعد'" @update:open="cancelModal.open = $event">
      <form class="space-y-4" @submit.prevent="submitCancel">
        <FormGroup label="السبب" name="reason" :error="cancelForm.errors.reason" required>
          <template #default>
            <textarea v-model="cancelForm.reason" rows="3" maxlength="500" class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand" placeholder="اذكر السبب…" />
          </template>
        </FormGroup>
        <p v-if="cancelForm.errors.appointment" class="text-xs text-danger">{{ cancelForm.errors.appointment }}</p>
      </form>
      <template #footer>
        <Button variant="outline" @click="cancelModal.open = false">تراجع</Button>
        <Button variant="destructive" :disabled="cancelForm.processing || !cancelForm.reason.trim()" @click="submitCancel">تأكيد</Button>
      </template>
    </Modal>

    <!-- Receipt reject modal -->
    <Modal :open="rejectModalOpen" title="رفض الإيصال" @update:open="rejectModalOpen = $event">
      <form class="space-y-4" @submit.prevent="submitReject">
        <FormGroup label="سبب الرفض" name="rejection_reason" :error="rejectForm.errors.rejection_reason" required>
          <template #default>
            <textarea v-model="rejectForm.rejection_reason" rows="3" maxlength="500" class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand" placeholder="مثال: المبلغ غير مطابق" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="rejectModalOpen = false">تراجع</Button>
        <Button variant="destructive" :disabled="rejectForm.processing || !rejectForm.rejection_reason.trim()" @click="submitReject">رفض</Button>
      </template>
    </Modal>

    <!-- Refund modal -->
    <Modal :open="refundModalOpen" title="تأكيد الاسترداد" @update:open="refundModalOpen = $event">
      <form class="space-y-4" @submit.prevent="submitRefunded">
        <FormGroup label="مرجع الاسترداد" name="refund_reference" :error="refundForm.errors.refund_reference">
          <template #default>
            <Input v-model="refundForm.refund_reference" placeholder="رقم العمليّة" dir="ltr" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="refundModalOpen = false">تراجع</Button>
        <Button :disabled="refundForm.processing" @click="submitRefunded">تأكيد</Button>
      </template>
    </Modal>

    <!-- Upload photo modal -->
    <Modal :open="photoModalOpen" title="إضافة صورة" @update:open="photoModalOpen = $event">
      <form class="space-y-4" @submit.prevent="submitPhoto">
        <FormGroup label="النوع" name="kind" :error="photoForm.errors.kind" required>
          <template #default>
            <div class="flex gap-2">
              <label class="flex-1 cursor-pointer rounded-md border-2 px-3 py-2 text-center text-sm font-bold transition" :class="photoForm.kind === 'before' ? 'border-warning bg-warning/5' : 'border-border-default'">
                <input v-model="photoForm.kind" type="radio" value="before" class="sr-only" />
                قبل الجلسة
              </label>
              <label class="flex-1 cursor-pointer rounded-md border-2 px-3 py-2 text-center text-sm font-bold transition" :class="photoForm.kind === 'after' ? 'border-success bg-success/5' : 'border-border-default'">
                <input v-model="photoForm.kind" type="radio" value="after" class="sr-only" />
                بعد الجلسة
              </label>
            </div>
          </template>
        </FormGroup>
        <FormGroup label="الصورة" name="photo" :error="photoForm.errors.photo" required hint="JPG / PNG / WEBP — حتى 8MB.">
          <template #default>
            <div class="space-y-2">
              <img v-if="photoPreview" :src="photoPreview" alt="معاينة" class="max-h-48 rounded-md object-contain ring-1 ring-border-default" />
              <input type="file" accept="image/jpeg,image/png,image/webp" @change="onPhotoChange" class="block w-full text-sm text-text-secondary file:me-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand/10 file:text-brand file:font-medium hover:file:bg-brand/15" />
              <p v-if="photoSizeError" class="text-xs text-danger font-medium">{{ photoSizeError }}</p>
            </div>
          </template>
        </FormGroup>
        <FormGroup label="ملاحظة (اختياري)" name="caption" :error="photoForm.errors.caption">
          <template #default>
            <textarea v-model="photoForm.caption" rows="2" maxlength="500" class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm" />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="photoModalOpen = false">إلغاء</Button>
        <Button :disabled="!photoForm.photo || photoForm.processing" @click="submitPhoto">{{ photoForm.processing ? 'جاري الرفع…' : 'رفع' }}</Button>
      </template>
    </Modal>

    <ConfirmModal
      :open="confirmDelete"
      title="حذف الصورة"
      message="هل أنت متأكد من حذف هذه الصورة نهائيًا؟"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDeletePhoto"
    />
  </AdminShell>
</template>
