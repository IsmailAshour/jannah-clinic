<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  intent: { type: String, required: true },
  authedHref: { type: String, required: true },
  context: { type: Object, default: () => ({}) },
})

const page = usePage()
const isAuthed = computed(() => !!page.props?.auth?.user)

const guestHref = computed(() => {
  const entries = Object.entries(props.context).filter(([, v]) => v !== null && v !== undefined && v !== '')
  const tail = entries.length === 0
    ? ''
    : '&' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')

  return `/login?intent=${encodeURIComponent(props.intent)}${tail}`
})
</script>

<template>
  <Link
    v-if="isAuthed"
    :href="authedHref"
    v-bind="$attrs"
  ><slot /></Link>
  <Link
    v-else
    :href="guestHref"
    v-bind="$attrs"
  ><slot /></Link>
</template>
