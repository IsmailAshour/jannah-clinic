<script setup>
import { computed } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import { Calendar, CheckCircle2, KeyRound, Mail, Phone, Save, User as UserIcon, X } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  user: { type: Object, required: true },
})

const form = useForm({
  name: props.user.name ?? '',
  email: props.user.email ?? '',
  phone: props.user.phone ?? '',
  date_of_birth: props.user.profile?.date_of_birth ?? '',
  gender: props.user.profile?.gender ?? '',
})

function submit() {
  form.put('/portal/profile', { preserveScroll: true })
}

const page = usePage()
const flashSuccess = computed(() => page.props?.flash?.success ?? null)

const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')

const initial = computed(() => {
  const n = (props.user.name ?? '').trim()
  return n ? Array.from(n)[0] : 'م'
})

const hasEmailOrPhone = computed(() => (form.email ?? '').trim() !== '' || (form.phone ?? '').trim() !== '')
</script>

<template>
  <ClientShell>
    <div class="px-4 py-6 pb-12 space-y-5 max-w-2xl mx-auto">
      <!-- Welcome header (mirrors Register heading style) -->
      <header class="text-center">
        <h1 class="text-2xl font-extrabold text-text-primary">حسابك في {{ clinicName }}</h1>
        <p class="mt-1 text-sm text-text-secondary">حدِّث بياناتك الشخصيّة وأمان حسابك من هنا.</p>
      </header>

      <!-- Avatar identity card -->
      <section class="bg-surface-card rounded-2xl shadow-md ring-1 ring-border-default p-5 flex items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-brand text-white grid place-items-center text-2xl font-extrabold shrink-0 ring-4 ring-white shadow-sm">
          {{ initial }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-lg font-extrabold text-text-primary truncate">{{ user.name }}</p>
          <p v-if="user.email" class="text-sm text-text-secondary truncate" dir="ltr">{{ user.email }}</p>
          <p v-if="user.phone" class="text-sm text-text-secondary truncate" dir="ltr">{{ user.phone }}</p>
        </div>
      </section>

      <!-- Success flash -->
      <div v-if="flashSuccess" role="status" class="rounded-md border border-success/30 bg-success/5 px-3 py-2.5 text-sm text-success inline-flex items-start gap-2">
        <CheckCircle2 class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" />
        <span>{{ flashSuccess }}</span>
      </div>

      <!-- Personal info form (Register-inspired styling) -->
      <section class="bg-surface-card rounded-2xl shadow-md ring-1 ring-border-default p-6 sm:p-7">
        <header class="mb-5">
          <h2 class="text-base font-bold text-text-primary inline-flex items-center gap-2">
            <UserIcon class="w-4 h-4 text-brand" aria-hidden="true" />
            البيانات الشخصيّة
          </h2>
          <p class="text-xs text-text-secondary mt-0.5">تظهر هذه البيانات للطاقم الطبي عند الحجز.</p>
        </header>

        <form class="space-y-4" @submit.prevent="submit">
          <!-- Name -->
          <div>
            <label for="name" class="block text-sm font-semibold text-text-primary mb-1.5">
              الاسم الكامل
              <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <div class="relative">
              <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
                <UserIcon class="h-4 w-4" aria-hidden="true" />
              </span>
              <Input
                id="name"
                v-model="form.name"
                class="h-11 ps-9"
                :aria-invalid="!!form.errors.name"
                :aria-describedby="form.errors.name ? 'name-error' : undefined"
                required
              />
            </div>
            <p v-if="form.errors.name" id="name-error" class="mt-1.5 inline-flex items-center gap-1 text-xs text-danger font-medium">
              <X class="w-3.5 h-3.5" aria-hidden="true" />
              {{ form.errors.name }}
            </p>
          </div>

          <!-- Contact hint card (mirrors Register) -->
          <div
            class="rounded-md border border-info/30 bg-info/5 px-3 py-2 text-xs"
            :class="hasEmailOrPhone ? 'text-success' : 'text-text-secondary'"
          >
            <span class="font-semibold">ملاحظة:</span>
            تحتاج البريد الإلكتروني أو رقم الجوّال على الأقل لاسترداد الحساب واستلام التأكيدات.
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-semibold text-text-primary mb-1.5">البريد الإلكتروني</label>
              <div class="relative">
                <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
                  <Mail class="h-4 w-4" aria-hidden="true" />
                </span>
                <Input
                  id="email"
                  v-model="form.email"
                  type="email"
                  dir="ltr"
                  placeholder="name@example.com"
                  class="h-11 ps-9"
                  :aria-invalid="!!form.errors.email"
                  :aria-describedby="form.errors.email ? 'email-error' : undefined"
                />
              </div>
              <p v-if="form.errors.email" id="email-error" class="mt-1.5 inline-flex items-center gap-1 text-xs text-danger font-medium">
                <X class="w-3.5 h-3.5" aria-hidden="true" />
                {{ form.errors.email }}
              </p>
            </div>

            <!-- Phone -->
            <div>
              <label for="phone" class="block text-sm font-semibold text-text-primary mb-1.5">رقم الجوال</label>
              <div class="relative">
                <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
                  <Phone class="h-4 w-4" aria-hidden="true" />
                </span>
                <Input
                  id="phone"
                  v-model="form.phone"
                  dir="ltr"
                  placeholder="05xxxxxxxx"
                  class="h-11 ps-9"
                  :aria-invalid="!!form.errors.phone"
                  :aria-describedby="form.errors.phone ? 'phone-error' : undefined"
                />
              </div>
              <p v-if="form.errors.phone" id="phone-error" class="mt-1.5 inline-flex items-center gap-1 text-xs text-danger font-medium">
                <X class="w-3.5 h-3.5" aria-hidden="true" />
                {{ form.errors.phone }}
              </p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Date of birth -->
            <div>
              <label for="date_of_birth" class="block text-sm font-semibold text-text-primary mb-1.5">تاريخ الميلاد</label>
              <div class="relative">
                <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
                  <Calendar class="h-4 w-4" aria-hidden="true" />
                </span>
                <Input
                  id="date_of_birth"
                  v-model="form.date_of_birth"
                  type="date"
                  dir="ltr"
                  class="h-11 ps-9"
                  :aria-invalid="!!form.errors.date_of_birth"
                  :aria-describedby="form.errors.date_of_birth ? 'dob-error' : undefined"
                />
              </div>
              <p v-if="form.errors.date_of_birth" id="dob-error" class="mt-1.5 inline-flex items-center gap-1 text-xs text-danger font-medium">
                <X class="w-3.5 h-3.5" aria-hidden="true" />
                {{ form.errors.date_of_birth }}
              </p>
            </div>

            <!-- Gender -->
            <div>
              <label for="gender" class="block text-sm font-semibold text-text-primary mb-1.5">الجنس</label>
              <select
                id="gender"
                v-model="form.gender"
                class="w-full h-11 rounded-md border border-border-default bg-surface-card px-3 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
                :aria-invalid="!!form.errors.gender"
                :aria-describedby="form.errors.gender ? 'gender-error' : undefined"
              >
                <option value="">— غير محدّد —</option>
                <option value="male">ذكر</option>
                <option value="female">أنثى</option>
              </select>
              <p v-if="form.errors.gender" id="gender-error" class="mt-1.5 inline-flex items-center gap-1 text-xs text-danger font-medium">
                <X class="w-3.5 h-3.5" aria-hidden="true" />
                {{ form.errors.gender }}
              </p>
            </div>
          </div>

          <Button
            type="submit"
            class="w-full h-11 text-base font-bold gap-2 mt-2"
            :disabled="form.processing"
          >
            <Save class="w-4 h-4" aria-hidden="true" />
            <span>{{ form.processing ? 'جاري الحفظ…' : 'حفظ التعديلات' }}</span>
          </Button>
        </form>
      </section>

      <!-- Account security shortcut -->
      <section class="bg-surface-card rounded-2xl shadow-md ring-1 ring-border-default p-5">
        <div class="flex items-start gap-3">
          <span class="w-10 h-10 rounded-full bg-brand/10 text-brand grid place-items-center shrink-0">
            <KeyRound class="w-5 h-5" aria-hidden="true" />
          </span>
          <div class="flex-1 min-w-0">
            <h2 class="text-base font-bold text-text-primary">أمان الحساب</h2>
            <p class="text-xs text-text-secondary mt-0.5 mb-3">حدِّث كلمة المرور من صفحة الإعدادات.</p>
            <Link
              href="/portal/settings"
              class="inline-flex items-center justify-center px-4 py-2 rounded-md border-2 border-brand/30 text-sm font-bold text-brand hover:bg-brand/5 transition"
            >
              إدارة الإعدادات
            </Link>
          </div>
        </div>
      </section>
    </div>
  </ClientShell>
</template>
