<script setup>
import { PageHeader, StatCard } from '@/Components/foundation'
import AdminShell from '@/Layouts/AdminShell.vue'
</script>
<template>
  <AdminShell>
    <div class="p-6">
      <PageHeader title="لوحة التحكم" eyebrow="عيادة جنّة" description="نظرة عامة (تُفعَّل المؤشرات في المراحل القادمة)" />
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard title="المواعيد اليوم" value="—" />
        <StatCard title="بانتظار مراجعة الدفع" value="—" />
        <StatCard title="عملاء جدد" value="—" />
        <StatCard title="الخدمات النشطة" value="—" />
      </div>
    </div>
  </AdminShell>
</template>
