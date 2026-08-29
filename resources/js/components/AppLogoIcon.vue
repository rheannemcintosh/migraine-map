<script setup lang="ts">
import { Brain } from '@lucide/vue';
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <Brain :class="className" v-bind="$attrs" />
</template>
