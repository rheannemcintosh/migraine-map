<script setup lang="ts">
import { computed } from 'vue';
import {
    bodyRegions,
    type BodyRegionId,
    type RegionSelection,
} from '@/lib/bodyRegions';

type Props = {
    primary: BodyRegionId | null;
    secondary: BodyRegionId[];
};

const props = defineProps<Props>();

const emit = defineEmits<{
    toggle: [id: BodyRegionId];
}>();

const frontRegions = computed(() =>
    bodyRegions.filter((region) => region.view === 'front'),
);

const backRegions = computed(() =>
    bodyRegions.filter((region) => region.view === 'back'),
);

const selectionFor = (id: BodyRegionId): RegionSelection => {
    if (props.primary === id) {
        return 'primary';
    }

    return props.secondary.includes(id) ? 'secondary' : null;
};

const regionClasses = (id: BodyRegionId): string => {
    const base =
        'cursor-pointer stroke-foreground/40 stroke-[1.5] transition-colors outline-none focus-visible:stroke-ring focus-visible:stroke-[3]';

    switch (selectionFor(id)) {
        case 'primary':
            return `${base} fill-red-500 hover:fill-red-600`;
        case 'secondary':
            return `${base} fill-amber-400 hover:fill-amber-500`;
        default:
            return `${base} fill-muted hover:fill-accent`;
    }
};

const onKeydown = (event: KeyboardEvent, id: BodyRegionId): void => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('toggle', id);
    }
};
</script>

<template>
    <svg
        viewBox="0 0 400 220"
        role="group"
        aria-label="Body map"
        class="h-auto w-full max-w-xl select-none"
    >
        <!-- Silhouette outlines give the regions context without being interactive. -->
        <g
            class="stroke-foreground/20 fill-none stroke-[1.5]"
            aria-hidden="true"
        >
            <path
                d="M 62 36 Q 100 10 138 36 Q 156 80 132 128 Q 116 142 100 142 Q 84 142 68 128 Q 44 80 62 36 Z"
            />
            <path
                d="M 84 136 L 82 166 L 20 178 M 116 136 L 118 166 L 180 178"
            />
            <path d="M 12 190 Q 100 214 188 190" />
            <path
                d="M 262 36 Q 300 10 338 36 Q 356 80 332 128 Q 316 142 300 142 Q 284 142 268 128 Q 244 80 262 36 Z"
            />
            <path
                d="M 284 136 L 282 166 L 220 178 M 316 136 L 318 166 L 380 178"
            />
            <path d="M 212 190 Q 300 214 388 190" />
        </g>

        <text
            x="100"
            y="216"
            text-anchor="middle"
            class="fill-muted-foreground text-[10px]"
        >
            Front
        </text>
        <text
            x="300"
            y="216"
            text-anchor="middle"
            class="fill-muted-foreground text-[10px]"
        >
            Back
        </text>

        <g v-for="group in [frontRegions, backRegions]" :key="group[0]?.view">
            <component
                v-for="region in group"
                :key="region.id"
                :is="region.shape"
                v-bind="region.attributes"
                :class="regionClasses(region.id)"
                :data-region="region.id"
                :data-selection="selectionFor(region.id) ?? 'none'"
                role="button"
                tabindex="0"
                :aria-label="region.label"
                :aria-pressed="selectionFor(region.id) !== null"
                @click="emit('toggle', region.id)"
                @keydown="onKeydown($event, region.id)"
            >
                <title>{{ region.label }}</title>
            </component>
        </g>
    </svg>
</template>
