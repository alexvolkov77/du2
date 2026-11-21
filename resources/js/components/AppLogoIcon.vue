<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img
        src="/images/du2-logo.webp"
        alt="Школа Душноты"
        :class="[className, 'object-contain']"
        v-bind="$attrs"
    />
</template>
