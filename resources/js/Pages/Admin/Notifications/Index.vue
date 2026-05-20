<script setup>
import { computed } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminShell from '@/Layouts/AdminShell.vue'
import { PageHeader } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  feed: { type: Object, required: true },
  filters: { type: Object, required: true },
  categories: { type: Array, required: true },
})

const categoryLabel = {
  appointment: 'المواعيد',
  payment: 'المدفوعات',
  medical: 'السجل الطبي',
  system: 'النظام',
}

const unreadCount = computed(() => usePage().props?.notifications?.unread_count ?? 0)

function applyFilter(category) {
  router.get('/admin/notifications', {
    ...(category ? { category } : {}),
    ...(props.filters.unread ? { unread: 1 } : {}),
  }, { preserveScroll: true })
}

function toggleUnread() {
  router.get('/admin/notifications', {
    ...(props.filters.category ? { category: props.filters.category } : {}),
    ...(props.filters.unread ? {} : { unread: 1 }),
  }, { preserveScroll: true })
}

function markAllRead() {
  router.post('/admin/notifications/mark-all-read', {}, { preserveScroll: true })
}

function openRow(row) {
  router.post(`/admin/notifications/${row.id}/read`)
}

function timeAgo(iso) {
  if (!iso) return ''
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60) return 'الآن'
  if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`
  if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`
  return new Date(iso).toLocaleDateString('ar-SA')
}
</script>

<template>
  <AdminShell>
    <div class="p-6 space-y-4">
      <div class="flex items-center justify-between">
        <PageHeader title="الإشعارات" :description="`غير مقروءة: ${unreadCount}`" />
        <Button v-if="unreadCount > 0" variant="outline" size="sm" @click="markAllRead">
          تعليم الكل كمقروء
        </Button>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button :variant="!filters.category ? 'default' : 'outline'" size="sm" @click="applyFilter(null)">الكل</Button>
        <Button
          v-for="c in categories"
          :key="c"
          :variant="filters.category === c ? 'default' : 'outline'"
          size="sm"
          @click="applyFilter(c)"
        >{{ categoryLabel[c] }}</Button>
        <Button :variant="filters.unread ? 'default' : 'outline'" size="sm" class="ms-auto" @click="toggleUnread">
          {{ filters.unread ? 'إظهار الكل' : 'غير المقروءة فقط' }}
        </Button>
      </div>

      <ul class="divide-y divide-border-default bg-surface-card rounded-lg shadow-sm">
        <li v-if="feed.data.length === 0" class="p-6 text-center text-text-secondary">
          لا إشعارات حتى الآن.
        </li>
        <li
          v-for="row in feed.data"
          :key="row.id"
          class="p-4 flex items-start gap-3 cursor-pointer hover:bg-surface-page"
          @click="openRow(row)"
        >
          <span v-if="!row.read_at" class="mt-2 h-2 w-2 rounded-full bg-brand shrink-0" />
          <span v-else class="mt-2 h-2 w-2 rounded-full bg-transparent shrink-0" />
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium text-text-primary">{{ row.data.title }}</div>
            <div class="text-sm text-text-secondary truncate">{{ row.data.body }}</div>
          </div>
          <div class="text-xs text-text-tertiary shrink-0">{{ timeAgo(row.created_at) }}</div>
        </li>
      </ul>

      <div v-if="feed.last_page > 1" class="flex justify-center gap-2">
        <Button
          v-for="link in feed.links"
          :key="link.label"
          :variant="link.active ? 'default' : 'outline'"
          size="sm"
          :disabled="!link.url"
          @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
        >
          <span v-html="link.label" />
        </Button>
      </div>
    </div>
  </AdminShell>
</template>
