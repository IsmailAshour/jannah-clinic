<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowRight, Clock, Coins, Home, Sparkles, Star, User as UserIcon } from 'lucide-vue-next'

const TEAM_ROLE_LABEL = {
  doctor: 'طبيب',
  nurse: 'ممرّض',
  physiotherapist: 'أخصّائي علاج طبيعي',
}
function roleLabel(d) {
  return TEAM_ROLE_LABEL[d.team_role] ?? 'طبيب'
}
import ClientShell from '@/Layouts/ClientShell.vue'
import { AuthGuardLink } from '@/Components/foundation'
import { Button } from '@/Components/ui/button'
import { iconForCategory, colorClassForCategory } from '@/lib/categoryIcons'

const props = defineProps({
  service: { type: Object, required: true },
  related: { type: Array, default: () => [] },
})

const heroBg = computed(() => props.service.image_path ? `url(/storage/${props.service.image_path})` : null)

const contentParagraphs = computed(() => {
  if (!props.service.content) return []
  return props.service.content.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean)
})

const loyaltyEarn = computed(() => {
  if (!props.service.loyalty_enabled) return null
  return Math.floor(Number(props.service.base_price) || 0)
})
</script>

<template>
  <ClientShell>
    <Head :title="service.name" />

    <article class="pb-8">
      <!-- Hero image -->
      <div
        v-if="heroBg"
        class="w-full aspect-[16/9] bg-cover bg-center"
        :style="{ backgroundImage: heroBg }"
        role="img"
        :aria-label="service.name"
      />
      <div
        v-else
        :class="['w-full aspect-[16/9] flex items-center justify-center', colorClassForCategory(service.category)]"
      >
        <component :is="iconForCategory(service.category)" class="w-20 h-20 opacity-70" aria-hidden="true" />
      </div>

      <!-- Header card (overlapping hero) -->
      <div class="-mt-6 mx-4">
        <div class="bg-surface-card rounded-2xl shadow-md p-5 space-y-3">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="space-y-1">
              <Link
                :href="`/services?category=${service.category_id}`"
                class="text-xs font-bold text-brand hover:underline inline-flex items-center gap-1"
              >
                <span>{{ service.category?.name }}</span>
              </Link>
              <h1 class="text-2xl font-extrabold text-text-primary">{{ service.name }}</h1>
              <p v-if="service.description" class="text-sm text-text-secondary leading-relaxed">{{ service.description }}</p>
            </div>
          </div>

          <!-- Quick facts row -->
          <dl class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 border-t border-border-default">
            <div class="text-center">
              <dt class="text-xs text-text-tertiary">السعر</dt>
              <dd class="text-base font-extrabold text-brand">{{ service.base_price }} ₪</dd>
            </div>
            <div class="text-center">
              <dt class="text-xs text-text-tertiary">المدة</dt>
              <dd class="text-sm font-bold text-text-primary inline-flex items-center gap-1 justify-center">
                <Clock class="w-3.5 h-3.5" aria-hidden="true" />
                {{ service.duration_minutes }} دقيقة
              </dd>
            </div>
            <div class="text-center">
              <dt class="text-xs text-text-tertiary">خدمة منزلية</dt>
              <dd class="text-sm font-bold inline-flex items-center gap-1 justify-center" :class="service.home_service_enabled ? 'text-success' : 'text-text-tertiary'">
                <Home class="w-3.5 h-3.5" aria-hidden="true" />
                {{ service.home_service_enabled ? 'متاحة' : 'غير متاحة' }}
              </dd>
            </div>
            <div v-if="loyaltyEarn" class="text-center">
              <dt class="text-xs text-text-tertiary">نقاط الولاء</dt>
              <dd class="text-sm font-bold text-warning inline-flex items-center gap-1 justify-center">
                <Coins class="w-3.5 h-3.5" aria-hidden="true" />
                +{{ loyaltyEarn }} نقطة
              </dd>
            </div>
          </dl>

          <!-- Primary CTA -->
          <AuthGuardLink
            intent="booking"
            :authed-href="`/portal/booking?service=${service.id}`"
            :context="{ service: service.id }"
            class="block"
          >
            <Button class="w-full h-12 text-base font-bold gap-2">
              <Sparkles class="w-4 h-4" aria-hidden="true" />
              <span>احجز هذه الخدمة</span>
            </Button>
          </AuthGuardLink>
        </div>
      </div>

      <!-- Detailed content -->
      <section v-if="contentParagraphs.length > 0" class="mx-4 mt-5">
        <h2 class="text-base font-bold text-text-primary mb-2">عن هذه الخدمة</h2>
        <div class="bg-surface-card rounded-2xl shadow-sm p-5 space-y-3">
          <p
            v-for="(p, i) in contentParagraphs"
            :key="i"
            class="text-sm text-text-primary leading-relaxed whitespace-pre-line"
          >{{ p }}</p>
        </div>
      </section>

      <!-- Loyalty card -->
      <section
        v-if="service.loyalty_enabled"
        class="mx-4 mt-5 rounded-2xl p-5 text-white shadow-md bg-gradient-to-bl from-warning/95 via-warning to-warning/80"
      >
        <p class="text-xs font-bold text-white/90">برنامج الولاء</p>
        <p class="mt-1 text-base font-extrabold">
          اكسب <span class="text-2xl">{{ loyaltyEarn }}</span> نقطة عند حجز هذه الخدمة ودفعها.
        </p>
        <p v-if="service.loyalty_redemption_points" class="mt-1 text-sm text-white/90">
          أو استبدلها مجّانًا مقابل {{ service.loyalty_redemption_points }} نقطة.
        </p>
      </section>

      <!-- Team members who offer it -->
      <section v-if="service.doctors && service.doctors.length > 0" class="mx-4 mt-5">
        <h2 class="text-base font-bold text-text-primary mb-2">الفريق الذي يقدّم هذه الخدمة</h2>
        <ul class="grid grid-cols-2 gap-3">
          <li
            v-for="d in service.doctors"
            :key="d.id"
            class="bg-surface-card rounded-2xl border-2 border-brand/15 p-3 space-y-2"
          >
            <div
              v-if="d.image_path"
              class="w-full aspect-square rounded-xl bg-cover bg-center"
              :style="{ backgroundImage: `url(/storage/${d.image_path})` }"
              role="img"
              :aria-label="d.user?.name"
            />
            <div v-else class="w-full aspect-square rounded-xl bg-brand/10 grid place-items-center text-brand">
              <UserIcon class="w-10 h-10" aria-hidden="true" />
            </div>
            <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand/10 text-brand">{{ roleLabel(d) }}</span>
            <p class="text-sm font-extrabold text-text-primary truncate">{{ d.user?.name }}</p>
            <p class="text-[11px] text-text-secondary truncate">{{ d.specialty || 'متعدّد التخصّصات' }}</p>
            <p v-if="d.rating_average" class="text-[11px] text-warning font-bold inline-flex items-center gap-0.5">
              <Star class="w-3 h-3 fill-current" aria-hidden="true" />
              {{ Number(d.rating_average).toFixed(1) }}
            </p>
          </li>
        </ul>
      </section>

      <!-- Related services -->
      <section v-if="related.length > 0" class="mx-4 mt-5">
        <div class="flex items-center justify-between mb-2">
          <h2 class="text-base font-bold text-text-primary">خدمات مشابهة</h2>
          <Link :href="`/services?category=${service.category_id}`" class="text-xs font-bold text-brand inline-flex items-center gap-1">
            عرض الكل
            <ArrowRight class="w-3.5 h-3.5 rtl:rotate-180" aria-hidden="true" />
          </Link>
        </div>
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <li v-for="r in related" :key="r.id">
            <Link
              :href="`/services/${r.id}`"
              class="block bg-surface-card rounded-2xl shadow-sm overflow-hidden hover:shadow-md transition"
            >
              <div
                v-if="r.image_path"
                class="w-full aspect-[16/9] bg-cover bg-center"
                :style="{ backgroundImage: `url(/storage/${r.image_path})` }"
              />
              <div
                v-else
                :class="['w-full aspect-[16/9] flex items-center justify-center', colorClassForCategory(r.category)]"
              >
                <component :is="iconForCategory(r.category)" class="w-10 h-10 opacity-70" aria-hidden="true" />
              </div>
              <div class="p-3 space-y-1">
                <h3 class="text-sm font-bold text-text-primary truncate">{{ r.name }}</h3>
                <p class="text-xs text-brand font-bold">{{ r.base_price }} ₪ · {{ r.duration_minutes }} د</p>
              </div>
            </Link>
          </li>
        </ul>
      </section>

      <!-- Secondary CTA (bottom) -->
      <div class="mx-4 mt-6">
        <AuthGuardLink
          intent="booking"
          :authed-href="`/portal/booking?service=${service.id}`"
          :context="{ service: service.id }"
          class="block"
        >
          <Button class="w-full h-12 text-base font-bold">
            احجز موعدك الآن
          </Button>
        </AuthGuardLink>
      </div>
    </article>
  </ClientShell>
</template>
