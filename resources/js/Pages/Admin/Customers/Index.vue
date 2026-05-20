<script setup>
import { ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import { Eye, UserPlus } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  DataTable,
  PageStates,
  StatusBadge,
  Modal,
  FormGroup,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  customers: { type: Object, default: () => ({ data: [], links: [] }) },
  filters: { type: Object, default: () => ({}) },
})

// Role check: only managers can create — server is authoritative; this hides
// the UI affordance for non-managers (matches the route-level `role:manager` guard).
const page = usePage()
const isManager = (() => page.props?.auth?.user?.role === 'manager')()

const q = ref(props.filters.q ?? '')
const status = ref(props.filters.status ?? '')

function applyFilters() {
  router.get(
    '/admin/customers',
    {
      q: q.value || undefined,
      status: status.value || undefined,
    },
    { preserveScroll: true, replace: true }
  )
}

function resetFilters() {
  q.value = ''
  status.value = ''
  applyFilters()
}

const columns = [
  { key: 'name', label: 'الاسم' },
  { key: 'phone', label: 'الهاتف' },
  { key: 'email', label: 'البريد' },
  { key: 'status', label: 'الحالة' },
  { key: 'actions', label: 'إجراءات', align: 'end' },
]

// Create modal: form mirrors the Show edit form's editable fields except
// `is_active` (new customers default active) and password (auto-generated server-side
// via Str::password(16); shown ONCE via flash on the redirected Show page).
const showCreate = ref(false)
const form = useForm({
  name: '',
  email: '',
  phone: '',
  date_of_birth: '',
  gender: '',
  notes: '',
})

function openCreate() {
  form.reset()
  form.clearErrors()
  showCreate.value = true
}

function submitCreate() {
  form.post('/admin/customers', {
    // The controller redirects to the new customer's Show page on success,
    // so the modal is unmounted by navigation — no explicit close needed.
    preserveScroll: false,
  })
}
</script>

<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="العملاء">
        <template v-if="isManager" #action>
          <Button @click="openCreate">
            <UserPlus class="h-4 w-4" aria-hidden="true" />
            <span>إضافة عميل</span>
          </Button>
        </template>
      </PageHeader>

      <!-- Filter bar -->
      <form class="mb-6 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
        <div class="flex flex-col gap-1">
          <label for="q" class="text-xs font-medium text-text-secondary">بحث (الاسم، البريد، الهاتف)</label>
          <Input id="q" v-model="q" name="q" placeholder="ابحث..." class="w-64" />
        </div>
        <div class="flex flex-col gap-1">
          <label for="status" class="text-xs font-medium text-text-secondary">الحالة</label>
          <select
            id="status"
            v-model="status"
            name="status"
            class="rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
          >
            <option value="">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">غير نشط</option>
          </select>
        </div>
        <Button type="submit">بحث</Button>
        <Button type="button" variant="outline" @click="resetFilters">تفريغ</Button>
      </form>

      <PageStates :is-empty="customers.data.length === 0">
        <template #empty>
          <div class="text-text-secondary p-6">لا يوجد عملاء.</div>
        </template>

        <DataTable :columns="columns" :rows="customers.data">
          <template #cell-name="{ row }">{{ row.name }}</template>
          <template #cell-phone="{ row }">{{ row.phone || '—' }}</template>
          <template #cell-email="{ row }">{{ row.email || '—' }}</template>
          <template #cell-status="{ row }">
            <StatusBadge
              :type="row.is_active ? 'success' : 'danger'"
              :label="row.is_active ? 'نشط' : 'غير نشط'"
            />
          </template>
          <template #cell-actions="{ row }">
            <div class="flex justify-end gap-2">
              <Link :href="`/admin/customers/${row.id}`" class="inline-flex items-center gap-1 text-sm text-brand hover:underline">
                <Eye class="h-4 w-4" aria-hidden="true" />
                <span>عرض</span>
              </Link>
            </div>
          </template>
        </DataTable>

        <!-- Pagination links -->
        <div v-if="customers.links && customers.links.length > 3" class="mt-4 flex gap-1 justify-center">
          <template v-for="link in customers.links" :key="link.label">
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

    <!-- Create-customer modal (manager only) -->
    <Modal :open="showCreate" title="إضافة عميل" @update:open="showCreate = $event">
      <form class="space-y-4" @submit.prevent="submitCreate">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="c-name" v-model="form.name" name="name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email" hint="يجب توفير البريد أو الهاتف.">
          <template #default="{ describedby }">
            <Input id="c-email" v-model="form.email" type="email" name="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="c-phone" v-model="form.phone" name="phone" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="تاريخ الميلاد" name="date_of_birth" :error="form.errors.date_of_birth">
          <template #default="{ describedby }">
            <Input id="c-dob" v-model="form.date_of_birth" type="date" name="date_of_birth" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الجنس" name="gender" :error="form.errors.gender">
          <template #default="{ describedby }">
            <select
              id="c-gender"
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

        <FormGroup label="ملاحظات" name="notes" :error="form.errors.notes" hint="ملاحظات داخلية للطاقم (اختياري).">
          <template #default="{ describedby }">
            <textarea
              id="c-notes"
              v-model="form.notes"
              name="notes"
              rows="3"
              :aria-describedby="describedby"
              class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
            />
          </template>
        </FormGroup>

        <p class="text-xs text-text-secondary">
          سيتم توليد كلمة مرور مؤقتة تلقائيًا — تُعرض مرة واحدة في صفحة العميل بعد الإنشاء. شارِكها مع العميل.
        </p>
      </form>
      <template #footer>
        <Button variant="outline" @click="showCreate = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitCreate">إنشاء</Button>
      </template>
    </Modal>
  </AdminShell>
</template>
