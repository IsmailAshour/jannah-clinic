<script setup>
import { computed } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import { ArrowRight, Mail, Phone, User as UserIcon, Trash2 } from 'lucide-vue-next'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader, StatusBadge } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  message: { type: Object, required: true },
})

const page = usePage()
const isManager = computed(() => page.props?.auth?.user?.role === 'manager')

const statusBadge = computed(() => {
  switch (props.message.status) {
    case 'new': return { type: 'warning', label: 'جديدة' }
    case 'read': return { type: 'info', label: 'مقروءة' }
    case 'replied': return { type: 'success', label: 'تم الرد' }
    case 'archived': return { type: 'danger', label: 'مؤرشفة' }
    default: return { type: 'info', label: props.message.status }
  }
})

function formatDate(s) {
  if (!s) return '—'
  try { return new Date(s).toLocaleString('ar-SA', { dateStyle: 'long', timeStyle: 'short' }) } catch (_) { return s }
}

function markStatus(next) {
  router.post(`/admin/messages/${props.message.id}/status`, { status: next }, { preserveScroll: true })
}

function destroyMessage() {
  if (!confirm('حذف هذه الرسالة نهائيًا؟')) return
  router.delete(`/admin/messages/${props.message.id}`)
}

const mailtoHref = computed(() => {
  const subject = encodeURIComponent(`Re: ${props.message.subject}`)
  const body = encodeURIComponent(`\n\n---\n${props.message.body}`)
  return `mailto:${props.message.email}?subject=${subject}&body=${body}`
})
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-6 max-w-3xl">
      <PageHeader :title="message.subject" :description="`من: ${message.name}`">
        <template #action>
          <Link href="/admin/messages" class="text-sm text-text-secondary hover:text-text-primary inline-flex items-center gap-1">
            <ArrowRight class="h-4 w-4 rtl:rotate-180" aria-hidden="true" />
            <span>كل الرسائل</span>
          </Link>
        </template>
      </PageHeader>

      <!-- Meta panel -->
      <div class="bg-surface-card rounded-lg shadow-sm p-5 space-y-4">
        <div class="flex items-center justify-between flex-wrap gap-2">
          <StatusBadge :type="statusBadge.type" :label="statusBadge.label" />
          <span class="text-xs text-text-tertiary" dir="ltr">{{ formatDate(message.created_at) }}</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
          <div class="flex items-center gap-2 text-text-secondary">
            <UserIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ message.name }}</span>
            <span v-if="message.user" class="text-xs text-text-tertiary">(عميل مسجَّل)</span>
          </div>
          <div class="flex items-center gap-2 text-text-secondary">
            <Mail class="h-4 w-4" aria-hidden="true" />
            <a :href="`mailto:${message.email}`" class="text-brand underline" dir="ltr">{{ message.email }}</a>
          </div>
          <div v-if="message.phone" class="flex items-center gap-2 text-text-secondary">
            <Phone class="h-4 w-4" aria-hidden="true" />
            <a :href="`tel:${message.phone}`" class="text-brand underline" dir="ltr">{{ message.phone }}</a>
          </div>
          <div v-if="message.replied_at" class="text-xs text-text-tertiary">
            تم الرد: {{ formatDate(message.replied_at) }} <span v-if="message.handler"> · بواسطة {{ message.handler.name }}</span>
          </div>
        </div>
      </div>

      <!-- Body -->
      <div class="bg-surface-card rounded-lg shadow-sm p-5">
        <p class="text-sm font-semibold text-text-primary mb-2">نص الرسالة</p>
        <p class="text-sm text-text-primary whitespace-pre-wrap leading-relaxed">{{ message.body }}</p>
      </div>

      <!-- Actions -->
      <div class="flex flex-wrap items-center gap-2 justify-end">
        <a :href="mailtoHref" class="inline-flex">
          <Button variant="outline">
            <Mail class="h-4 w-4" aria-hidden="true" />
            <span>الرد عبر البريد</span>
          </Button>
        </a>
        <template v-if="isManager">
          <Button v-if="message.status !== 'replied'" variant="outline" @click="markStatus('replied')">وسم: تم الرد</Button>
          <Button v-if="message.status !== 'archived'" variant="outline" @click="markStatus('archived')">أرشفة</Button>
          <Button v-if="message.status === 'archived'" variant="outline" @click="markStatus('read')">إلغاء الأرشفة</Button>
          <Button variant="destructive" @click="destroyMessage">
            <Trash2 class="h-4 w-4" aria-hidden="true" />
            <span>حذف</span>
          </Button>
        </template>
      </div>
    </div>
  </AdminShell>
</template>
