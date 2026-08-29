<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="border-sidebar-border/70 dark:border-sidebar-border flex flex-1 flex-col items-center justify-center gap-4 rounded-xl border p-8 text-center"
        >
            <AppLogoIcon class="text-primary size-12" />
            <h1 class="text-2xl font-semibold tracking-tight">Migraine Map</h1>
            <p class="text-muted-foreground">
                Track your triggers. See your patterns.
            </p>
        </div>
    </div>
</template>
