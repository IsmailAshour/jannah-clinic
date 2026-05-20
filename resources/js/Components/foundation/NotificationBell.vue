<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Bell } from 'lucide-vue-next'

const props = defineProps({
  href: { type: String, required: true },
})
const page = usePage()
const count = computed(() => page.props?.notifications?.unread_count ?? 0)
</script>

<template>
  <Link
    :href="props.href"
    class="relative inline-flex h-9 w-9 items-center justify-center rounded-md text-text-secondary hover:text-text-primary hover:bg-surface-page"
    aria-label="الإشعارات"
  >
    <Bell class="h-5 w-5" aria-hidden="true" />
    <span
      v-if="count > 0"
      data-testid="bell-badge"
      class="absolute -top-1 -inline-end-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1.5 text-[10px] font-bold text-white"
    >{{ count > 99 ? '99+' : count }}</span>
  </Link>
</template>
