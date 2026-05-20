<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

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
const flashSuccess = page.props?.flash?.success
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="حسابي" description="حدِّث بياناتك الشخصيّة." />

      <div v-if="flashSuccess" class="bg-success/10 text-success text-sm p-3 rounded-md">
        {{ flashSuccess }}
      </div>

      <form class="bg-surface-card rounded-lg shadow-sm p-4 space-y-4" @submit.prevent="submit">
        <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
          <template #default="{ describedby }">
            <Input id="name" v-model="form.name" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email">
          <template #default="{ describedby }">
            <Input id="email" v-model="form.email" type="email" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الهاتف" name="phone" :error="form.errors.phone">
          <template #default="{ describedby }">
            <Input id="phone" v-model="form.phone" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="تاريخ الميلاد" name="date_of_birth" :error="form.errors.date_of_birth">
          <template #default="{ describedby }">
            <Input id="date_of_birth" v-model="form.date_of_birth" type="date" dir="ltr" :aria-describedby="describedby" />
          </template>
        </FormGroup>

        <FormGroup label="الجنس" name="gender" :error="form.errors.gender">
          <template #default="{ describedby }">
            <select
              id="gender"
              v-model="form.gender"
              :aria-describedby="describedby"
              class="w-full h-9 rounded-md border border-border-default bg-surface-card px-3 text-sm"
            >
              <option value="">—</option>
              <option value="male">ذكر</option>
              <option value="female">أنثى</option>
            </select>
          </template>
        </FormGroup>

        <div class="flex justify-end">
          <Button type="submit" :disabled="form.processing">حفظ التعديلات</Button>
        </div>
      </form>
    </div>
  </ClientShell>
</template>
