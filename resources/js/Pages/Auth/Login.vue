<script setup>
import { computed } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { LogIn, Mail, UserPlus } from 'lucide-vue-next'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PasswordField from '@/Components/auth/PasswordField.vue'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'
import Checkbox from '@/Components/Checkbox.vue'

const props = defineProps({
  canResetPassword: { type: Boolean, default: false },
  status: { type: String, default: null },
  intent: { type: String, default: null },
  context: { type: Object, default: () => ({}) },
})

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')

const form = useForm({
  identifier: '',
  password: '',
  remember: false,
  intent: props.intent ?? '',
  service: props.context?.service ?? '',
  doctor: props.context?.doctor ?? '',
  category: props.context?.category ?? '',
})

const intentBanner = computed(() => {
  if (!props.intent) return null
  if (props.intent === 'booking') return 'سجّل دخولك لإكمال حجز موعدك.'
  return 'سجّل دخولك للمتابعة.'
})

function submit() {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <GuestLayout>
    <Head title="تسجيل الدخول" />

    <!-- Header -->
    <header class="text-center mb-6">
      <h1 class="text-2xl font-extrabold text-text-primary">أهلًا بعودتك</h1>
      <p class="mt-1 text-sm text-text-secondary">سجّل الدخول لمتابعة مواعيدك مع {{ clinicName }}.</p>
    </header>

    <!-- Status (e.g. after password reset link sent) -->
    <div
      v-if="status"
      role="status"
      class="mb-4 rounded-md border border-success/30 bg-success/5 px-3 py-2 text-sm text-success"
    >
      {{ status }}
    </div>

    <!-- Intent banner (post-login redirect context) -->
    <div
      v-if="intentBanner"
      class="mb-4 rounded-md border border-brand/30 bg-brand/5 px-3 py-2 text-sm text-brand"
    >
      {{ intentBanner }}
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <!-- Identifier -->
      <div>
        <label for="identifier" class="block text-sm font-semibold text-text-primary mb-1.5">
          البريد الإلكتروني أو رقم الجوال
          <span class="text-danger" aria-hidden="true">*</span>
        </label>
        <div class="relative">
          <span class="absolute top-1/2 -translate-y-1/2 start-3 text-text-tertiary pointer-events-none">
            <Mail class="h-4 w-4" aria-hidden="true" />
          </span>
          <Input
            id="identifier"
            v-model="form.identifier"
            type="text"
            autocomplete="username"
            autofocus
            dir="ltr"
            class="h-11 ps-9"
            :aria-invalid="!!form.errors.identifier"
            :aria-describedby="form.errors.identifier ? 'identifier-error' : 'identifier-hint'"
            required
          />
        </div>
        <p v-if="!form.errors.identifier" id="identifier-hint" class="mt-1.5 text-xs text-text-tertiary">
          استعمل البريد الذي سجّلت به أو رقم جوّالك.
        </p>
        <p v-if="form.errors.identifier" id="identifier-error" class="mt-1.5 text-xs text-danger font-medium">
          {{ form.errors.identifier }}
        </p>
      </div>

      <!-- Password -->
      <PasswordField
        id="password"
        v-model="form.password"
        label="كلمة المرور"
        autocomplete="current-password"
        :error="form.errors.password"
        required
      />

      <!-- Row: remember + forgot -->
      <div class="flex items-center justify-between flex-wrap gap-2">
        <label class="flex items-center gap-2 cursor-pointer text-sm text-text-secondary select-none">
          <Checkbox v-model:checked="form.remember" name="remember" />
          <span>ابقني مسجَّلًا</span>
        </label>
        <Link
          v-if="canResetPassword"
          :href="route('password.request')"
          class="text-sm font-medium text-brand hover:underline"
        >
          نسيت كلمة المرور؟
        </Link>
      </div>

      <!-- Submit -->
      <Button
        type="submit"
        class="w-full h-11 text-base font-bold gap-2"
        :disabled="form.processing"
      >
        <LogIn class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
        <span>{{ form.processing ? 'جاري الدخول…' : 'دخول' }}</span>
      </Button>
    </form>

    <!-- Divider -->
    <div class="my-6 flex items-center gap-3 text-xs text-text-tertiary">
      <span class="h-px flex-1 bg-border-default"></span>
      <span>أو</span>
      <span class="h-px flex-1 bg-border-default"></span>
    </div>

    <!-- Register CTA -->
    <Link
      :href="route('register')"
      class="flex items-center justify-center gap-2 h-11 w-full rounded-md border-2 border-brand/30 bg-surface-card text-sm font-bold text-brand hover:bg-brand/5 transition"
    >
      <UserPlus class="h-4 w-4" aria-hidden="true" />
      <span>إنشاء حساب جديد</span>
    </Link>
  </GuestLayout>
</template>
