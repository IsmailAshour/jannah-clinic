<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, Home as HomeIcon, Lock, MapPinned, ServerCrash, WifiOff } from 'lucide-vue-next'
import ClientShell from '@/Layouts/ClientShell.vue'
import { Button } from '@/Components/ui/button'

const props = defineProps({
  status: { type: Number, default: 500 },
})

const META = {
  403: {
    icon: Lock,
    title: 'وصول غير مسموح',
    description: 'ليس لديك صلاحية للوصول إلى هذه الصفحة. إن كنت تظنّ أنّ هذا خطأ، تواصل معنا.',
  },
  404: {
    icon: MapPinned,
    title: 'الصفحة غير موجودة',
    description: 'يبدو أنّ الرابط الذي تتبعه لم يعد متوفّرًا. تأكّد من الرابط أو ارجع للصفحة الرئيسيّة.',
  },
  419: {
    icon: WifiOff,
    title: 'انتهت الجلسة',
    description: 'انتهت صلاحيّة الجلسة. أعد المحاولة بعد تحديث الصفحة.',
  },
  500: {
    icon: ServerCrash,
    title: 'حدث خلل في الخادم',
    description: 'صادفنا مشكلة غير متوقّعة. حاول لاحقًا، ولو استمرت تواصل مع الدعم.',
  },
  503: {
    icon: ServerCrash,
    title: 'الخدمة غير متاحة مؤقّتًا',
    description: 'نقوم بصيانة قصيرة. عُد بعد قليل.',
  },
}

const meta = computed(() => META[props.status] ?? META[500])
</script>

<template>
  <ClientShell>
    <Head :title="meta.title" />

    <div class="px-5 py-10 min-h-[60vh] flex items-center">
      <div class="w-full max-w-md mx-auto">
        <!-- Hero card: status badge + icon + headline + description -->
        <div class="bg-surface-card rounded-2xl shadow-lg ring-1 ring-border-default p-8 text-center space-y-5">
          <div class="mx-auto w-20 h-20 rounded-full bg-brand/10 text-brand grid place-items-center">
            <component :is="meta.icon" class="w-10 h-10" aria-hidden="true" />
          </div>

          <div class="space-y-1.5">
            <p class="text-xs font-extrabold tracking-widest text-brand" dir="ltr">ERROR {{ status }}</p>
            <h1 class="text-2xl font-extrabold text-text-primary">{{ meta.title }}</h1>
            <p class="text-sm text-text-secondary leading-relaxed">{{ meta.description }}</p>
          </div>

          <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-2">
            <Link href="/" class="flex-1">
              <Button class="w-full h-11 gap-1.5 font-bold">
                <HomeIcon class="w-4 h-4" aria-hidden="true" />
                <span>العودة للرئيسيّة</span>
              </Button>
            </Link>
            <Link href="/support" class="flex-1">
              <Button variant="outline" class="w-full h-11 gap-1.5 font-bold">
                <span>تواصل مع الدعم</span>
                <ArrowLeft class="w-4 h-4 rtl:rotate-180" aria-hidden="true" />
              </Button>
            </Link>
          </div>
        </div>

        <p class="mt-4 text-center text-[11px] text-text-tertiary">
          إذا كنت تستحقّ الوصول لهذه الصفحة، تواصل مع إدارة العيادة.
        </p>
      </div>
    </div>
  </ClientShell>
</template>
