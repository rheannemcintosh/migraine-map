<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import DayLogForm from '@/components/DayLogForm.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type Medication = {
    id: number;
    name: string;
    dose: string;
};

type RecordedMedication = {
    id: number;
    name: string;
    dose: string;
    quantity: number;
};

type Props = {
    today: string;
    scoreId: number | null;
    score: number | null;
    medications: Medication[];
    medicationsTaken: RecordedMedication[];
};

const props = defineProps<Props>();

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

const formattedToday = computed(() =>
    new Date(`${props.today}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="mx-auto flex h-full w-full max-w-lg flex-1 flex-col gap-4 p-4">
        <div class="space-y-1">
            <p class="text-muted-foreground text-sm">Today</p>
            <h1 class="text-2xl font-semibold tracking-tight">
                {{ formattedToday }}
            </h1>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>
                    {{ score === null ? 'Log your day' : 'Today so far' }}
                </CardTitle>
                <CardDescription>
                    {{
                        score === null
                            ? 'No score logged yet. Choose a migraine score from 0 (no migraine) to 10 (worst possible) and record any medications you took.'
                            : `Migraine score ${score} out of 10. Update the score or the medications recorded for today.`
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <DayLogForm
                    :key="scoreId ?? today"
                    :date="today"
                    :score-id="scoreId"
                    :score="score"
                    :medications="medications"
                    :recorded="medicationsTaken"
                    id-prefix="today-medication"
                >
                    <template #actions="{ submittable }">
                        <Button type="submit" :disabled="!submittable">
                            Save
                        </Button>
                    </template>
                </DayLogForm>
            </CardContent>
        </Card>
    </div>
</template>
