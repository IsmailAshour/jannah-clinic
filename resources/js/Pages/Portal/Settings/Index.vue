<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const pwdForm = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
})

function submitPassword() {
  pwdForm.put('/portal/settings/password', {
    preserveScroll: true,
    onSuccess: () => pwdForm.reset(),
  })
}

const page = usePage()
const flashSuccess = page.props?.flash?.success
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الإعدادات" description="إدارة حسابك." />

      <div v-if="flashSuccess" class="bg-success/10 text-success text-sm p-3 rounded-md">
        {{ flashSuccess }}
      </div>

      <section class="bg-surface-card rounded-lg shadow-sm p-4 space-y-4">
        <h2 class="text-sm font-semibold text-text-primary">تغيير كلمة المرور</h2>
        <form class="space-y-3" @submit.prevent="submitPassword">
          <FormGroup label="كلمة المرور الحاليّة" name="current_password" :error="pwdForm.errors.current_password" required>
            <template #default="{ describedby }">
              <Input id="current_password" v-model="pwdForm.current_password" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <FormGroup label="كلمة المرور الجديدة" name="password" :error="pwdForm.errors.password" required>
            <template #default="{ describedby }">
              <Input id="password" v-model="pwdForm.password" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <FormGroup label="تأكيد كلمة المرور" name="password_confirmation" :error="pwdForm.errors.password_confirmation" required>
            <template #default="{ describedby }">
              <Input id="password_confirmation" v-model="pwdForm.password_confirmation" type="password" :aria-describedby="describedby" />
            </template>
          </FormGroup>
          <div class="flex justify-end">
            <Button type="submit" :disabled="pwdForm.processing">تحديث</Button>
          </div>
        </form>
      </section>

    </div>
  </ClientShell>
</template>
