<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import PillIcon from '@/components/PillIcon.vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { calendar } from '@/routes';
import { store } from '@/routes/migraine-scores';

type Medication = {
    id: number;
    name: string;
    dose: string;
};

type Props = {
    year: number;
    today: string;
    scores: Record<string, number>;
    medicationDays: string[];
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
          score: number;
          tookMedication: boolean;
      };

type MedicationTaken = {
    id: number;
    quantity: number;
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

const SCORE_OPTIONS = Array.from({ length: 11 }, (_, i) => i);

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
                    score,
                    tookMedication: medicationDaySet.value.has(key),
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
    scored: 'bg-emerald-500 text-white',
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

const form = useForm<{
    date: string;
    score: number | null;
    medications: MedicationTaken[];
}>({
    date: '',
    score: null,
    medications: [],
});

const isSelected = (medication: Medication): boolean =>
    form.medications.some((taken) => taken.id === medication.id);

const quantityFor = (medication: Medication): number =>
    form.medications.find((taken) => taken.id === medication.id)?.quantity ?? 1;

const toggleMedication = (medication: Medication, checked: boolean): void => {
    form.medications = checked
        ? [...form.medications, { id: medication.id, quantity: 1 }]
        : form.medications.filter((taken) => taken.id !== medication.id);
};

const setQuantity = (medication: Medication, value: string | number): void => {
    const quantity = Math.max(1, Math.floor(Number(value)) || 1);

    form.medications = form.medications.map((taken) =>
        taken.id === medication.id ? { ...taken, quantity } : taken,
    );
};

const medicationError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return Object.keys(errors)
        .filter((key) => key.startsWith('medications'))
        .map((key) => errors[key])
        .find((message) => message !== undefined);
});

const openDay = (cell: DayCell): void => {
    if (cell.kind !== 'unscored') {
        return;
    }

    form.reset();
    form.clearErrors();
    form.date = cell.date;
    selectedDate.value = cell.date;
};

const close = (): void => {
    selectedDate.value = null;
    form.reset();
    form.clearErrors();
};

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

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
                            <div
                                v-else
                                class="relative flex aspect-square w-full items-center justify-center rounded text-xs font-semibold"
                                :class="cellClass[cell.kind]"
                                :data-date="
                                    cell.kind === 'nonexistent'
                                        ? undefined
                                        : cell.date
                                "
                                :data-medication="
                                    cell.kind === 'scored' &&
                                    cell.tookMedication
                                        ? 'true'
                                        : undefined
                                "
                                :title="
                                    cell.kind === 'scored'
                                        ? `${cell.date}: score ${cell.score}${cell.tookMedication ? ', medication taken' : ''}`
                                        : undefined
                                "
                            >
                                <span>{{
                                    cell.kind === 'scored' ? cell.score : ''
                                }}</span>
                                <PillIcon
                                    v-if="
                                        cell.kind === 'scored' &&
                                        cell.tookMedication
                                    "
                                    class="absolute top-0.5 right-0.5 size-1.5"
                                    aria-label="Medication taken"
                                />
                            </div>
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

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    class="grid grid-cols-6 gap-2 sm:grid-cols-11"
                    role="radiogroup"
                    aria-label="Score"
                >
                    <Button
                        v-for="option in SCORE_OPTIONS"
                        :key="option"
                        type="button"
                        role="radio"
                        :aria-checked="form.score === option"
                        :variant="form.score === option ? 'default' : 'outline'"
                        size="icon"
                        @click="form.score = option"
                    >
                        {{ option }}
                    </Button>
                </div>
                <InputError :message="form.errors.score" />
                <InputError :message="form.errors.date" />

                <fieldset class="space-y-2">
                    <legend class="text-sm font-medium">
                        Medications taken
                    </legend>
                    <p
                        v-if="medications.length === 0"
                        class="text-muted-foreground text-sm"
                    >
                        No ad hoc medications declared yet. Add them on the
                        Medications page to record them here.
                    </p>
                    <ul v-else class="space-y-2">
                        <li
                            v-for="medication in medications"
                            :key="medication.id"
                            class="flex min-h-9 items-center gap-3"
                        >
                            <Checkbox
                                :id="`medication-${medication.id}`"
                                :model-value="isSelected(medication)"
                                @update:model-value="
                                    (checked) =>
                                        toggleMedication(
                                            medication,
                                            checked === true,
                                        )
                                "
                            />
                            <Label
                                :for="`medication-${medication.id}`"
                                class="flex flex-1 flex-col items-start gap-0"
                            >
                                <span>{{ medication.name }}</span>
                                <span
                                    class="text-muted-foreground text-xs font-normal"
                                >
                                    {{ medication.dose }}
                                </span>
                            </Label>
                            <Input
                                v-if="isSelected(medication)"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                step="1"
                                class="w-20"
                                :aria-label="`Number of ${medication.name} taken`"
                                :model-value="quantityFor(medication)"
                                @update:model-value="
                                    (value) => setQuantity(medication, value)
                                "
                            />
                        </li>
                    </ul>
                    <InputError :message="medicationError" />
                </fieldset>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="close">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.score === null || form.processing"
                    >
                        Save
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
