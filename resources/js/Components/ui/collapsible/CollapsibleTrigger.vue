<script setup>
import { CollapsibleTrigger, useForwardProps } from "reka-ui";

const props = defineProps({
  as: { type: null, required: false },
  asChild: { type: Boolean, required: false },
});

const forwarded = useForwardProps(props);
</script>

<template>
  <CollapsibleTrigger v-bind="{ ...forwarded, ...$attrs }">
    <slot />
  </CollapsibleTrigger>
</template>
