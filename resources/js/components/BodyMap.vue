<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    figureOrigins,
    findRegion,
    headOutline,
    profileHeadOutline,
    profileTorsoOutline,
    regionShapes,
    torsoOutline,
    viewLabels,
    type BodyRegionId,
    type BodyView,
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

const hovered = ref<BodyRegionId | null>(null);

const hoveredRegion = computed(() =>
    hovered.value === null ? null : findRegion(hovered.value),
);

const views = Object.keys(figureOrigins) as BodyView[];

const isProfile = (view: BodyView): boolean => view.endsWith('-profile');

// Side markers show which anatomical side each edge of a figure is.
const edgeMarkers = (view: BodyView): [string, string] => {
    switch (view) {
        case 'front':
            return ['R', 'L'];
        case 'back':
            return ['L', 'R'];
        case 'left-profile':
            return ['Front', 'Back'];
        case 'right-profile':
            return ['Back', 'Front'];
    }
};

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
    <div class="flex w-full max-w-xl flex-col items-center gap-2">
        <svg
            viewBox="0 0 480 540"
            role="group"
            aria-label="Body map"
            class="h-auto w-full select-none"
        >
            <!-- Silhouette outlines give the regions context without being interactive. -->
            <g
                class="stroke-foreground/20 fill-none stroke-[1.5]"
                aria-hidden="true"
            >
                <g
                    v-for="view in views"
                    :key="view"
                    :transform="`translate(${figureOrigins[view].x} ${figureOrigins[view].y})`"
                >
                    <template v-if="isProfile(view)">
                        <g
                            :transform="
                                view === 'right-profile' ? 'scale(-1 1)' : ''
                            "
                        >
                            <path :d="profileHeadOutline" />
                            <path :d="profileTorsoOutline" />
                        </g>
                    </template>
                    <template v-else>
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
                    </template>
                    <text
                        x="-108"
                        y="120"
                        text-anchor="middle"
                        class="fill-muted-foreground stroke-none text-[10px]"
                    >
                        {{ edgeMarkers(view)[0] }}
                    </text>
                    <text
                        x="108"
                        y="120"
                        text-anchor="middle"
                        class="fill-muted-foreground stroke-none text-[10px]"
                    >
                        {{ edgeMarkers(view)[1] }}
                    </text>
                    <text
                        x="0"
                        y="264"
                        text-anchor="middle"
                        class="fill-muted-foreground stroke-none text-[10px]"
                    >
                        {{ viewLabels[view] }}
                    </text>
                </g>
            </g>

            <component
                v-for="shape in regionShapes"
                :key="`${shape.view}:${shape.region}`"
                :is="shape.shape"
                v-bind="shape.attributes"
                :class="regionClasses(shape.region)"
                :data-region="shape.region"
                :data-view="shape.view"
                :data-selection="selectionFor(shape.region) ?? 'none'"
                role="button"
                tabindex="0"
                :aria-label="findRegion(shape.region)?.label"
                :aria-pressed="selectionFor(shape.region) !== null"
                @click="emit('toggle', shape.region)"
                @keydown="onKeydown($event, shape.region)"
                @mouseenter="hovered = shape.region"
                @mouseleave="hovered = null"
                @focus="hovered = shape.region"
                @blur="hovered = null"
            >
                <title>
                    {{ findRegion(shape.region)?.label }} ({{
                        findRegion(shape.region)?.term
                    }})
                </title>
            </component>
        </svg>

        <p
            class="text-muted-foreground h-5 text-sm"
            aria-live="polite"
            data-testid="hovered-region"
        >
            <template v-if="hoveredRegion">
                <span class="text-foreground font-medium">
                    {{ hoveredRegion.label }}
                </span>
                &middot; {{ hoveredRegion.term }}
            </template>
            <template v-else>Hover over an area to see what it is</template>
        </p>
    </div>
</template>
