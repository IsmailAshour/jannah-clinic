<script setup>
defineProps({ columns:{type:Array,required:true}, rows:{type:Array,default:()=>[]}, emptyText:{type:String,default:'لا توجد سجلات.'} })
</script>
<template>
  <div class="overflow-x-auto rounded-lg border border-border-default bg-surface-card">
    <table class="w-full text-sm">
      <thead class="sticky top-0 bg-surface-sunken">
        <tr><th v-for="c in columns" :key="c.key" class="px-4 py-3 font-medium text-text-secondary" :class="c.align==='end'?'text-end':'text-start'">{{ c.label }}</th></tr>
      </thead>
      <tbody>
        <tr v-if="rows.length===0"><td :colspan="columns.length" class="px-4 py-10 text-center text-text-tertiary">{{ emptyText }}</td></tr>
        <tr v-for="(r,i) in rows" :key="i" class="border-t border-border-default hover:bg-surface-sunken/60">
          <td v-for="c in columns" :key="c.key" class="px-4 py-3" :class="c.align==='end'?'text-end':'text-start'"><slot :name="`cell-${c.key}`" :row="r">{{ r[c.key] }}</slot></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
