<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import {
  ArrowLeft, ArrowRight, Calendar, ChevronLeft, ChevronRight, Clock, Home,
  ImagePlus, MapPin, Phone, Stethoscope, Trash2, User as UserIcon, X,
} from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, StatusBadge, Modal, FormGroup, ConfirmModal } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  doctor: { type: Object, required: true },
  date: { type: String, required: true },
  prev_date: { type: String, required: true },
  next_date: { type: String, required: true },
  today: { type: String, required: true },
  appointments: { type: Array, default: () => [] },
})

const page = usePage()
const userRole = computed(() => page.props?.auth?.user?.role ?? null)
const canEditPhotos = computed(() => ['manager', 'doctor'].includes(userRole.value))

const statusMap = {
  requested:   { label: 'بانتظار التأكيد', variant: 'warning' },
  confirmed:   { label: 'مؤكد',            variant: 'success' },
  completed:   { label: 'مكتمل',           variant: 'info'    },
  cancelled:   { label: 'ملغى',            variant: 'danger'  },
  rejected:    { label: 'مرفوض',           variant: 'danger'  },
  no_show:     { label: 'لم يحضر',         variant: 'warning' },
  rescheduled: { label: 'أُعيد جدولته',    variant: 'info'    },
}

function formatDateAr(d) {
  if (!d) return ''
  try { return new Date(d).toLocaleDateString('ar-SA', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }) }
  catch (_) { return d }
}

function goToDate(d) {
  router.get(`/admin/doctors/${props.doctor.id}/day`, { date: d }, { preserveScroll: true })
}

// Date picker for jumping to a specific day
const datePickerOpen = ref(false)
const jumpDate = ref(props.date)
function applyJumpDate() {
  if (jumpDate.value) {
    goToDate(jumpDate.value)
    datePickerOpen.value = false
  }
}

// Photo upload modal
const photoModalOpen = ref(false)
const photoTargetApptId = ref(null)
const photoForm = useForm({ kind: 'before', photo: null, caption: '' })
const photoPreview = ref(null)
const photoSizeError = ref(null)

function openPhotoModal(apptId) {
  photoTargetApptId.value = apptId
  photoForm.reset()
  photoForm.kind = 'before'
  photoPreview.value = null
  photoSizeError.value = null
  photoModalOpen.value = true
}
function onPhotoChange(e) {
  const file = e.target.files?.[0] ?? null
  photoForm.photo = file
  photoSizeError.value = null
  if (file) {
    if (file.size > 8 * 1024 * 1024) {
      photoSizeError.value = `الصورة كبيرة (${(file.size / 1024 / 1024).toFixed(1)}MB). الحدّ الأقصى 8MB.`
      photoForm.photo = null
      e.target.value = ''
      photoPreview.value = null
      return
    }
    const r = new FileReader()
    r.onload = (ev) => { photoPreview.value = ev.target.result }
    r.readAsDataURL(file)
  } else {
    photoPreview.value = null
  }
}
function submitPhoto() {
  if (!photoTargetApptId.value || !photoForm.photo) return
  photoForm.post(`/admin/appointments/${photoTargetApptId.value}/photos`, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { photoModalOpen.value = false; photoPreview.value = null },
  })
}

// Photo delete
const confirmDelete = ref(false)
const deleteTarget = ref(null)   // { apptId, photoId }
function askDeletePhoto(apptId, photoId) {
  deleteTarget.value = { apptId, photoId }
  confirmDelete.value = true
}
function doDeletePhoto() {
  if (!deleteTarget.value) return
  router.delete(`/admin/appointments/${deleteTarget.value.apptId}/photos/${deleteTarget.value.photoId}`, {
    preserveScroll: true,
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
  })
}

// Group photos within each appointment by kind for nicer display
function partitionPhotos(photos) {
  const before = photos.filter(p => p.kind === 'before')
  const after = photos.filter(p => p.kind === 'after')
  return { before, after }
}
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-5">
      <PageHeader :title="`جدول الطبيب: ${doctor.name}`" :description="doctor.specialty || 'متعدّد التخصّصات'">
        <template #action>
          <Link href="/admin/doctors" class="text-sm text-text-secondary hover:text-text-primary inline-flex items-center gap-1">
            <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
            <span>كل الأطباء</span>
          </Link>
        </template>
      </PageHeader>

      <!-- Date navigator -->
      <section class="bg-surface-card rounded-2xl ring-1 ring-border-default p-4 flex items-center justify-between gap-2 flex-wrap">
        <Button variant="outline" size="sm" class="gap-1.5" @click="goToDate(prev_date)">
          <ChevronRight class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
          <span>اليوم السابق</span>
        </Button>
        <div class="text-center flex-1 min-w-0">
          <p class="text-xs text-text-tertiary">{{ date === today ? 'اليوم' : '' }}</p>
          <button type="button" class="text-base font-extrabold text-text-primary hover:text-brand inline-flex items-center gap-1.5" @click="datePickerOpen = true">
            <Calendar class="w-4 h-4 text-brand" aria-hidden="true" />
            {{ formatDateAr(date) }}
          </button>
        </div>
        <Button variant="outline" size="sm" class="gap-1.5" @click="goToDate(next_date)">
          <span>اليوم التالي</span>
          <ChevronLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
        </Button>
        <Button v-if="date !== today" variant="ghost" size="sm" class="w-full sm:w-auto" @click="goToDate(today)">العودة لليوم</Button>
      </section>

      <!-- Timeline -->
      <section v-if="appointments.length === 0" class="bg-surface-card rounded-2xl ring-1 ring-border-default p-12 text-center text-text-secondary">
        <Clock class="w-10 h-10 mx-auto text-brand/30 mb-3" aria-hidden="true" />
        <p class="text-base font-bold">لا مواعيد لـ {{ doctor.name }} في هذا اليوم.</p>
        <p class="text-xs text-text-tertiary mt-1">جرّب يومًا آخر أو افتح صفحة الجدول الأسبوعي.</p>
      </section>

      <ul v-else class="space-y-3">
        <li
          v-for="a in appointments"
          :key="a.id"
          class="bg-surface-card rounded-2xl ring-1 ring-border-default overflow-hidden"
        >
          <!-- Header strip -->
          <div class="flex items-start gap-3 p-4 border-b border-border-default">
            <!-- Time block -->
            <div class="shrink-0 w-16 rounded-xl bg-brand/10 ring-1 ring-brand/15 text-center py-2">
              <div class="text-lg font-extrabold text-brand leading-none" dir="ltr">{{ a.time }}</div>
              <div class="text-[10px] text-text-tertiary mt-0.5">{{ a.service.duration_minutes }} د</div>
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2 flex-wrap">
                <h3 class="text-base font-extrabold text-text-primary truncate">{{ a.service.name }}</h3>
                <StatusBadge :type="statusMap[a.status]?.variant ?? 'info'" :label="statusMap[a.status]?.label ?? a.status" />
              </div>
              <div class="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1 text-xs text-text-secondary">
                <p class="inline-flex items-center gap-1.5">
                  <UserIcon class="w-3 h-3" aria-hidden="true" />
                  {{ a.customer.name }}
                </p>
                <p v-if="a.customer.phone" class="inline-flex items-center gap-1.5">
                  <Phone class="w-3 h-3" aria-hidden="true" />
                  <a :href="`tel:${a.customer.phone}`" dir="ltr" class="text-brand">{{ a.customer.phone }}</a>
                </p>
                <p class="inline-flex items-center gap-1.5">
                  <component :is="a.delivery_mode === 'home' ? Home : MapPin" class="w-3 h-3" aria-hidden="true" />
                  {{ a.delivery_mode === 'home' ? 'منزليّة' : 'في المركز' }}
                </p>
                <p class="inline-flex items-center gap-1.5">
                  <span class="font-bold">{{ a.price_at_booking }} ₪</span>
                </p>
              </div>
            </div>
          </div>

          <!-- Photos section -->
          <div class="p-4 space-y-3">
            <div class="flex items-center justify-between">
              <p class="text-sm font-bold text-text-primary">صور قبل/بعد الجلسة</p>
              <Button v-if="canEditPhotos" size="sm" variant="outline" class="gap-1.5" @click="openPhotoModal(a.id)">
                <ImagePlus class="w-3.5 h-3.5" aria-hidden="true" />
                <span>إضافة صورة</span>
              </Button>
            </div>

            <div v-if="a.photos.length === 0" class="text-xs text-text-tertiary py-2">
              لا صور مرفوعة بعد.
            </div>

            <div v-else class="grid grid-cols-2 gap-3">
              <!-- Before -->
              <div>
                <p class="text-xs font-bold text-text-secondary mb-1.5 inline-flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-warning" aria-hidden="true" />
                  قبل ({{ partitionPhotos(a.photos).before.length }})
                </p>
                <ul v-if="partitionPhotos(a.photos).before.length > 0" class="grid grid-cols-2 gap-2">
                  <li v-for="p in partitionPhotos(a.photos).before" :key="p.id" class="relative group">
                    <a :href="p.file_url" target="_blank" rel="noopener" class="block aspect-square rounded-md overflow-hidden ring-1 ring-border-default">
                      <img :src="p.file_url" :alt="p.caption || 'قبل'" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                    <button
                      v-if="canEditPhotos"
                      type="button"
                      class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-danger text-white grid place-items-center shadow opacity-0 group-hover:opacity-100 transition"
                      aria-label="حذف الصورة"
                      @click.prevent="askDeletePhoto(a.id, p.id)"
                    >
                      <Trash2 class="w-3 h-3" aria-hidden="true" />
                    </button>
                    <p v-if="p.caption" class="mt-1 text-[10px] text-text-tertiary truncate" :title="p.caption">{{ p.caption }}</p>
                  </li>
                </ul>
                <p v-else class="text-[11px] text-text-tertiary">لا صور قبل.</p>
              </div>

              <!-- After -->
              <div>
                <p class="text-xs font-bold text-text-secondary mb-1.5 inline-flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-success" aria-hidden="true" />
                  بعد ({{ partitionPhotos(a.photos).after.length }})
                </p>
                <ul v-if="partitionPhotos(a.photos).after.length > 0" class="grid grid-cols-2 gap-2">
                  <li v-for="p in partitionPhotos(a.photos).after" :key="p.id" class="relative group">
                    <a :href="p.file_url" target="_blank" rel="noopener" class="block aspect-square rounded-md overflow-hidden ring-1 ring-border-default">
                      <img :src="p.file_url" :alt="p.caption || 'بعد'" class="w-full h-full object-cover" loading="lazy" />
                    </a>
                    <button
                      v-if="canEditPhotos"
                      type="button"
                      class="absolute -top-1.5 -end-1.5 w-6 h-6 rounded-full bg-danger text-white grid place-items-center shadow opacity-0 group-hover:opacity-100 transition"
                      aria-label="حذف الصورة"
                      @click.prevent="askDeletePhoto(a.id, p.id)"
                    >
                      <Trash2 class="w-3 h-3" aria-hidden="true" />
                    </button>
                    <p v-if="p.caption" class="mt-1 text-[10px] text-text-tertiary truncate" :title="p.caption">{{ p.caption }}</p>
                  </li>
                </ul>
                <p v-else class="text-[11px] text-text-tertiary">لا صور بعد.</p>
              </div>
            </div>
          </div>
        </li>
      </ul>
    </div>

    <!-- Jump to date modal -->
    <Modal :open="datePickerOpen" title="الانتقال إلى يوم" @update:open="datePickerOpen = $event">
      <FormGroup label="التاريخ" name="jump_date">
        <template #default>
          <Input v-model="jumpDate" type="date" dir="ltr" class="h-11" />
        </template>
      </FormGroup>
      <template #footer>
        <Button variant="outline" @click="datePickerOpen = false">إلغاء</Button>
        <Button :disabled="!jumpDate" @click="applyJumpDate">انتقال</Button>
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
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                @change="onPhotoChange"
                class="block w-full text-sm text-text-secondary file:me-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand/10 file:text-brand file:font-medium hover:file:bg-brand/15"
              />
              <p v-if="photoSizeError" class="text-xs text-danger font-medium">{{ photoSizeError }}</p>
            </div>
          </template>
        </FormGroup>

        <FormGroup label="ملاحظة (اختياري)" name="caption" :error="photoForm.errors.caption">
          <template #default>
            <textarea
              v-model="photoForm.caption"
              rows="2"
              maxlength="500"
              placeholder="مثال: زاوية أماميّة - إضاءة طبيعيّة"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="photoModalOpen = false">إلغاء</Button>
        <Button :disabled="!photoForm.photo || photoForm.processing" @click="submitPhoto">
          {{ photoForm.processing ? 'جاري الرفع…' : 'رفع' }}
        </Button>
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
