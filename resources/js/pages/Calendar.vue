<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import DayLogForm from '@/components/DayLogForm.vue';
import PillIcon from '@/components/PillIcon.vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { calendar } from '@/routes';

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
    year: number;
    today: string;
    scores: Record<string, number>;
    scoreIds: Record<string, number>;
    medicationDays: string[];
    medicationsByDay: Record<string, RecordedMedication[]>;
    medications: Medication[];
};

type DayCell =
    | { kind: 'nonexistent'; key: string }
    | { kind: 'future'; key: string; date: string }
    | { kind: 'unscored'; key: string; date: string }
    | {
          kind: 'scored';
          key: string;
          date: string;
          id: number;
          score: number;
          tookMedication: boolean;
          medications: RecordedMedication[];
      };

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Calendar',
                href: calendar(),
            },
        ],
    },
});

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

const pad = (n: number): string => String(n).padStart(2, '0');

const daysInMonth = (year: number, month: number): number =>
    new Date(year, month + 1, 0).getDate();

const medicationDaySet = computed(() => new Set(props.medicationDays));

const rows = computed<DayCell[][]>(() =>
    Array.from({ length: 31 }, (_, dayIndex) => {
        const day = dayIndex + 1;

        return MONTHS.map((_, month): DayCell => {
            const key = `${props.year}-${pad(month + 1)}-${pad(day)}`;

            if (day > daysInMonth(props.year, month)) {
                return { kind: 'nonexistent', key };
            }

            if (key > props.today) {
                return { kind: 'future', key, date: key };
            }

            const score = props.scores[key];

            if (score !== undefined) {
                return {
                    kind: 'scored',
                    key,
                    date: key,
                    id: props.scoreIds[key],
                    score,
                    tookMedication: medicationDaySet.value.has(key),
                    medications: props.medicationsByDay[key] ?? [],
                };
            }

            return { kind: 'unscored', key, date: key };
        });
    }),
);

const cellClass: Record<DayCell['kind'], string> = {
    nonexistent: 'bg-muted/40 text-transparent',
    future: 'bg-muted text-muted-foreground/50',
    unscored:
        'cursor-pointer bg-red-500 text-white hover:bg-red-600 focus-visible:ring-2 focus-visible:ring-ring',
    scored: 'cursor-pointer bg-emerald-500 text-white hover:bg-emerald-600 focus-visible:ring-2 focus-visible:ring-ring',
};

const selectedDate = ref<string | null>(null);
const isOpen = computed({
    get: () => selectedDate.value !== null,
    set: (open: boolean) => {
        if (!open) {
            close();
        }
    },
});

const openDay = (cell: DayCell): void => {
    if (cell.kind !== 'unscored') {
        return;
    }

    selectedDate.value = cell.date;
};

const close = (): void => {
    selectedDate.value = null;
};

const editingCell = ref<Extract<DayCell, { kind: 'scored' }> | null>(null);
const isEditOpen = computed({
    get: () => editingCell.value !== null,
    set: (open: boolean) => {
        if (!open) {
            closeEdit();
        }
    },
});

const openScored = (cell: DayCell): void => {
    if (cell.kind !== 'scored') {
        return;
    }

    editingCell.value = cell;
};

const closeEdit = (): void => {
    editingCell.value = null;
};

const formattedEditDate = computed(() =>
    editingCell.value
        ? new Date(`${editingCell.value.date}T00:00:00`).toLocaleDateString(
              undefined,
              {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              },
          )
        : '',
);

const formattedSelectedDate = computed(() =>
    selectedDate.value
        ? new Date(`${selectedDate.value}T00:00:00`).toLocaleDateString(
              undefined,
              {
                  weekday: 'long',
                  day: 'numeric',
                  month: 'long',
                  year: 'numeric',
              },
          )
        : '',
);
</script>

<template>
    <Head :title="`Calendar ${year}`" />

    <div class="mx-auto flex h-full w-full max-w-lg flex-1 flex-col gap-4 p-4">
        <div class="flex items-center justify-between">
            <Button as-child variant="outline" size="icon">
                <Link
                    :href="calendar(year - 1)"
                    :aria-label="`Previous year (${year - 1})`"
                >
                    <ChevronLeft />
                </Link>
            </Button>

            <h1 class="text-2xl font-semibold tracking-tight">{{ year }}</h1>

            <Button as-child variant="outline" size="icon">
                <Link
                    :href="calendar(year + 1)"
                    :aria-label="`Next year (${year + 1})`"
                >
                    <ChevronRight />
                </Link>
            </Button>
        </div>

        <div class="overflow-x-auto">
            <table
                class="w-full min-w-[26rem] table-fixed border-separate border-spacing-1"
            >
                <thead>
                    <tr>
                        <th class="w-8"></th>
                        <th
                            v-for="month in MONTHS"
                            :key="month"
                            class="text-muted-foreground text-xs font-medium"
                        >
                            {{ month }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, dayIndex) in rows" :key="dayIndex">
                        <th
                            class="text-muted-foreground text-right text-xs font-medium"
                        >
                            {{ dayIndex + 1 }}
                        </th>
                        <td v-for="cell in row" :key="cell.key" class="p-0">
                            <button
                                v-if="cell.kind === 'unscored'"
                                type="button"
                                class="flex aspect-square w-full items-center justify-center rounded text-xs"
                                :class="cellClass[cell.kind]"
                                :aria-label="`Log score for ${cell.date}`"
                                :data-date="cell.date"
                                @click="openDay(cell)"
                            />
                            <button
                                v-else-if="cell.kind === 'scored'"
                                type="button"
                                class="relative flex aspect-square w-full items-center justify-center rounded text-xs font-semibold"
                                :class="cellClass[cell.kind]"
                                :aria-label="`Edit score for ${cell.date}`"
                                :data-date="cell.date"
                                :data-medication="
                                    cell.tookMedication ? 'true' : undefined
                                "
                                :title="`${cell.date}: score ${cell.score}${cell.tookMedication ? ', medication taken' : ''}`"
                                @click="openScored(cell)"
                            >
                                <span>{{ cell.score }}</span>
                                <PillIcon
                                    v-if="cell.tookMedication"
                                    class="absolute top-0.5 right-0.5 size-1.5"
                                    aria-label="Medication taken"
                                />
                            </button>
                            <div
                                v-else
                                class="flex aspect-square w-full items-center justify-center rounded text-xs font-semibold"
                                :class="cellClass[cell.kind]"
                                :data-date="
                                    cell.kind === 'nonexistent'
                                        ? undefined
                                        : cell.date
                                "
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="text-muted-foreground flex flex-wrap gap-4 text-xs"
            aria-label="Legend"
        >
            <span class="flex items-center gap-1.5">
                <span class="size-3 rounded bg-red-500" /> Needs a score
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-3 rounded bg-emerald-500" /> Scored
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="flex size-3 items-center justify-center rounded bg-emerald-500 text-white"
                >
                    <PillIcon class="size-2" aria-hidden="true" />
                </span>
                Medication taken
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-muted size-3 rounded" /> Future
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-muted/40 size-3 rounded" /> Not a date
            </span>
        </div>
    </div>

    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Log your day</DialogTitle>
                <DialogDescription>
                    {{ formattedSelectedDate }}. Choose a migraine score from 0
                    (no migraine) to 10 (worst possible) and record any
                    medications you took.
                </DialogDescription>
            </DialogHeader>

            <DayLogForm
                v-if="selectedDate"
                :key="selectedDate"
                :date="selectedDate"
                :score-id="null"
                :score="null"
                :medications="medications"
                :recorded="[]"
                id-prefix="medication"
                @saved="close"
            >
                <template #actions="{ submittable }">
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="close">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="!submittable">
                            Save
                        </Button>
                    </DialogFooter>
                </template>
            </DayLogForm>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="isEditOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Edit day</DialogTitle>
                <DialogDescription>
                    {{ formattedEditDate }}. Update the migraine score (0 = no
                    migraine, 10 = worst possible) and the medications recorded
                    for this day.
                </DialogDescription>
            </DialogHeader>

            <DayLogForm
                v-if="editingCell"
                :key="editingCell.id"
                :date="editingCell.date"
                :score-id="editingCell.id"
                :score="editingCell.score"
                :medications="medications"
                :recorded="editingCell.medications"
                id-prefix="edit-medication"
                @saved="closeEdit"
            >
                <template #actions="{ submittable }">
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeEdit"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="!submittable">
                            Save
                        </Button>
                    </DialogFooter>
                </template>
            </DayLogForm>
        </DialogContent>
    </Dialog>
</template>
