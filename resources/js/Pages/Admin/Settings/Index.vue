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
})

const form = useForm({
  home_surcharge_pct: props.home_surcharge_pct,
})

function saveSurcharge() {
  form.put('/admin/settings/surcharge')
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-8">
      <PageHeader title="إعدادات العيادة" />

      <FormSection title="إعدادات الخدمة المنزلية">
        <form class="space-y-4" @submit.prevent="saveSurcharge">
          <FormGroup label="نسبة رسوم الخدمة المنزلية (%)" name="home_surcharge_pct" :error="form.errors.home_surcharge_pct" required>
            <template #default="{ describedby }">
              <Input
                id="home_surcharge_pct"
                v-model.number="form.home_surcharge_pct"
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
          <Button :disabled="form.processing" @click="saveSurcharge">حفظ الإعداد</Button>
        </div>
      </FormSection>
    </div>
  </AdminShell>
</template>
