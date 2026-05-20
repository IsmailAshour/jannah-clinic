<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Pencil } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { h } from 'vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  FormGroup,
  Modal,
  FormSection,
  StatCard,
  StatusBadge,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  customer: { type: Object, required: true },
  appointments: { type: Object, default: () => ({ data: [], links: [] }) },
  stats: { type: Object, default: () => ({ total: 0, completed: 0, noShow: 0, lastVisit: null }) },
  medicalEntries: { type: Object, default: null },
  canViewMedical: { type: Boolean, default: false },
  canEditMedicalProfile: { type: Boolean, default: false },
  addableAppointments: { type: Array, default: () => [] },
  loyaltyBalance: { type: Number, default: 0 },
  loyaltyPreview: { type: Array, default: () => [] },
  loyaltyTotals: { type: Object, default: () => ({ earned: 0, redeemed: 0 }) },
  canAdjustLoyalty: { type: Boolean, default: false },
})

const showAddEntryModal = ref(false)

function openMedicalEntryFor(apptId) {
  showAddEntryModal.value = false
  router.visit(`/admin/appointments/${apptId}/medical-entry/create`)
}

const page = usePage()
// Role lives on the authenticated user — staff is manager/doctor/receptionist;
// only manager can mutate, mirroring the route-level guard. This drives UI
// visibility only (server is authoritative).
const isManager = (() => {
  const role = page.props?.auth?.user?.role
  return role === 'manager'
})()

// One-shot temporary password from session flash — set by store() after creating
// a new customer (Str::password(16) hashed server-side, returned ONCE here).
// The session key is shared via HandleInertiaRequests::share(). Manager must
// share it with the customer; it is NOT persisted anywhere else.
const tempPassword = page.props?.flash?.temp_password ?? null
const passwordCopied = ref(false)
async function copyPassword() {
  if (!tempPassword) return
  try {
    await navigator.clipboard.writeText(tempPassword)
    passwordCopied.value = true
    setTimeout(() => (passwordCopied.value = false), 2000)
  } catch {
    // Clipboard API blocked (e.g. insecure context) — user can still select+copy manually.
  }
}

// Arabic AppointmentStatus map mirrors Admin/Appointments/Index.vue
const statusMap = {
  requested:   { label: 'بانتظار التأكيد', variant: 'warning' },
  confirmed:   { label: 'مؤكد',            variant: 'success' },
  rejected:    { label: 'مرفوض',           variant: 'danger'  },
  completed:   { label: 'مكتمل',           variant: 'info'    },
  cancelled:   { label: 'ملغى',            variant: 'danger'  },
  no_show:     { label: 'لم يحضر',         variant: 'warning' },
  rescheduled: { label: 'أُعيد جدولته',    variant: 'info'    },
}
function statusLabel(v) { return statusMap[v]?.label ?? v }
function statusVariant(v) { return statusMap[v]?.variant ?? 'info' }

function formatDateTime(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('ar-SA', {
    year: 'numeric', month: 'short', day: 'numeric',
  })
}

function deliveryLabel(mode) { return mode === 'home' ? 'منزلية' : 'في العيادة' }

function genderLabel(g) {
  if (!g) return '—'
  if (g === 'male') return 'ذكر'
  if (g === 'female') return 'أنثى'
  return g
}

// Toggle active — POST to the toggle-active route
function toggleActive() {
  router.post(`/admin/customers/${props.customer.id}/toggle-active`, {}, { preserveScroll: true })
}

// Edit modal
const showEdit = ref(false)
const form = useForm({
  name: props.customer.name ?? '',
  email: props.customer.email ?? '',
  phone: props.customer.phone ?? '',
  is_active: !!props.customer.is_active,
  date_of_birth: props.customer.customer_profile?.date_of_birth ?? '',
  gender: props.customer.customer_profile?.gender ?? '',
  notes: props.customer.customer_profile?.notes ?? '',
})

function openEdit() {
  form.name = props.customer.name ?? ''
  form.email = props.customer.email ?? ''
  form.phone = props.customer.phone ?? ''
  form.is_active = !!props.customer.is_active
  form.date_of_birth = props.customer.customer_profile?.date_of_birth ?? ''
  form.gender = props.customer.customer_profile?.gender ?? ''
  form.notes = props.customer.customer_profile?.notes ?? ''
  form.clearErrors()
  showEdit.value = true
}

function submitEdit() {
  form.put(`/admin/customers/${props.customer.id}`, {
    preserveScroll: true,
    onSuccess: () => { showEdit.value = false },
  })
}

function goToAppointmentsPage(p) {
  router.get(`/admin/customers/${props.customer.id}`, { page: p }, { preserveScroll: true, preserveState: true })
}

const apptColumns = [
  {
    accessorKey: 'start_at',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الموعد' }),
    cell: ({ row }) => formatDateTime(row.original.start_at),
    meta: { label: 'الموعد' },
  },
  {
    accessorKey: 'doctor',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الطبيب' }),
    cell: ({ row }) => row.original.doctor?.user?.name ?? '—',
    meta: { label: 'الطبيب' },
  },
  {
    accessorKey: 'service',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الخدمة' }),
    cell: ({ row }) => row.original.service?.name ?? '—',
    meta: { label: 'الخدمة' },
  },
  {
    accessorKey: 'status',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الحالة' }),
    cell: ({ row }) => h(StatusBadge, {
      type: statusVariant(row.original.status),
      label: statusLabel(row.original.status),
    }),
    meta: { label: 'الحالة' },
  },
  {
    accessorKey: 'delivery_mode',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'طريقة التقديم' }),
    cell: ({ row }) => h(StatusBadge, {
      type: row.original.delivery_mode === 'home' ? 'warning' : 'info',
      label: deliveryLabel(row.original.delivery_mode),
    }),
    meta: { label: 'طريقة التقديم' },
  },
  {
    accessorKey: 'price_at_booking',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'السعر' }),
    cell: ({ row }) => `${row.original.price_at_booking} ₪`,
    meta: { label: 'السعر' },
  },
  ...(props.canViewMedical ? [{
    id: 'record',
    enableHiding: false,
    header: () => 'السجل الطبي',
    cell: ({ row }) => row.original.status === 'completed'
      ? h('a', {
          href: `/admin/appointments/${row.original.id}/medical-entry/create`,
          class: 'text-sm text-brand underline',
        }, 'إضافة/فتح')
      : h('span', { class: 'text-text-tertiary' }, '—'),
  }] : []),
]

// Medical profile form (chronic + allergies)
const medForm = useForm({
  chronic_conditions: props.customer.customer_profile?.chronic_conditions ?? '',
  allergies: props.customer.customer_profile?.allergies ?? '',
})

function saveMedical() {
  medForm.put(`/admin/customers/${props.customer.id}/profile/medical`, { preserveScroll: true })
}

const showAdjustModal = ref(false)
const adjustForm = useForm({ delta: '', note: '' })

function openAdjust() {
  adjustForm.reset()
  adjustForm.clearErrors()
  showAdjustModal.value = true
}
function submitAdjust() {
  adjustForm
    .transform((data) => ({ delta: Number(data.delta), note: data.note }))
    .post(`/admin/customers/${props.customer.id}/loyalty/adjust`, {
      preserveScroll: true,
      onSuccess: () => { showAdjustModal.value = false },
    })
}

const reasonLabel = {
  earned_from_payment: 'كسب من زيارة',
  redeemed_for_appointment: 'استبدال للحجز',
  clawback_from_refund: 'سحب بعد استرداد',
  refund_reversal: 'إعادة بعد إلغاء',
  adjustment_by_manager: 'تعديل من الإدارة',
}

// Medical entries — AdminDataTable column defs
const entryColumns = [
  {
    accessorKey: 'date',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'تاريخ الزيارة' }),
    cell: ({ row }) => row.original.date ? new Date(row.original.date).toLocaleDateString('ar-SA') : '—',
    meta: { label: 'تاريخ الزيارة' },
  },
  {
    accessorKey: 'visible_summary',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الخلاصة' }),
    cell: ({ row }) => {
      const t = row.original.visible_summary ?? ''
      return t.length > 80 ? t.slice(0, 80) + '…' : t
    },
    meta: { label: 'الخلاصة' },
  },
  {
    accessorKey: 'prescriptions_count',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'وصفات' }),
    meta: { label: 'وصفات' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => h(DropdownMenuItem, {
        onClick: () => router.visit(`/admin/medical-entries/${row.original.id}/edit`),
      }, 'فتح السجل'),
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6">
      <!--
        One-shot temp-password banner — appears only on the redirect that follows
        a successful customer-create. Refreshing the page clears the flash, and
        navigating away discards it. There is no other way to retrieve it later
        (by design); the customer should change it after first login.
      -->
      <div v-if="tempPassword" class="rounded-md border border-warning bg-warning/10 p-4 space-y-2">
        <div class="text-sm font-semibold text-text-primary">تم إنشاء العميل — كلمة مرور مؤقتة</div>
        <div class="flex items-center gap-3 flex-wrap">
          <code class="font-mono text-base px-3 py-1 rounded bg-surface-card border border-border-default select-all" dir="ltr">{{ tempPassword }}</code>
          <Button type="button" variant="outline" @click="copyPassword">
            {{ passwordCopied ? 'تم النسخ ✓' : 'نسخ' }}
          </Button>
        </div>
        <p class="text-xs text-text-secondary">شارِك كلمة المرور مع العميل الآن — لن تُعرض مرة أخرى. يستطيع العميل تغييرها بعد تسجيل الدخول.</p>
      </div>

      <PageHeader :title="customer.name">
        <template #action>
          <div class="flex gap-2">
            <Button v-if="isManager" variant="outline" @click="toggleActive">
              {{ customer.is_active ? 'تعطيل' : 'تفعيل' }}
            </Button>
            <Button v-if="isManager" @click="openEdit">
              <Pencil class="h-4 w-4" aria-hidden="true" />
              <span>تعديل</span>
            </Button>
          </div>
        </template>
      </PageHeader>

      <!-- Profile block -->
      <FormSection title="بيانات العميل" description="عرض فقط — استخدم زر «تعديل» للتغيير.">
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-text-secondary">الاسم</dt>
            <dd class="text-text-primary">{{ customer.name }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">الحالة</dt>
            <dd>
              <StatusBadge
                :type="customer.is_active ? 'success' : 'danger'"
                :label="customer.is_active ? 'نشط' : 'غير نشط'"
              />
            </dd>
          </div>
          <div>
            <dt class="text-text-secondary">البريد الإلكتروني</dt>
            <dd class="text-text-primary" dir="ltr">{{ customer.email || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">الهاتف</dt>
            <dd class="text-text-primary" dir="ltr">{{ customer.phone || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">تاريخ الميلاد</dt>
            <dd class="text-text-primary">{{ formatDate(customer.customer_profile?.date_of_birth) }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">الجنس</dt>
            <dd class="text-text-primary">{{ genderLabel(customer.customer_profile?.gender) }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">تاريخ الانضمام</dt>
            <dd class="text-text-primary">{{ formatDateTime(customer.created_at) }}</dd>
          </div>
          <div class="md:col-span-2">
            <dt class="text-text-secondary">ملاحظات الطاقم</dt>
            <dd class="text-text-primary whitespace-pre-line">{{ customer.customer_profile?.notes || '—' }}</dd>
          </div>
        </dl>
      </FormSection>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <StatCard title="إجمالي الحجوزات" :value="stats.total ?? 0" />
        <StatCard title="مكتملة" :value="stats.completed ?? 0" />
        <StatCard title="لم يحضر" :value="stats.noShow ?? 0" />
        <StatCard title="آخر زيارة" :value="formatDateTime(stats.lastVisit)" />
      </div>

      <!-- Medical profile (chronic + allergies) -->
      <FormSection v-if="canViewMedical" title="الملف الطبي" description="حقول حساسة مُشفّرة عند التخزين.">
        <form v-if="canEditMedicalProfile" class="space-y-4" @submit.prevent="saveMedical">
          <FormGroup label="الأمراض المزمنة" name="chronic_conditions" :error="medForm.errors.chronic_conditions">
            <template #default="{ describedby }">
              <textarea
                id="chronic_conditions"
                v-model="medForm.chronic_conditions"
                name="chronic_conditions"
                rows="3"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
              />
            </template>
          </FormGroup>
          <FormGroup label="الحساسية" name="allergies" :error="medForm.errors.allergies">
            <template #default="{ describedby }">
              <textarea
                id="allergies"
                v-model="medForm.allergies"
                name="allergies"
                rows="3"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
              />
            </template>
          </FormGroup>
          <div class="flex justify-end">
            <Button :disabled="medForm.processing">حفظ</Button>
          </div>
        </form>
        <dl v-else class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div>
            <dt class="text-text-secondary">الأمراض المزمنة</dt>
            <dd class="text-text-primary whitespace-pre-line">{{ customer.customer_profile?.chronic_conditions || '—' }}</dd>
          </div>
          <div>
            <dt class="text-text-secondary">الحساسية</dt>
            <dd class="text-text-primary whitespace-pre-line">{{ customer.customer_profile?.allergies || '—' }}</dd>
          </div>
        </dl>
      </FormSection>

      <!-- Loyalty section -->
      <FormSection v-if="canViewMedical" title="نقاط الولاء" description="رصيد العميل وآخر 10 حركات.">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الرصيد الحالي</p>
            <p class="text-3xl font-bold text-text-primary">{{ loyaltyBalance }}</p>
          </div>
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الكسب الإجمالي</p>
            <p class="text-2xl font-semibold text-success">+{{ loyaltyTotals.earned }}</p>
          </div>
          <div class="bg-surface-page rounded-lg p-4">
            <p class="text-sm text-text-secondary">الاستبدال الإجمالي</p>
            <p class="text-2xl font-semibold text-danger">−{{ loyaltyTotals.redeemed }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <Button v-if="canAdjustLoyalty" size="sm" variant="outline" @click="openAdjust">+ تعديل يدوي</Button>
          <Button size="sm" variant="ghost" @click="router.visit(`/admin/customers/${customer.id}/loyalty`)">
            عرض الكل
          </Button>
        </div>

        <ul class="divide-y divide-border-default">
          <li v-if="loyaltyPreview.length === 0" class="py-4 text-center text-text-tertiary text-sm">
            لا توجد حركات نقاط بعد.
          </li>
          <li v-for="row in loyaltyPreview" :key="row.id" class="py-3 flex items-center gap-3">
            <span :class="['text-sm font-semibold w-16', row.points_delta > 0 ? 'text-success' : 'text-danger']">
              {{ row.points_delta > 0 ? '+' : '' }}{{ row.points_delta }}
            </span>
            <div class="flex-1 min-w-0">
              <div class="text-sm text-text-primary">{{ reasonLabel[row.reason] || row.reason }}</div>
              <div class="text-xs text-text-tertiary truncate">
                {{ row.notes || '—' }} {{ row.actor_name ? `· بواسطة ${row.actor_name}` : '' }}
              </div>
            </div>
            <div class="text-xs text-text-tertiary shrink-0">
              {{ new Date(row.created_at).toLocaleDateString('ar-SA') }}
            </div>
          </li>
        </ul>
      </FormSection>

      <!-- Medical entries — AdminDataTable -->
      <FormSection v-if="canViewMedical && medicalEntries" title="السجل الطبي للزيارات">
        <div v-if="addableAppointments.length > 0" class="flex justify-end">
          <Button variant="default" size="sm" @click="showAddEntryModal = true">
            + إضافة سجل لزيارة مكتملة
          </Button>
        </div>
        <AdminDataTable
          :columns="entryColumns"
          :data="medicalEntries.data"
          filter-column="visible_summary"
          filter-placeholder="ابحث في الخلاصات…"
          :server-meta="medicalEntries.meta"
          :on-page-change="(p) => router.get(`/admin/customers/${customer.id}`, { page: p }, { preserveState: true, preserveScroll: true })"
          empty-text="لا توجد سجلات طبية."
        />
      </FormSection>

      <!-- Appointments table -->
      <FormSection title="المواعيد">
        <AdminDataTable
          :columns="apptColumns"
          :data="appointments.data"
          :server-meta="appointments"
          :on-page-change="goToAppointmentsPage"
          empty-text="لا توجد مواعيد."
        />
      </FormSection>
    </div>

    <!-- Edit modal -->
    <Modal :open="showEdit" title="تعديل بيانات العميل" @update:open="showEdit = $event">
      <form class="space-y-4" @submit.prevent="submitEdit">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email">
          <template #default="{ describedby }">
            <Input id="email" v-model="form.email" type="email" name="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="phone" v-model="form.phone" name="phone" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="نشط" name="is_active" :error="form.errors.is_active">
          <template #default>
            <input id="is_active" v-model="form.is_active" type="checkbox" name="is_active" class="h-4 w-4" />
          </template>
        </FormGroup>

        <FormGroup label="تاريخ الميلاد" name="date_of_birth" :error="form.errors.date_of_birth">
          <template #default="{ describedby }">
            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" name="date_of_birth" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الجنس" name="gender" :error="form.errors.gender">
          <template #default="{ describedby }">
            <select
              id="gender"
              v-model="form.gender"
              name="gender"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            >
              <option value="">—</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </template>
        </FormGroup>

        <FormGroup label="ملاحظات" name="notes" :error="form.errors.notes" hint="ملاحظات داخلية للطاقم.">
          <template #default="{ describedby }">
            <textarea
              id="notes"
              v-model="form.notes"
              name="notes"
              rows="4"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showEdit = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitEdit">حفظ التعديلات</Button>
      </template>
    </Modal>

    <Modal :open="showAdjustModal" title="تعديل رصيد النقاط" @update:open="showAdjustModal = $event">
      <form class="space-y-4" @submit.prevent="submitAdjust">
        <FormGroup label="التغيير (موجب للإضافة، سالب للحسم)" name="delta" :error="adjustForm.errors.delta" required>
          <template #default="{ describedby }">
            <Input id="adj-delta" v-model="adjustForm.delta" type="number" name="delta" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>
        <FormGroup label="السبب" name="note" :error="adjustForm.errors.note" required>
          <template #default="{ describedby }">
            <textarea
              id="adj-note"
              v-model="adjustForm.note"
              name="note"
              rows="3"
              maxlength="500"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>
      </form>
      <template #footer>
        <Button variant="outline" @click="showAdjustModal = false">إلغاء</Button>
        <Button :disabled="adjustForm.processing" @click="submitAdjust">حفظ</Button>
      </template>
    </Modal>

    <!-- Add medical entry modal — picks from completed appointments without an entry -->
    <Modal
      :open="showAddEntryModal"
      title="اختر زيارة لإضافة سجلها الطبي"
      @update:open="showAddEntryModal = $event"
    >
      <div v-if="addableAppointments.length === 0" class="text-sm text-text-secondary">
        لا توجد زيارات مكتملة بانتظار التوثيق.
      </div>
      <ul v-else class="divide-y divide-border-default">
        <li
          v-for="appt in addableAppointments"
          :key="appt.id"
          class="flex items-center justify-between py-3"
        >
          <div class="text-sm">
            <div class="text-text-primary">{{ formatDateTime(appt.start_at) }}</div>
            <div class="text-text-secondary">{{ appt.service || '—' }}</div>
          </div>
          <Button size="sm" @click="openMedicalEntryFor(appt.id)">إضافة</Button>
        </li>
      </ul>
      <template #footer>
        <Button variant="outline" @click="showAddEntryModal = false">إغلاق</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
