<script setup>
import { User as UserIcon } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { PageHeader } from '@/Components/foundation'

defineProps({
  doctors: { type: Array, default: () => [] },
})

const TEAM_ROLE_LABEL = {
  doctor: 'طبيب',
  nurse: 'ممرّض',
  physiotherapist: 'أخصّائي علاج طبيعي',
}

function roleLabel(d) {
  return TEAM_ROLE_LABEL[d.team_role] ?? 'طبيب'
}
</script>

<template>
  <ClientShell>
    <div class="p-4 space-y-4">
      <PageHeader title="الفريق الطبي" description="تعرّف على أعضاء فريقنا — أطبّاء وممرّضين وأخصّائيين." />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <article
          v-for="d in doctors"
          :key="d.id"
          class="bg-surface-card rounded-2xl shadow-sm overflow-hidden border-2 border-brand/10 hover:border-brand/40 hover:shadow-md transition"
        >
          <!-- Avatar / fallback -->
          <div
            v-if="d.image_path"
            class="w-full aspect-square bg-cover bg-center"
            :style="{ backgroundImage: `url(/storage/${d.image_path})` }"
            role="img"
            :aria-label="d.user?.name"
          />
          <div v-else class="w-full aspect-square flex items-center justify-center bg-brand/10 text-brand">
            <UserIcon class="w-16 h-16" aria-hidden="true" />
          </div>

          <div class="p-4 space-y-1.5">
            <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-brand/10 text-brand">{{ roleLabel(d) }}</span>
            <h3 class="text-base font-extrabold text-text-primary">{{ d.user?.name }}</h3>
            <p class="text-xs text-text-secondary">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
            <p v-if="d.bio" class="text-xs text-text-tertiary line-clamp-2 leading-relaxed">{{ d.bio }}</p>
          </div>
        </article>
      </div>

      <p v-if="doctors.length === 0" class="text-center text-text-secondary py-6">
        لا يوجد أعضاء حاليًا.
      </p>
    </div>
  </ClientShell>
</template>
