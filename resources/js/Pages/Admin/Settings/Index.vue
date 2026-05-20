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
  home_surcharge_pct: { type: [String, Number], default: 30 },
  bank: {
    type: Object,
    default: () => ({ name: '', account_holder: '', iban: '', account_number: '' }),
  },
})

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
