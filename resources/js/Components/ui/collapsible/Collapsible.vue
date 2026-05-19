<script setup>
import { CollapsibleRoot, useForwardPropsEmits } from "reka-ui";

const props = defineProps({
  defaultOpen: { type: Boolean, required: false },
  open: { type: Boolean, required: false, default: undefined },
  disabled: { type: Boolean, required: false },
  unmountOnHide: { type: Boolean, required: false, default: true },
  as: { type: null, required: false },
  asChild: { type: Boolean, required: false },
});
const emits = defineEmits(["update:open"]);

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <CollapsibleRoot v-slot="slotProps" v-bind="forwarded">
    <slot v-bind="slotProps" />
  </CollapsibleRoot>
</template>
