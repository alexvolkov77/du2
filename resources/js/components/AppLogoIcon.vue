<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <img
        src="https://du2.ru/wp-content/uploads/2025/05/logo.png"
        alt="Школа Душноты"
        :class="className"
        v-bind="$attrs"
    />
</template>
