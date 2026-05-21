<script setup>
import { computed, ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { Send } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader, FormGroup } from '@/Components/foundation'
import { Input } from '@/Components/ui/input'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  faqs: { type: Array, default: () => [] },
  contact: { type: Object, default: () => ({}) },
})

const page = usePage()
const authedUser = computed(() => page.props?.auth?.user ?? null)
const successFlash = computed(() => page.props?.flash?.success ?? null)

const openIndex = ref(null)
function toggle(i) {
  openIndex.value = openIndex.value === i ? null : i
}

const form = useForm({
  name: authedUser.value?.name ?? '',
  email: authedUser.value?.email ?? '',
  phone: authedUser.value?.phone ?? '',
  subject: '',
  body: '',
})

function submit() {
  form.post('/support/contact', {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('subject', 'body')
    },
  })
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الدعم" description="نحن هنا لمساعدتك." />

      <section v-if="contact.phone || contact.whatsapp || contact.address" class="bg-surface-card rounded-lg shadow-sm p-4 space-y-2">
        <p class="text-sm font-semibold text-text-primary">للتواصل</p>
        <p v-if="contact.phone" class="text-sm text-text-secondary">
          📞 <a :href="`tel:${contact.phone}`" dir="ltr" class="text-brand underline">{{ contact.phone }}</a>
        </p>
        <p v-if="contact.whatsapp" class="text-sm text-text-secondary">
          💬 <a :href="`https://wa.me/${contact.whatsapp}`" target="_blank" rel="noopener" class="text-brand underline">واتساب</a>
        </p>
        <p v-if="contact.address" class="text-sm text-text-secondary">📍 {{ contact.address }}</p>
      </section>

      <!-- Contact form -->
      <section class="bg-surface-card rounded-lg shadow-sm p-4 space-y-3">
        <div>
          <p class="text-sm font-semibold text-text-primary">أرسل لنا رسالة</p>
          <p class="text-xs text-text-secondary">سنرد عليك خلال يوم عمل عبر البريد أو الهاتف.</p>
        </div>

        <div
          v-if="successFlash"
          role="status"
          class="rounded-md border border-brand/30 bg-brand/5 p-3 text-sm text-brand"
        >
          {{ successFlash }}
        </div>

        <form class="space-y-3" @submit.prevent="submit">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <FormGroup label="الاسم" name="name" :error="form.errors.name" required>
              <template #default="{ describedby }">
                <Input id="contact-name" v-model="form.name" name="name" :aria-describedby="describedby" />
              </template>
            </FormGroup>

            <FormGroup label="البريد الإلكتروني" name="email" :error="form.errors.email" required>
              <template #default="{ describedby }">
                <Input id="contact-email" v-model="form.email" type="email" dir="ltr" name="email" :aria-describedby="describedby" />
              </template>
            </FormGroup>
          </div>

          <FormGroup label="الهاتف" name="phone" :error="form.errors.phone" hint="اختياري — يساعدنا بالرد الأسرع.">
            <template #default="{ describedby }">
              <Input id="contact-phone" v-model="form.phone" dir="ltr" name="phone" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <FormGroup label="الموضوع" name="subject" :error="form.errors.subject" required>
            <template #default="{ describedby }">
              <Input id="contact-subject" v-model="form.subject" name="subject" :aria-describedby="describedby" />
            </template>
          </FormGroup>

          <FormGroup label="الرسالة" name="body" :error="form.errors.body" required>
            <template #default="{ describedby }">
              <textarea
                id="contact-body"
                v-model="form.body"
                name="body"
                rows="5"
                maxlength="2000"
                :aria-describedby="describedby"
                class="w-full rounded-md border border-border-default bg-surface-card px-3 py-2 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-brand"
              />
            </template>
          </FormGroup>

          <div class="flex justify-end">
            <Button type="submit" :disabled="form.processing">
              <Send class="h-4 w-4" aria-hidden="true" />
              <span>إرسال</span>
            </Button>
          </div>
        </form>
      </section>

      <section v-if="faqs.length > 0" class="space-y-2">
        <p class="text-sm font-semibold text-text-primary">أسئلة شائعة</p>
        <ul class="bg-surface-card rounded-lg shadow-sm divide-y divide-border-default">
          <li v-for="(f, i) in faqs" :key="i">
            <button
              type="button"
              class="w-full p-4 flex items-center justify-between text-start hover:bg-surface-page transition"
              :aria-expanded="openIndex === i"
              @click="toggle(i)"
            >
              <span class="text-sm font-medium text-text-primary">{{ f.q }}</span>
              <span class="text-text-tertiary text-lg leading-none">{{ openIndex === i ? '−' : '+' }}</span>
            </button>
            <div v-if="openIndex === i" class="px-4 pb-4 text-sm text-text-secondary">
              {{ f.a }}
            </div>
          </li>
        </ul>
      </section>
    </div>
  </ClientShell>
</template>
