<script setup>
const props = defineProps({
  loyaltyEnabled: { type: Boolean, default: false },
  loyaltyRedemptionPoints: { type: Number, default: null },
  loyaltyBalance: { type: Number, default: 0 },
  modelValue: { type: String, default: 'cash' },
})
const emit = defineEmits(['update:modelValue'])

function pick(value) {
  emit('update:modelValue', value)
}

const showPicker = props.loyaltyEnabled && props.loyaltyRedemptionPoints && props.loyaltyRedemptionPoints > 0
const canRedeem = showPicker && props.loyaltyBalance >= props.loyaltyRedemptionPoints
</script>

<template>
  <fieldset v-if="showPicker" data-testid="picker" class="space-y-2">
    <legend class="text-sm font-semibold text-text-primary">طريقة الدفع</legend>
    <label class="flex items-center gap-2 p-3 rounded-md border border-border-default cursor-pointer">
      <input
        type="radio"
        name="payment_method"
        :checked="modelValue === 'cash'"
        @change="pick('cash')"
      />
      <span class="text-sm">نقدًا (تحويل بنكي)</span>
    </label>
    <label
      v-if="canRedeem"
      class="flex items-center gap-2 p-3 rounded-md border border-border-default cursor-pointer"
    >
      <input
        type="radio"
        name="payment_method"
        :checked="modelValue === 'loyalty_points'"
        @change="pick('loyalty_points')"
      />
      <span class="text-sm">
        بنقاط الولاء — يكلّف {{ loyaltyRedemptionPoints }} نقطة، رصيدك {{ loyaltyBalance }}
      </span>
    </label>
    <p v-else class="text-xs text-text-tertiary">
      رصيدك ({{ loyaltyBalance }}) لا يكفي للاستبدال بنقاط (المطلوب: {{ loyaltyRedemptionPoints }}).
    </p>
  </fieldset>
</template>
