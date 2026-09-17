<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { RotateCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import BodyMap from '@/components/BodyMap.vue';
import { bodyRegions, type BodyRegionId } from '@/lib/bodyRegions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { painLocation } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pain location',
                href: painLocation(),
            },
        ],
    },
});

const primary = ref<BodyRegionId | null>(null);
const secondary = ref<BodyRegionId[]>([]);

// First click sets the primary area; later clicks add secondaries. Clicking a
// selected region clears it, and clearing the primary promotes the oldest
// secondary so there is always a primary while anything is selected.
const toggle = (id: BodyRegionId): void => {
    if (primary.value === id) {
        primary.value = secondary.value.shift() ?? null;
        return;
    }

    if (secondary.value.includes(id)) {
        secondary.value = secondary.value.filter((region) => region !== id);
        return;
    }

    if (primary.value === null) {
        primary.value = id;
        return;
    }

    secondary.value = [...secondary.value, id];
};

const reset = (): void => {
    primary.value = null;
    secondary.value = [];
};

const labelFor = (id: BodyRegionId): string =>
    bodyRegions.find((region) => region.id === id)?.label ?? id;

const hasSelection = computed(
    () => primary.value !== null || secondary.value.length > 0,
);
</script>

<template>
    <Head title="Pain location" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Pain location
                </h1>
                <p class="text-muted-foreground text-sm">
                    Click where the pain is. The first area you choose is your
                    primary location; any others are secondary. Click an area
                    again to clear it.
                </p>
            </div>

            <Button
                variant="outline"
                class="cursor-pointer"
                :disabled="!hasSelection"
                @click="reset"
            >
                <RotateCcw />
                Clear
            </Button>
        </div>

        <div
            class="border-sidebar-border/70 dark:border-sidebar-border grid gap-6 rounded-xl border p-6 lg:grid-cols-[2fr_1fr]"
        >
            <div class="flex items-center justify-center">
                <BodyMap
                    :primary="primary"
                    :secondary="secondary"
                    @toggle="toggle"
                />
            </div>

            <div class="space-y-6">
                <section class="space-y-2">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span class="size-3 rounded-full bg-red-500" />
                        Primary
                    </h2>
                    <p
                        v-if="primary === null"
                        class="text-muted-foreground text-sm"
                    >
                        Not selected
                    </p>
                    <Badge v-else data-testid="primary-label">
                        {{ labelFor(primary) }}
                    </Badge>
                </section>

                <section class="space-y-2">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span class="size-3 rounded-full bg-amber-400" />
                        Secondary
                    </h2>
                    <p
                        v-if="secondary.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        None
                    </p>
                    <ul v-else class="flex flex-wrap gap-1">
                        <li v-for="id in secondary" :key="id">
                            <Badge variant="secondary">
                                {{ labelFor(id) }}
                            </Badge>
                        </li>
                    </ul>
                </section>

                <section class="space-y-2">
                    <h2 class="flex items-center gap-2 font-semibold">
                        <span class="bg-muted size-3 rounded-full border" />
                        Unselected
                    </h2>
                    <p class="text-muted-foreground text-sm">
                        Selections are kept in the page only and are not saved
                        yet.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
