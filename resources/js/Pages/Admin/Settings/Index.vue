<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import {
  PageHeader,
  FormGroup,
  FormSection,
} from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'

const props = defineProps({
  clinic_name: { type: String, default: 'عيادة جنّة' },
  clinic_logo_path: { type: String, default: null },
  home_surcharge_pct: { type: [String, Number], default: 30 },
  bank: {
    type: Object,
    default: () => ({ name: '', account_holder: '', iban: '', account_number: '' }),
  },
})

const clinicForm = useForm({
  clinic_name: props.clinic_name,
})

function saveClinic() {
  clinicForm.put('/admin/settings/clinic')
}

const logoForm = useForm({
  logo: null,
})

function pickLogo(event) {
  logoForm.logo = event.target.files?.[0] ?? null
}

function uploadLogo() {
  if (!logoForm.logo) return
  logoForm.post('/admin/settings/clinic/logo', {
    forceFormData: true,
    onSuccess: () => { logoForm.reset() },
  })
}

const surchargeForm = useForm({
  home_surcharge_pct: props.home_surcharge_pct,
})

function saveSurcharge() {
  surchargeForm.put('/admin/settings/surcharge')
}

const bankForm = useForm({
  bank_name: props.bank.name ?? '',
  bank_account_holder: props.bank.account_holder ?? '',
  bank_iban: props.bank.iban ?? '',
  bank_account_number: props.bank.account_number ?? '',
})

function saveBank() {
  bankForm.put('/admin/settings/bank')
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-8">
      <PageHeader title="إعدادات العيادة" />

      <FormSection title="هويّة العيادة" description="الاسم الذي يظهر للعميل في الترويسة وأعلى كل صفحة.">
        <form class="space-y-4" @submit.prevent="saveClinic">
          <FormGroup label="اسم العيادة" name="clinic_name" :error="clinicForm.errors.clinic_name" required>
            <template #default="{ describedby }">
              <Input
                id="clinic_name"
                v-model="clinicForm.clinic_name"
                name="clinic_name"
                maxlength="120"
                :aria-describedby="describedby"
                class="w-full max-w-md"
              />
            </template>
          </FormGroup>
        </form>

        <div class="flex justify-end">
          <Button :disabled="clinicForm.processing" @click="saveClinic">حفظ الاسم</Button>
        </div>

        <div class="border-t border-border-default pt-4 space-y-3">
          <p class="text-sm font-semibold text-text-primary">شعار العيادة</p>
          <div class="flex items-center gap-3">
            <img
              v-if="clinic_logo_path"
              :src="`/storage/${clinic_logo_path}`"
              alt="شعار العيادة"
              class="w-16 h-16 rounded-md object-cover bg-surface-page"
            />
            <div v-else class="w-16 h-16 rounded-md bg-surface-page flex items-center justify-center text-text-tertiary text-xs">
              لا يوجد
            </div>
            <div class="flex-1 space-y-2">
              <input
                type="file"
                accept="image/*"
                @change="pickLogo"
                class="block text-sm"
              />
              <p v-if="logoForm.errors.logo" class="text-xs text-danger">{{ logoForm.errors.logo }}</p>
              <Button size="sm" :disabled="!logoForm.logo || logoForm.processing" @click="uploadLogo">
                رفع الشعار
              </Button>
            </div>
          </div>
        </div>
      </FormSection>

      <FormSection title="إعدادات الخدمة المنزلية">
        <form class="space-y-4" @submit.prevent="saveSurcharge">
          <FormGroup label="نسبة رسوم الخدمة المنزلية (%)" name="home_surcharge_pct" :error="surchargeForm.errors.home_surcharge_pct" required>
            <template #default="{ describedby }">
              <Input
                id="home_surcharge_pct"
                v-model.number="surchargeForm.home_surcharge_pct"
                type="number"
                name="home_surcharge_pct"
                min="0"
                max="100"
                dir="ltr"
                :aria-describedby="describedby"
                class="w-36"
              />
            </template>
          </FormGroup>
        </form>

        <div class="flex justify-end">
          <Button :disabled="surchargeForm.processing" @click="saveSurcharge">حفظ الإعداد</Button>
        </div>
      </FormSection>

      <FormSection title="بيانات الحساب البنكي" description="تُعرض للعميل في صفحة الدفع لكل موعد.">
        <form class="space-y-4" @submit.prevent="saveBank">
          <FormGroup label="اسم البنك" name="bank_name" :error="bankForm.errors.bank_name">
            <template #default="{ describedby }">
              <Input id="bank_name" v-model="bankForm.bank_name" name="bank_name" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <FormGroup label="اسم صاحب الحساب" name="bank_account_holder" :error="bankForm.errors.bank_account_holder">
            <template #default="{ describedby }">
              <Input id="bank_account_holder" v-model="bankForm.bank_account_holder" name="bank_account_holder" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <FormGroup label="IBAN" name="bank_iban" :error="bankForm.errors.bank_iban">
            <template #default="{ describedby }">
              <Input id="bank_iban" v-model="bankForm.bank_iban" name="bank_iban" dir="ltr" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <FormGroup label="رقم الحساب" name="bank_account_number" :error="bankForm.errors.bank_account_number">
            <template #default="{ describedby }">
              <Input id="bank_account_number" v-model="bankForm.bank_account_number" name="bank_account_number" dir="ltr" :aria-describedby="describedby" />
            </template>
          </FormGroup>
        </form>

        <div class="flex justify-end">
          <Button :disabled="bankForm.processing" @click="saveBank">حفظ بيانات البنك</Button>
        </div>
      </FormSection>
    </div>
  </AdminShell>
</template>
