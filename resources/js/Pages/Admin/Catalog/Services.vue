<script setup>
import { ref, computed, h, watch } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Search } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  AdminDataTable,
  AdminDataTableColumnHeader,
  AdminDataTableRowActions,
  AdminDataTableViewOptions,
  FormGroup,
  Modal,
  ConfirmModal,
} from '@/Components/foundation'
import { DropdownMenuItem } from '@/Components/ui/dropdown-menu'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  services: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
})

const page = usePage()
const isManager = (() => page.props?.auth?.user?.role === 'manager')()

const rows = computed(() => props.services.map(s => ({
  ...s,
  category_name: s.category?.name ?? '—',
})))

const q = ref('')
const categoryId = ref('')

const filteredRows = computed(() => {
  const term = q.value.trim().toLowerCase()
  return rows.value.filter(r => {
    if (categoryId.value && String(r.category_id) !== String(categoryId.value)) return false
    if (!term) return true
    const haystack = `${r.name ?? ''} ${r.category_name ?? ''}`.toLowerCase()
    return haystack.includes(term)
  })
})

function applyFilters() {
  // Client-side filtering only — handled by computed.
}

function resetFilters() {
  q.value = ''
  categoryId.value = ''
}

// Row selection — header checkbox + per-row checkbox
const SelectAllHeader = (table) => h('input', {
  type: 'checkbox',
  class: 'h-4 w-4 cursor-pointer',
  'aria-label': 'تحديد الكل',
  checked: table.getIsAllPageRowsSelected(),
  indeterminate: table.getIsSomePageRowsSelected() && !table.getIsAllPageRowsSelected(),
  onChange: (e) => table.toggleAllPageRowsSelected(e.target.checked),
})
const SelectRow = (row) => h('input', {
  type: 'checkbox',
  class: 'h-4 w-4 cursor-pointer',
  'aria-label': 'تحديد الصف',
  checked: row.getIsSelected(),
  onChange: (e) => row.toggleSelected(e.target.checked),
})

const showModal = ref(false)
const editingId = ref(null)

const form = useForm({
  category_id: '',
  name: '',
  description: '',
  content: '',
  base_price: 0,
  duration_minutes: 30,
  home_service_enabled: false,
  online_service_enabled: false,
  is_featured: false,
  icon_key: '',
  is_active: true,
  display_order: 0,
  loyalty_enabled: true,
  loyalty_redemption_points: '',
  image: null,
  remove_image: false,
  _method: 'POST',
})

watch(() => form.loyalty_enabled, (v) => {
  if (!v) form.loyalty_redemption_points = ''
})

const currentImagePath = ref(null)
const imagePreview = ref(null)
const imageInputEl = ref(null)

function clearImagePreview() {
  imagePreview.value = null
  if (imageInputEl.value) imageInputEl.value.value = ''
}

function onImageChange(e) {
  const file = e.target.files?.[0] ?? null
  form.image = file
  if (file) {
    const reader = new FileReader()
    reader.onload = (ev) => { imagePreview.value = ev.target.result }
    reader.readAsDataURL(file)
    form.remove_image = false
  } else {
    imagePreview.value = null
  }
}

function clearImageSelection() {
  form.image = null
  clearImagePreview()
}

function markRemoveCurrentImage() {
  form.remove_image = true
  currentImagePath.value = null
}

function openCreate() {
  editingId.value = null
  form.reset()
  form.is_active = true
  form.display_order = 0
  form.duration_minutes = 30
  form.remove_image = false
  currentImagePath.value = null
  clearImagePreview()
  showModal.value = true
}

function openEdit(row) {
  editingId.value = row.id
  form.category_id = row.category_id
  form.name = row.name
  form.description = row.description ?? ''
  form.content = row.content ?? ''
  form.base_price = row.base_price
  form.duration_minutes = row.duration_minutes
  form.home_service_enabled = row.home_service_enabled
  form.online_service_enabled = row.online_service_enabled ?? false
  form.is_featured = row.is_featured ?? false
  form.icon_key = row.icon_key ?? ''
  form.is_active = row.is_active
  form.display_order = row.display_order
  form.loyalty_enabled = row.loyalty_enabled
  form.loyalty_redemption_points = row.loyalty_redemption_points ?? ''
  form.image = null
  form.remove_image = false
  currentImagePath.value = row.image_path ?? null
  clearImagePreview()
  showModal.value = true
}

function submitForm() {
  form.transform((data) => ({
    ...data,
    loyalty_enabled: !!data.loyalty_enabled,
    loyalty_redemption_points: data.loyalty_enabled ? (data.loyalty_redemption_points || null) : null,
    home_service_enabled: !!data.home_service_enabled,
    online_service_enabled: !!data.online_service_enabled,
    is_featured: !!data.is_featured,
    is_active: !!data.is_active,
    remove_image: !!data.remove_image,
  }))
  const onSuccess = () => { showModal.value = false }
  if (editingId.value) {
    form._method = 'PUT'
    form.post(`/admin/catalog/services/${editingId.value}`, {
      forceFormData: true,
      onSuccess,
    })
  } else {
    form._method = 'POST'
    form.post('/admin/catalog/services', {
      forceFormData: true,
      onSuccess,
    })
  }
}

const confirmDelete = ref(false)
const deleteTarget = ref(null)

function askDelete(row) {
  deleteTarget.value = row
  confirmDelete.value = true
}

function doDelete() {
  useForm({}).delete(`/admin/catalog/services/${deleteTarget.value.id}`, {
    onSuccess: () => { confirmDelete.value = false; deleteTarget.value = null },
  })
}

const columns = [
  {
    id: 'select',
    enableHiding: false,
    enableSorting: false,
    header: ({ table }) => SelectAllHeader(table),
    cell: ({ row }) => SelectRow(row),
    meta: { label: 'تحديد', headerClass: 'w-10', cellClass: 'w-10 text-center' },
  },
  {
    accessorKey: 'category_name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الفئة' }),
    meta: { label: 'الفئة' },
  },
  {
    accessorKey: 'name',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'الاسم' }),
    meta: { label: 'الاسم' },
  },
  {
    accessorKey: 'base_price',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'السعر' }),
    cell: ({ row }) => `${row.original.base_price} ₪`,
    meta: { label: 'السعر' },
  },
  {
    accessorKey: 'duration_minutes',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'المدة (د)' }),
    cell: ({ row }) => `${row.original.duration_minutes} دقيقة`,
    meta: { label: 'المدة' },
  },
  {
    accessorKey: 'home_service_enabled',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'خدمة منزلية' }),
    cell: ({ row }) => row.original.home_service_enabled ? 'نعم' : 'لا',
    meta: { label: 'خدمة منزلية' },
  },
  {
    accessorKey: 'online_service_enabled',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'أونلاين' }),
    cell: ({ row }) => row.original.online_service_enabled ? 'نعم' : 'لا',
    meta: { label: 'أونلاين' },
  },
  {
    accessorKey: 'is_featured',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'مميّزة' }),
    cell: ({ row }) => row.original.is_featured ? 'نعم' : 'لا',
    meta: { label: 'مميّزة' },
  },
  {
    accessorKey: 'is_active',
    header: ({ column }) => h(AdminDataTableColumnHeader, { column, title: 'نشطة' }),
    cell: ({ row }) => row.original.is_active ? 'نعم' : 'لا',
    meta: { label: 'نشطة' },
  },
  {
    id: 'actions',
    enableHiding: false,
    header: () => '',
    cell: ({ row }) => h(AdminDataTableRowActions, null, {
      default: () => [
        h(DropdownMenuItem, { onClick: () => openEdit(row.original) }, 'تعديل'),
        h(DropdownMenuItem, { class: 'text-danger', onClick: () => askDelete(row.original) }, 'حذف'),
      ],
    }),
  },
]
</script>

<template>
  <AdminShell>
    <div class="p-4 sm:p-6 space-y-6">
      <PageHeader title="الخدمات" description="إدارة كتالوج الخدمات المُقدَّمة وأسعارها ومدّتها.">
        <template v-if="isManager" #action>
          <Button @click="openCreate">إضافة خدمة</Button>
        </template>
      </PageHeader>

      <div class="bg-surface-card rounded-lg shadow-sm px-4">
        <AdminDataTable
          :columns="columns"
          :data="filteredRows"
          empty-text="لا توجد خدمات بعد."
        >
          <template #toolbar="{ table }">
            <form class="flex flex-wrap items-center justify-between gap-2 w-full" @submit.prevent="applyFilters()">
              <div class="flex flex-wrap items-center gap-2">
                <div class="relative w-72">
                  <Search class="absolute top-1/2 -translate-y-1/2 start-3 h-4 w-4 text-text-tertiary pointer-events-none" aria-hidden="true" />
                  <Input
                    id="q"
                    v-model="q"
                    name="q"
                    placeholder="ابحث في الخدمات…"
                    class="ps-9 h-9"
                  />
                </div>
                <select
                  v-model="categoryId"
                  name="category_id"
                  aria-label="فلتر الفئة"
                  class="h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                >
                  <option value="">كل الفئات</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
                <Button type="submit" size="sm" class="h-9">تطبيق</Button>
                <Button type="button" variant="ghost" size="sm" class="h-9" @click="resetFilters">تفريغ</Button>
              </div>
              <AdminDataTableViewOptions :table="table" />
            </form>
          </template>
        </AdminDataTable>
      </div>
    </div>

    <Modal :open="showModal" :title="editingId ? 'تعديل الخدمة' : 'إضافة خدمة'" @update:open="showModal = $event">
      <form class="space-y-6" @submit.prevent="submitForm">
        <!-- Section 1: Basic info -->
        <section class="space-y-4">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">المعلومات الأساسية</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormGroup label="الفئة" name="category_id" :error="form.errors.category_id" required>
              <template #default="{ describedby }">
                <select
                  id="category_id"
                  v-model="form.category_id"
                  name="category_id"
                  :aria-describedby="describedby"
                  class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
                >
                  <option value="" disabled>اختر الفئة</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </template>
            </FormGroup>
            <FormGroup label="اسم الخدمة" name="name" :error="form.errors.name" required>
              <template #default="{ describedby }">
                <Input id="name" v-model="form.name" name="name" :aria-describedby="describedby" />
              </template>
            </FormGroup>
          </div>
          <FormGroup label="وصف قصير" name="description" :error="form.errors.description" hint="جملة أو جملتان تظهر في بطاقة الخدمة.">
            <template #default="{ describedby }">
              <textarea
                id="description"
                v-model="form.description"
                name="description"
                rows="2"
                maxlength="500"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm"
              />
            </template>
          </FormGroup>
          <FormGroup label="المحتوى التفصيلي" name="content" :error="form.errors.content" hint="يظهر في صفحة الخدمة العامة — اكتب فقرات تشرح الخدمة، خطواتها، النتائج المتوقّعة، والتحضير اللازم.">
            <template #default="{ describedby }">
              <textarea
                id="content"
                v-model="form.content"
                name="content"
                rows="6"
                maxlength="10000"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm leading-relaxed"
              />
            </template>
          </FormGroup>
        </section>

        <!-- Section 2: Image -->
        <section class="space-y-4">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">صورة الخدمة</h3>
          <FormGroup label="رفع صورة" name="image" :error="form.errors.image" hint="JPG / PNG / WEBP — حتى 4MB. تظهر في صفحة الخدمة وبطاقاتها.">
            <template #default="{ describedby }">
              <div class="space-y-2">
                <div v-if="imagePreview" class="relative inline-block">
                  <img :src="imagePreview" alt="معاينة الصورة الجديدة" class="h-32 w-full sm:w-64 object-cover rounded-md border border-border-default" />
                  <button type="button" class="absolute top-1 end-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-danger text-white shadow" aria-label="إلغاء الصورة الجديدة" @click="clearImageSelection">×</button>
                </div>
                <div v-else-if="currentImagePath" class="relative inline-block">
                  <img :src="`/storage/${currentImagePath}`" alt="الصورة الحالية" class="h-32 w-full sm:w-64 object-cover rounded-md border border-border-default" />
                  <button type="button" class="absolute top-1 end-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-danger text-white shadow" aria-label="إزالة الصورة الحالية" @click="markRemoveCurrentImage">×</button>
                </div>
                <input
                  id="image"
                  ref="imageInputEl"
                  type="file"
                  name="image"
                  accept="image/jpeg,image/png,image/webp"
                  :aria-describedby="describedby"
                  class="block w-full text-sm text-text-secondary file:me-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-brand/10 file:text-brand file:font-medium hover:file:bg-brand/15"
                  @change="onImageChange"
                />
              </div>
            </template>
          </FormGroup>
        </section>

        <!-- Section 3: Pricing + duration -->
        <section class="space-y-4">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">التسعير والمدّة</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormGroup label="السعر الأساسي (₪)" name="base_price" :error="form.errors.base_price" required>
              <template #default="{ describedby }">
                <Input id="base_price" v-model.number="form.base_price" type="number" name="base_price" min="0" step="0.01" :aria-describedby="describedby" />
              </template>
            </FormGroup>
            <FormGroup label="المدة (دقيقة)" name="duration_minutes" :error="form.errors.duration_minutes" required>
              <template #default="{ describedby }">
                <Input id="duration_minutes" v-model.number="form.duration_minutes" type="number" name="duration_minutes" min="1" :aria-describedby="describedby" />
              </template>
            </FormGroup>
          </div>
        </section>

        <!-- Section 4: Delivery modes (toggle cards) -->
        <section class="space-y-3">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">طُرُق التقديم المتاحة</h3>
          <p class="text-xs text-text-secondary">اختر الطُرُق التي يمكن للمريض حجزها لهذه الخدمة (في المركز متاحة دائمًا).</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label
              :class="[
                'cursor-pointer rounded-xl border-2 p-3 flex items-start gap-3 transition',
                form.home_service_enabled ? 'border-brand bg-brand/5 ring-2 ring-brand/15' : 'border-border-default hover:border-brand/40',
              ]"
            >
              <input id="home_service_enabled" v-model="form.home_service_enabled" type="checkbox" name="home_service_enabled" class="h-4 w-4 mt-0.5" />
              <div class="min-w-0">
                <p class="text-sm font-bold text-text-primary">زيارة منزلية</p>
                <p class="text-xs text-text-tertiary mt-0.5">يمكن طلب الخدمة في منزل المريض</p>
              </div>
            </label>
            <label
              :class="[
                'cursor-pointer rounded-xl border-2 p-3 flex items-start gap-3 transition',
                form.online_service_enabled ? 'border-brand bg-brand/5 ring-2 ring-brand/15' : 'border-border-default hover:border-brand/40',
              ]"
            >
              <input id="online_service_enabled" v-model="form.online_service_enabled" type="checkbox" name="online_service_enabled" class="h-4 w-4 mt-0.5" />
              <div class="min-w-0">
                <p class="text-sm font-bold text-text-primary">أونلاين (واتساب)</p>
                <p class="text-xs text-text-tertiary mt-0.5">يتواصل الطبيب عبر واتساب وقت الموعد</p>
              </div>
            </label>
          </div>
        </section>

        <!-- Section 5: Display + visibility -->
        <section class="space-y-4">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">العَرض والتنظيم</h3>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <FormGroup label="مفتاح الأيقونة" name="icon_key" :error="form.errors.icon_key">
              <template #default="{ describedby }">
                <Input id="icon_key" v-model="form.icon_key" name="icon_key" dir="ltr" :aria-describedby="describedby" />
              </template>
            </FormGroup>
            <FormGroup label="الترتيب" name="display_order" :error="form.errors.display_order">
              <template #default="{ describedby }">
                <Input id="display_order" v-model.number="form.display_order" type="number" name="display_order" min="0" :aria-describedby="describedby" />
              </template>
            </FormGroup>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label
              :class="[
                'cursor-pointer rounded-xl border-2 p-3 flex items-start gap-3 transition',
                form.is_featured ? 'border-brand bg-brand/5 ring-2 ring-brand/15' : 'border-border-default hover:border-brand/40',
              ]"
            >
              <input id="is_featured" v-model="form.is_featured" type="checkbox" name="is_featured" class="h-4 w-4 mt-0.5" />
              <div class="min-w-0">
                <p class="text-sm font-bold text-text-primary">مميّزة في الصفحة الرئيسية</p>
                <p class="text-xs text-text-tertiary mt-0.5">حتى 4 خدمات تظهر في قسم "خدماتنا المميّزة"</p>
              </div>
            </label>
            <label
              :class="[
                'cursor-pointer rounded-xl border-2 p-3 flex items-start gap-3 transition',
                form.is_active ? 'border-success bg-success/5 ring-2 ring-success/15' : 'border-border-default hover:border-success/40',
              ]"
            >
              <input id="is_active" v-model="form.is_active" type="checkbox" name="is_active" class="h-4 w-4 mt-0.5" />
              <div class="min-w-0">
                <p class="text-sm font-bold text-text-primary">نشطة</p>
                <p class="text-xs text-text-tertiary mt-0.5">عند الإيقاف، تختفي الخدمة من جميع الواجهات</p>
              </div>
            </label>
          </div>
        </section>

        <!-- Section 6: Loyalty -->
        <section class="space-y-3">
          <h3 class="text-sm font-bold text-text-primary border-b border-border-default pb-2">برنامج الولاء</h3>
          <label
            :class="[
              'cursor-pointer rounded-xl border-2 p-3 flex items-start gap-3 transition',
              form.loyalty_enabled ? 'border-warning bg-warning/5 ring-2 ring-warning/15' : 'border-border-default hover:border-warning/40',
            ]"
          >
            <input id="loyalty_enabled" v-model="form.loyalty_enabled" type="checkbox" name="loyalty_enabled" class="h-4 w-4 mt-0.5" />
            <div class="min-w-0">
              <p class="text-sm font-bold text-text-primary">تفعيل الولاء على هذه الخدمة</p>
              <p class="text-xs text-text-tertiary mt-0.5">يكسب المريض نقاط عند حجز هذه الخدمة</p>
            </div>
          </label>
          <FormGroup
            label="نقاط الاستبدال"
            name="loyalty_redemption_points"
            :error="form.errors.loyalty_redemption_points"
            hint="اتركه فارغًا إن أردت كسب النقاط فقط دون السماح بالاستبدال."
          >
            <template #default="{ describedby }">
              <Input
                id="loyalty_redemption_points"
                v-model="form.loyalty_redemption_points"
                type="number"
                min="1"
                name="loyalty_redemption_points"
                dir="ltr"
                :disabled="!form.loyalty_enabled"
                :aria-describedby="describedby"
              />
            </template>
          </FormGroup>
        </section>
      </form>
      <template #footer>
        <Button variant="outline" @click="showModal = false">إلغاء</Button>
        <Button :disabled="form.processing" @click="submitForm">
          {{ editingId ? 'حفظ التعديلات' : 'إضافة' }}
        </Button>
      </template>
    </Modal>

    <ConfirmModal
      :open="confirmDelete"
      title="حذف الخدمة"
      :message="`هل أنت متأكد من حذف خدمة «${deleteTarget?.name}»؟`"
      confirm-text="حذف"
      cancel-text="إلغاء"
      @update:open="confirmDelete = $event"
      @confirm="doDelete"
    />
  </AdminShell>
</template>
