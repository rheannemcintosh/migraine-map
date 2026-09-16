<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, Pill } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { medicationExports, medications } from '@/routes';
import type { NavItem } from '@/types';

const tabs: NavItem[] = [
    {
        title: 'Medications',
        href: medications(),
        icon: Pill,
    },
    {
        title: 'Export',
        href: medicationExports(),
        icon: Download,
    },
];

const { isCurrentUrl } = useCurrentUrl();
</script>

<template>
    <nav
        class="flex gap-1 border-b pb-2"
        aria-label="Medications"
        role="tablist"
    >
        <Button
            v-for="tab in tabs"
            :key="toUrl(tab.href)"
            variant="ghost"
            size="sm"
            :class="{ 'bg-muted': isCurrentUrl(tab.href) }"
            as-child
        >
            <Link
                :href="tab.href"
                role="tab"
                :aria-selected="isCurrentUrl(tab.href)"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.title }}
            </Link>
        </Button>
    </nav>
</template>
