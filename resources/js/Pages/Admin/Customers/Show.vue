<script setup>
import { ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import { Pencil } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { h } from 'vue'
import {
  PageHeader,
  DataTable,
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
})

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

const apptColumns = [
  { key: 'start_at',      label: 'الموعد' },
  { key: 'doctor',        label: 'الطبيب' },
  { key: 'service',       label: 'الخدمة' },
  { key: 'status',        label: 'الحالة' },
  { key: 'delivery_mode', label: 'طريقة التقديم' },
  { key: 'price',         label: 'السعر' },
  ...(props.canViewMedical ? [{ key: 'record',  label: 'السجل الطبي' }] : []),
]

// Medical profile form (chronic + allergies)
const medForm = useForm({
  chronic_conditions: props.customer.customer_profile?.chronic_conditions ?? '',
  allergies: props.customer.customer_profile?.allergies ?? '',
})

function saveMedical() {
  medForm.put(`/admin/customers/${props.customer.id}/profile/medical`, { preserveScroll: true })
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

      <!-- Medical entries — AdminDataTable -->
      <FormSection v-if="canViewMedical && medicalEntries" title="السجل الطبي للزيارات">
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
        <DataTable :columns="apptColumns" :rows="appointments.data" empty-text="لا توجد مواعيد.">
          <template #cell-start_at="{ row }">{{ formatDateTime(row.start_at) }}</template>
          <template #cell-doctor="{ row }">{{ row.doctor?.user?.name ?? '—' }}</template>
          <template #cell-service="{ row }">{{ row.service?.name ?? '—' }}</template>
          <template #cell-status="{ row }">
            <StatusBadge :type="statusVariant(row.status)" :label="statusLabel(row.status)" />
          </template>
          <template #cell-delivery_mode="{ row }">
            <StatusBadge :type="row.delivery_mode === 'home' ? 'warning' : 'info'" :label="deliveryLabel(row.delivery_mode)" />
          </template>
          <template #cell-price="{ row }">{{ row.price_at_booking }} ₪</template>
          <template #cell-record="{ row }">
            <a
              v-if="row.status === 'completed'"
              :href="`/admin/appointments/${row.id}/medical-entry/create`"
              class="text-sm text-brand underline"
            >إضافة/فتح</a>
            <span v-else class="text-text-tertiary">—</span>
          </template>
        </DataTable>

        <div v-if="appointments.links && appointments.links.length > 3" class="mt-4 flex gap-1 justify-center">
          <template v-for="link in appointments.links" :key="link.label">
            <component
              :is="link.url ? 'a' : 'span'"
              :href="link.url ?? undefined"
              class="px-3 py-1 text-sm rounded-md border border-border-default"
              :class="link.active ? 'bg-brand text-white' : 'bg-surface-card text-text-secondary hover:bg-surface-sunken'"
              v-html="link.label"
            />
          </template>
        </div>
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
  </AdminShell>
</template>
