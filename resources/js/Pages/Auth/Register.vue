<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { Check, Mail, Phone, User, UserPlus, X } from 'lucide-vue-next'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PasswordField from '@/Components/auth/PasswordField.vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')

const form = useForm({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

function submit() {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}

const hasEmailOrPhone = computed(() => form.email.trim() !== '' || form.phone.trim() !== '')

const strengthScore = computed(() => {
  const v = form.password
  if (!v) return 0
  let score = 0
  if (v.length >= 8) score++
  if (v.length >= 12) score++
  if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++
  if (/\d/.test(v)) score++
  if (/[^A-Za-z0-9]/.test(v)) score++
  return Math.min(score, 4)
})

const strengthMeta = computed(() => {
  const tiers = [
    { label: 'ضعيفة جدًا', color: 'bg-danger', text: 'text-danger' },
    { label: 'ضعيفة', color: 'bg-danger', text: 'text-danger' },
    { label: 'متوسطة', color: 'bg-warning', text: 'text-warning' },
    { label: 'جيّدة', color: 'bg-info', text: 'text-info' },
    { label: 'قويّة', color: 'bg-success', text: 'text-success' },
  ]
  return tiers[strengthScore.value] ?? tiers[0]
})

const passwordsMatch = computed(() =>
  form.password.length > 0
  && form.password_confirmation.length > 0
  && form.password === form.password_confirmation
)
const passwordsMismatch = computed(() =>
  form.password_confirmation.length > 0
  && form.password !== form.password_confirmation
)
</script>

<template>
  <GuestLayout>
    <Head title="إنشاء حساب" />

    <header class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-text-primary">أنشئ حسابك في {{ clinicName }}</h1>
      <p class="mt-1 text-sm text-text-secondary">احجز مواعيدك، تابع نقاط الولاء، واطّلع على ملفّك الطبي.</p>
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
            <User class="h-4 w-4" aria-hidden="true" />
          </span>
          <Input
            id="name"
            v-model="form.name"
            type="text"
            autocomplete="name"
            autofocus
            class="h-11 ps-9"
            :aria-invalid="!!form.errors.name"
            :aria-describedby="form.errors.name ? 'name-error' : 'name-hint'"
            required
          />
        </div>
        <p v-if="!form.errors.name" id="name-hint" class="mt-1.5 text-xs text-text-tertiary">
          الاسم كما تحبّ أن يظهر في موعدك.
        </p>
        <p v-if="form.errors.name" id="name-error" class="mt-1.5 text-xs text-danger font-medium">
          {{ form.errors.name }}
        </p>
      </div>

      <!-- Contact hint -->
      <div
        class="rounded-md border border-info/30 bg-info/5 px-3 py-2 text-xs"
        :class="hasEmailOrPhone ? 'text-success' : 'text-text-secondary'"
      >
        <span class="font-semibold">ملاحظة:</span>
        تحتاج البريد الإلكتروني أو رقم الجوّال على الأقل — لاستلام التأكيدات واسترداد الحساب.
      </div>

      <!-- Email -->
      <div>
        <label for="email" class="block text-sm font-semibold text-text-primary mb-1.5">
          البريد الإلكتروني
        </label>
        <div class="relative">
          <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
            <Mail class="h-4 w-4" aria-hidden="true" />
          </span>
          <Input
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            dir="ltr"
            placeholder="name@example.com"
            class="h-11 ps-9"
            :aria-invalid="!!form.errors.email"
            :aria-describedby="form.errors.email ? 'email-error' : undefined"
          />
        </div>
        <p v-if="form.errors.email" id="email-error" class="mt-1.5 text-xs text-danger font-medium">
          {{ form.errors.email }}
        </p>
      </div>

      <!-- Phone -->
      <div>
        <label for="phone" class="block text-sm font-semibold text-text-primary mb-1.5">
          رقم الجوال
        </label>
        <div class="relative">
          <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
            <Phone class="h-4 w-4" aria-hidden="true" />
          </span>
          <Input
            id="phone"
            v-model="form.phone"
            type="tel"
            autocomplete="tel"
            dir="ltr"
            placeholder="05xxxxxxxx"
            class="h-11 ps-9"
            :aria-invalid="!!form.errors.phone"
            :aria-describedby="form.errors.phone ? 'phone-error' : undefined"
          />
        </div>
        <p v-if="form.errors.phone" id="phone-error" class="mt-1.5 text-xs text-danger font-medium">
          {{ form.errors.phone }}
        </p>
      </div>

      <!-- Password -->
      <div>
        <PasswordField
          id="password"
          v-model="form.password"
          label="كلمة المرور"
          autocomplete="new-password"
          hint="8 أحرف على الأقل، مزيج بين الأحرف والأرقام يقوّيها."
          :error="form.errors.password"
          required
        />

        <!-- Strength meter -->
        <div v-if="form.password" class="mt-2 space-y-1">
          <div class="flex gap-1" aria-hidden="true">
            <span
              v-for="i in 4"
              :key="i"
              :class="[
                'h-1.5 flex-1 rounded-full transition',
                i <= strengthScore ? strengthMeta.color : 'bg-border-default',
              ]"
            />
          </div>
          <p class="text-xs font-medium" :class="strengthMeta.text">
            قوّة كلمة المرور: {{ strengthMeta.label }}
          </p>
        </div>
      </div>

      <!-- Confirm password -->
      <div>
        <PasswordField
          id="password_confirmation"
          v-model="form.password_confirmation"
          label="تأكيد كلمة المرور"
          autocomplete="new-password"
          :error="form.errors.password_confirmation"
          required
        />
        <p v-if="passwordsMatch" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-success">
          <Check class="h-3.5 w-3.5" aria-hidden="true" />
          كلمتا المرور متطابقتان
        </p>
        <p v-if="passwordsMismatch" class="mt-1.5 inline-flex items-center gap-1 text-xs font-medium text-danger">
          <X class="h-3.5 w-3.5" aria-hidden="true" />
          كلمتا المرور غير متطابقتين
        </p>
      </div>

      <!-- Submit -->
      <Button
        type="submit"
        class="w-full h-11 text-base font-bold gap-2 mt-2"
        :disabled="form.processing"
      >
        <UserPlus class="h-4 w-4" aria-hidden="true" />
        <span>{{ form.processing ? 'جاري الإنشاء…' : 'إنشاء حسابي' }}</span>
      </Button>
    </form>

    <p class="mt-6 text-center text-sm text-text-secondary">
      لديك حساب بالفعل؟
      <Link :href="route('login')" class="font-bold text-brand hover:underline">سجّل الدخول</Link>
    </p>
  </GuestLayout>
</template>
