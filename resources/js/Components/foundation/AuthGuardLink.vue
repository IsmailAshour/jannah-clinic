<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  intent: { type: String, required: true },
  authedHref: { type: String, required: true },
  staffHref: { type: String, default: null },
  context: { type: Object, default: () => ({}) },
})

const page = usePage()
const role = computed(() => page.props?.auth?.user?.role ?? null)
const isAuthed = computed(() => !!page.props?.auth?.user)
const isStaff = computed(() => ['manager', 'doctor', 'receptionist'].includes(role.value))

const targetHref = computed(() => {
  if (!isAuthed.value) {
    const entries = Object.entries(props.context).filter(([, v]) => v !== null && v !== undefined && v !== '')
    const tail = entries.length === 0
      ? ''
      : '&' + entries.map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&')
    return `/login?intent=${encodeURIComponent(props.intent)}${tail}`
  }
  if (isStaff.value && props.staffHref) {
    return props.staffHref
  }
  return props.authedHref
})
</script>

<template>
  <Link :href="targetHref" v-bind="$attrs"><slot /></Link>
</template>
