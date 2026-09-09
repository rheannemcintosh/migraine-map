<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import DayLogForm from '@/components/DayLogForm.vue';
import PillIcon from '@/components/PillIcon.vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { calendar } from '@/routes';
import {
    destroy as destroyConfirmation,
    store as storeConfirmation,
} from '@/routes/medication-confirmations';

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

type ScheduledDose = {
    scheduleId: number;
    medicationId: number;
    name: string;
    dose: string;
    label: string;
    confirmationId: number | null;
};

type Props = {
    year: number;
    today: string;
    scores: Record<string, number>;
    scoreIds: Record<string, number>;
    medicationDays: string[];
    medicationsByDay: Record<string, RecordedMedication[]>;
    medications: Medication[];
    scheduledDosesByDay: Record<string, ScheduledDose[]>;
    missedMedicationDays: string[];
};

type DayCell =
    | { kind: 'nonexistent'; key: string }
    | { kind: 'future'; key: string; date: string }
    | { kind: 'unscored'; key: string; date: string; missedMedication: boolean }
    | {
          kind: 'scored';
          key: string;
          date: string;
          id: number;
          score: number;
          tookMedication: boolean;
          missedMedication: boolean;
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
const missedMedicationDaySet = computed(
    () => new Set(props.missedMedicationDays),
);

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
                    missedMedication: missedMedicationDaySet.value.has(key),
                    medications: props.medicationsByDay[key] ?? [],
                };
            }

            return {
                kind: 'unscored',
                key,
                date: key,
                missedMedication: missedMedicationDaySet.value.has(key),
            };
        });
    }),
);

// A recorded score is coloured on a fixed 11-step scale so every score 0-10
// has its own vivid, clearly distinguishable colour rather than a muddy
// interpolation: greens for the low end, a pair of yellows through the middle,
// then light orange to dark red climbing to 10.
const SCORE_COLORS = [
    '#157f3b', // 0  dark green
    '#43a047', // 1  green
    '#8bc34a', // 2  light green
    '#cddc39', // 3  yellow-green
    '#ffde3d', // 4  yellow
    '#ffc21f', // 5  golden yellow
    '#ffa52b', // 6  light orange
    '#fb8c00', // 7  orange
    '#ef6c00', // 8  dark orange
    '#e53935', // 9  red
    '#b71c1c', // 10 dark red
] as const;

const SEVERITY_GRADIENT = `linear-gradient(to right, ${SCORE_COLORS.join(', ')})`;

const clampScore = (score: number): number =>
    Math.min(Math.max(Math.round(score), 0), 10);

const scoreColor = (score: number): string => SCORE_COLORS[clampScore(score)];

// The pale middle of the scale needs dark text; the saturated ends need light
// text. Pick per cell from the colour's relative luminance.
const scoreTextColor = (score: number): string => {
    const hex = scoreColor(score).slice(1);
    const [r, g, b] = [0, 2, 4].map(
        (i) => parseInt(hex.slice(i, i + 2), 16) / 255,
    );

    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.62 ? '#1f2937' : '#ffffff';
};

// Past days without a score use a hatched fill so they never read as a green
// (low) score. Future days use an outlined, empty cell and non-existent days a
// faint flat block, so all three stay separable at a glance.
const HATCH_FILL =
    '[background-image:repeating-linear-gradient(45deg,var(--muted-foreground)_0,var(--muted-foreground)_1px,transparent_1px,transparent_5px)] [background-color:var(--muted)]';

const cellClass: Record<DayCell['kind'], string> = {
    nonexistent: 'bg-muted/30 text-transparent',
    future: 'border border-dashed border-muted-foreground/40 bg-transparent text-muted-foreground/40',
    unscored: `relative cursor-pointer border border-border text-muted-foreground hover:brightness-95 focus-visible:ring-2 focus-visible:ring-ring ${HATCH_FILL}`,
    scored: 'cursor-pointer hover:brightness-95 focus-visible:ring-2 focus-visible:ring-ring',
};

const classFor = (cell: DayCell): string => cellClass[cell.kind];

const MISSED_DOSE_DOT =
    'absolute top-0.5 left-0.5 size-1.5 rounded-full bg-white ring-1 ring-black';

// The marker's tooltip is a hover affordance, so only enable it on the larger,
// pointer-friendly screens where hovering a 6px target is realistic.
const showDoseTooltip = useMediaQuery('(min-width: 1024px)');

const styleFor = (cell: DayCell): Record<string, string> =>
    cell.kind === 'scored'
        ? {
              backgroundColor: scoreColor(cell.score),
              color: scoreTextColor(cell.score),
          }
        : {};

const scheduledDosesFor = (date: string | null): ScheduledDose[] =>
    date === null ? [] : (props.scheduledDosesByDay[date] ?? []);

const pendingDoseKey = ref<string | null>(null);

const doseKey = (date: string, dose: ScheduledDose): string =>
    `${date}:${dose.scheduleId}`;

const toggleDose = (
    date: string,
    dose: ScheduledDose,
    taken: boolean,
): void => {
    if (pendingDoseKey.value !== null) {
        return;
    }

    pendingDoseKey.value = doseKey(date, dose);

    const options = {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (pendingDoseKey.value = null),
    };

    if (taken) {
        router.post(
            storeConfirmation.url(),
            { medication_schedule_id: dose.scheduleId, date },
            options,
        );
    } else if (dose.confirmationId !== null) {
        router.delete(destroyConfirmation.url(dose.confirmationId), options);
    }
};

const todayClass = 'outline-foreground font-bold outline-2 outline-offset-2';

const isToday = (cell: DayCell): boolean =>
    cell.kind !== 'nonexistent' && cell.date === props.today;

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

        <TooltipProvider :delay-duration="150" disable-hoverable-content>
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
                                    class="relative flex aspect-square w-full items-center justify-center rounded text-xs"
                                    :class="[
                                        classFor(cell),
                                        isToday(cell) && todayClass,
                                    ]"
                                    :aria-label="`Log score for ${cell.date}`"
                                    :aria-current="
                                        isToday(cell) ? 'date' : undefined
                                    "
                                    :data-date="cell.date"
                                    :data-today="
                                        isToday(cell) ? 'true' : undefined
                                    "
                                    :data-missed-medication="
                                        cell.missedMedication
                                            ? 'true'
                                            : undefined
                                    "
                                    :title="
                                        cell.missedMedication
                                            ? `${cell.date}: scheduled medication missing`
                                            : undefined
                                    "
                                    @click="openDay(cell)"
                                >
                                    <Tooltip
                                        v-if="cell.missedMedication"
                                        :disabled="!showDoseTooltip"
                                    >
                                        <TooltipTrigger
                                            as="span"
                                            :class="MISSED_DOSE_DOT"
                                            aria-hidden="true"
                                        />
                                        <TooltipContent>
                                            Scheduled medication missing
                                        </TooltipContent>
                                    </Tooltip>
                                </button>
                                <button
                                    v-else-if="cell.kind === 'scored'"
                                    type="button"
                                    class="relative flex aspect-square w-full items-center justify-center rounded text-xs font-semibold"
                                    :class="[
                                        classFor(cell),
                                        isToday(cell) && todayClass,
                                    ]"
                                    :style="styleFor(cell)"
                                    :aria-label="`Edit score for ${cell.date}`"
                                    :aria-current="
                                        isToday(cell) ? 'date' : undefined
                                    "
                                    :data-date="cell.date"
                                    :data-today="
                                        isToday(cell) ? 'true' : undefined
                                    "
                                    :data-medication="
                                        cell.tookMedication ? 'true' : undefined
                                    "
                                    :data-missed-medication="
                                        cell.missedMedication
                                            ? 'true'
                                            : undefined
                                    "
                                    :title="`${cell.date}: score ${cell.score}${cell.tookMedication ? ', medication taken' : ''}${cell.missedMedication ? ', scheduled medication missing' : ''}`"
                                    @click="openScored(cell)"
                                >
                                    <span>{{ cell.score }}</span>
                                    <Tooltip
                                        v-if="cell.missedMedication"
                                        :disabled="!showDoseTooltip"
                                    >
                                        <TooltipTrigger
                                            as="span"
                                            :class="MISSED_DOSE_DOT"
                                            aria-hidden="true"
                                        />
                                        <TooltipContent>
                                            Scheduled medication missing
                                        </TooltipContent>
                                    </Tooltip>
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
        </TooltipProvider>

        <div
            class="text-muted-foreground flex flex-wrap gap-4 text-xs"
            aria-label="Legend"
        >
            <span class="flex items-center gap-1.5">
                <span
                    class="h-3 w-16 rounded"
                    :style="{ backgroundImage: SEVERITY_GRADIENT }"
                />
                Score 0 (green) to 10 (red)
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="border-border size-3 rounded border"
                    :class="HATCH_FILL"
                />
                Needs a score
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="border-border relative size-3 rounded border"
                    :class="HATCH_FILL"
                >
                    <span
                        class="absolute -top-0.5 -left-0.5 size-1.5 rounded-full bg-white ring-1 ring-black"
                    />
                </span>
                Scheduled medication missing
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="bg-muted-foreground flex size-3 items-center justify-center rounded text-white"
                >
                    <PillIcon class="size-2" aria-hidden="true" />
                </span>
                Medication taken
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="border-muted-foreground/40 size-3 rounded border border-dashed bg-transparent"
                />
                Future
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="bg-muted outline-foreground size-3 rounded outline-2 outline-offset-1"
                />
                Today
            </span>
            <span class="flex items-center gap-1.5">
                <span class="bg-muted/30 size-3 rounded" /> Not a date
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

            <fieldset
                v-if="selectedDate && scheduledDosesFor(selectedDate).length"
                class="space-y-2"
            >
                <legend class="text-sm font-medium">
                    Scheduled medications
                </legend>
                <p class="text-muted-foreground text-xs">
                    Tick each dose as you take it. These are saved immediately.
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="dose in scheduledDosesFor(selectedDate)"
                        :key="dose.scheduleId"
                        class="flex min-h-9 items-center gap-3"
                    >
                        <Checkbox
                            :id="`dose-${dose.scheduleId}`"
                            :model-value="dose.confirmationId !== null"
                            :disabled="pendingDoseKey !== null"
                            @update:model-value="
                                (checked) =>
                                    toggleDose(
                                        selectedDate!,
                                        dose,
                                        checked === true,
                                    )
                            "
                        />
                        <Label
                            :for="`dose-${dose.scheduleId}`"
                            class="flex flex-1 flex-col items-start gap-0"
                        >
                            <span>{{ dose.name }}</span>
                            <span
                                class="text-muted-foreground text-xs font-normal"
                            >
                                {{ dose.dose }} &middot; {{ dose.label }}
                            </span>
                        </Label>
                    </li>
                </ul>
            </fieldset>
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

            <fieldset
                v-if="editingCell && scheduledDosesFor(editingCell.date).length"
                class="space-y-2"
            >
                <legend class="text-sm font-medium">
                    Scheduled medications
                </legend>
                <p class="text-muted-foreground text-xs">
                    Tick each dose as you take it. These are saved immediately.
                </p>
                <ul class="space-y-2">
                    <li
                        v-for="dose in scheduledDosesFor(editingCell.date)"
                        :key="dose.scheduleId"
                        class="flex min-h-9 items-center gap-3"
                    >
                        <Checkbox
                            :id="`edit-dose-${dose.scheduleId}`"
                            :model-value="dose.confirmationId !== null"
                            :disabled="pendingDoseKey !== null"
                            @update:model-value="
                                (checked) =>
                                    toggleDose(
                                        editingCell!.date,
                                        dose,
                                        checked === true,
                                    )
                            "
                        />
                        <Label
                            :for="`edit-dose-${dose.scheduleId}`"
                            class="flex flex-1 flex-col items-start gap-0"
                        >
                            <span>{{ dose.name }}</span>
                            <span
                                class="text-muted-foreground text-xs font-normal"
                            >
                                {{ dose.dose }} &middot; {{ dose.label }}
                            </span>
                        </Label>
                    </li>
                </ul>
            </fieldset>
        </DialogContent>
    </Dialog>
</template>
