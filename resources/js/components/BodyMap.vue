<script setup lang="ts">
import { computed } from 'vue';
import {
    bodyRegions,
    figureOrigins,
    headOutline,
    torsoOutline,
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
        viewBox="0 0 480 280"
        role="group"
        aria-label="Body map"
        class="h-auto w-full max-w-xl select-none"
    >
        <!-- Silhouette outlines give the regions context without being interactive. -->
        <g
            class="stroke-foreground/20 fill-none stroke-[1.5]"
            aria-hidden="true"
        >
            <g
                v-for="(originX, view) in figureOrigins"
                :key="view"
                :transform="`translate(${originX} 0)`"
            >
                <ellipse v-bind="headOutline" />
                <path :d="torsoOutline" />
                <line
                    x1="0"
                    y1="44"
                    x2="0"
                    y2="250"
                    class="stroke-foreground/10"
                    stroke-dasharray="3 3"
                />
                <text
                    x="-108"
                    y="120"
                    text-anchor="middle"
                    class="fill-muted-foreground stroke-none text-[10px]"
                >
                    {{ view === 'front' ? 'R' : 'L' }}
                </text>
                <text
                    x="108"
                    y="120"
                    text-anchor="middle"
                    class="fill-muted-foreground stroke-none text-[10px]"
                >
                    {{ view === 'front' ? 'L' : 'R' }}
                </text>
            </g>
        </g>

        <text
            :x="figureOrigins.front"
            y="270"
            text-anchor="middle"
            class="fill-muted-foreground text-[10px]"
        >
            Front
        </text>
        <text
            :x="figureOrigins.back"
            y="270"
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
