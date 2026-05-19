<script setup>
import { CollapsibleContent, useForwardPropsEmits } from "reka-ui";

const props = defineProps({
  forceMount: { type: Boolean, required: false },
  as: { type: null, required: false },
  asChild: { type: Boolean, required: false },
});
const emits = defineEmits(["contentFound"]);

const forwarded = useForwardPropsEmits(props, emits);
</script>

<template>
  <CollapsibleContent v-bind="{ ...forwarded, ...$attrs }">
    <slot />
  </CollapsibleContent>
</template>
