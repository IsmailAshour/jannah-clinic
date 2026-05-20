<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { PageHeader, StatCard } from '@/Components/foundation'
import AdminShell from '@/Layouts/AdminShell.vue'

const page = usePage()
const clinicName = computed(() => page.props?.clinic?.name ?? 'عيادة جنّة')
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="لوحة التحكم" :eyebrow="clinicName" description="نظرة عامة (تُفعَّل المؤشرات في المراحل القادمة)" />
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard title="المواعيد اليوم" value="—" />
        <StatCard title="بانتظار مراجعة الدفع" value="—" />
        <StatCard title="عملاء جدد" value="—" />
        <StatCard title="الخدمات النشطة" value="—" />
      </div>
    </div>
  </AdminShell>
</template>
