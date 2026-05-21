<script setup>
import { computed, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { CheckCircle2, X } from 'lucide-vue-next'

const page = usePage()

// Tracks the currently-displayed success message + a timer ref so a fresh
// flash arriving while the previous toast is still on screen replaces it
// cleanly (cancels the old timer, restarts the auto-dismiss).
const visible = ref(false)
const message = ref('')
let dismissTimer = null

const flashMessage = computed(() => page.props?.flash?.success ?? null)

watch(flashMessage, (val) => {
  if (!val) return
  message.value = val
  visible.value = true
  if (dismissTimer) clearTimeout(dismissTimer)
  dismissTimer = setTimeout(() => { visible.value = false }, 3500)
}, { immediate: true })

function dismiss() {
  visible.value = false
  if (dismissTimer) {
    clearTimeout(dismissTimer)
    dismissTimer = null
  }
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="-translate-y-4 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="-translate-y-4 opacity-0"
    >
      <div
        v-if="visible"
        role="status"
        aria-live="polite"
        class="fixed top-4 inset-inline-0 z-50 mx-auto w-fit max-w-[calc(100%-2rem)] pointer-events-none"
      >
        <div
          class="pointer-events-auto inline-flex items-center gap-3 rounded-full bg-success text-white shadow-lg px-5 py-3 ring-4 ring-success/20"
        >
          <span class="grid place-items-center w-8 h-8 rounded-full bg-white/20">
            <CheckCircle2 class="w-5 h-5" aria-hidden="true" />
          </span>
          <p class="text-sm font-bold flex-1">{{ message }}</p>
          <button
            type="button"
            class="grid place-items-center w-7 h-7 rounded-full hover:bg-white/15 transition"
            aria-label="إغلاق"
            @click="dismiss"
          >
            <X class="w-4 h-4" aria-hidden="true" />
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
